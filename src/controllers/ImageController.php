<?php

namespace bensomething\placard\controllers;

use bensomething\placard\events\DefineCardEvent;
use bensomething\placard\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\FileHelper;
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

        // Config values skip validation, so anything unknown is `auto`.
        $format = in_array($card->settings->format, ['png', 'jpeg'], true)
            ? $card->settings->format
            : ($card->background ? 'jpeg' : 'png');
        $dir = $generator->cachePath();
        FileHelper::createDirectory($dir);
        // Per site as well as entry, since an entry's card differs between sites and
        // the prune below would otherwise delete one site's card for another's.
        $prefix = "og-$entry->id-$entry->siteId-";
        $file = $dir . '/' . $prefix . $generator->cardHash($card) . ($format === 'jpeg' ? '.jpg' : '.png');

        // Read straight into memory, so a prune by another request can't remove the file mid-read.
        $content = is_file($file) ? @file_get_contents($file) : false;

        if ($content === false) {
            // A newly shared entry draws a burst of crawlers, so one request renders while
            // the rest wait for its file. Past the timeout, render anyway rather than fail.
            $mutex = Craft::$app->getMutex();
            $lock = 'placard-card:' . basename($file);
            $locked = $mutex->acquire($lock, 15);

            try {
                $content = is_file($file) ? @file_get_contents($file) : false;
                if ($content === false) {
                    $content = $this->renderToCache($card, $format, $file, $dir . '/' . $prefix);
                }
            } finally {
                if ($locked) {
                    $mutex->release($lock);
                }
            }
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $format === 'jpeg' ? 'image/jpeg' : 'image/png');
        $response->headers->set('Cache-Control', $cacheControl);
        $response->content = $content;

        return $response;
    }

    /** Renders the card into `$file`, then removes the entry's older cards (those starting `$prefixPath`). */
    private function renderToCache(DefineCardEvent $card, string $format, string $file, string $prefixPath): string
    {
        $content = Plugin::getInstance()->getGenerator()->generate(
            $card->title,
            $card->label,
            $card->background,
            $card->meta,
            $card->description,
            $card->backgroundColor,
            $card->settings,
            $format,
        );

        // Written aside and renamed in, so a concurrent request never serves a partial file.
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        if (file_put_contents($tmp, $content) !== false && rename($tmp, $file)) {
            // The entry's older versions are never requested again, as card URLs change with them.
            foreach (glob($prefixPath . '*') ?: [] as $old) {
                if ($old !== $file && !str_ends_with($old, '.tmp')) {
                    @unlink($old);
                }
            }
        } else {
            @unlink($tmp);
        }

        return $content;
    }
}
