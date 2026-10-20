<?php

namespace bensomething\placard\controllers;

use bensomething\placard\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImageController extends Controller
{
    protected array|bool|int $allowAnonymous = ['generate'];

    /**
     * Public, so live entries with a URL only. Drafts and disabled entries 404 so
     * unpublished content can't be read off the URL, and so do entries without a page
     * (nested entries, URL-less sections), as their content may not be shown anywhere
     * and anyone could step through IDs to render them all. The CP uses
     * {@see actionPreview()}.
     */
    public function actionGenerate(int $entryId): Response
    {
        $entry = Entry::find()->id($entryId)->one();

        if (!$entry || $entry->getUrl() === null) {
            throw new NotFoundHttpException('Entry not found.');
        }

        return $this->cardResponse($entry, 'public, max-age=31536000, immutable');
    }

    /** Logged-in only, as it renders drafts and disabled entries. */
    public function actionPreview(): Response
    {
        $entry = $this->viewableEntry();

        // Versioned by `v` (and the field's `t`), so safe to keep, but only privately.
        return $this->cardResponse($entry, 'private, max-age=31536000, immutable');
    }

    /**
     * Not cached, so a config change shows on reload. A section/type pair renders a
     * real entry, since placeholder text wouldn't show its fields or DefineCard handlers.
     */
    public function actionSample(): Response
    {
        $this->requireAdmin(false);

        $generator = Plugin::getInstance()->getGenerator();
        $sectionHandle = $this->request->getQueryParam('section') ?: null;
        $typeHandle = $this->request->getQueryParam('type') ?: null;
        $entryId = (int)$this->request->getQueryParam('entryId') ?: null;
        // '' for none, rather than the entry's own choice.
        $style = (string)$this->request->getQueryParam('style', '');
        // Looked up within the pair, so the picker can't be pointed elsewhere.
        $entry = $entryId
            ? $generator->sampleEntryQuery($sectionHandle, $typeHandle)?->id($entryId)->one()
            : $generator->sampleEntry($sectionHandle, $typeHandle);

        if ($entry) {
            $card = $generator->defineCard($entry, $style);
            $png = $generator->generate(
                $card->title,
                $card->label,
                $card->background,
                $card->meta,
                $card->description,
                $card->backgroundColor,
                $card->settings,
            );
        } else {
            $type = $typeHandle ? Craft::$app->getEntries()->getEntryTypeByHandle($typeHandle) : null;
            $png = $generator->generate(
                Craft::t('placard', 'The quick brown fox jumps over the lazy dog'),
                $type->name ?? Craft::t('placard', 'Label'),
                null,
                date('j F Y'),
                Craft::t('placard', 'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'),
                null,
                Plugin::getInstance()->getSettings()->forTypes($sectionHandle, $typeHandle, $style),
            );
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'no-store');
        $response->content = $png;

        return $response;
    }

    public function actionSampleEntries(): Response
    {
        $this->requireAdmin(false);
        $this->requireAcceptsJson();

        $query = Plugin::getInstance()->getGenerator()->sampleEntryQuery(
            $this->request->getQueryParam('section') ?: null,
            $this->request->getQueryParam('type') ?: null,
        );

        $entries = [];
        foreach ($query?->limit(10)->all() ?? [] as $entry) {
            $entries[] = ['id' => $entry->id, 'title' => (string)$entry->title];
        }

        return $this->asJson(['entries' => $entries]);
    }

    /** For the Social Image field to refresh its preview after an autosave. */
    public function actionCardUrl(): Response
    {
        $this->requireAcceptsJson();

        return $this->asJson(['url' => Plugin::getInstance()->getGenerator()->previewUrl($this->viewableEntry())]);
    }

    /** For the Social Image field to preview a pick before the entry's saved. */
    public function actionAssetUrl(): Response
    {
        $this->requireAcceptsJson();

        $asset = Asset::find()->id((int)$this->request->getRequiredQueryParam('assetId'))->kind('image')->one();
        if (!$asset || !Craft::$app->getElements()->canView($asset)) {
            throw new NotFoundHttpException('Asset not found.');
        }

        return $this->asJson(['url' => Plugin::getInstance()->getGenerator()->assetShareUrl($asset)]);
    }

    private function viewableEntry(): Entry
    {
        $entry = Entry::find()
            ->id((int)$this->request->getRequiredQueryParam('entryId'))
            ->siteId((int)$this->request->getQueryParam('siteId') ?: null)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->status(null)
            ->one();

        if (!$entry || !Craft::$app->getElements()->canView($entry)) {
            throw new NotFoundHttpException('Entry not found.');
        }

        return $entry;
    }

    private function cardResponse(Entry $entry, string $cacheControl): Response
    {
        $generator = Plugin::getInstance()->getGenerator();
        $card = $generator->defineCard($entry);
        ['content' => $content, 'format' => $format] = $generator->cachedCard($card);

        // Drawn without a Google font that couldn't be downloaded yet, so not to be kept.
        if ($generator->fontsPending($card->settings)) {
            $cacheControl = 'no-store';
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $format === 'jpeg' ? 'image/jpeg' : 'image/png');
        $response->headers->set('Cache-Control', $cacheControl);
        $response->content = $content;

        return $response;
    }
}
