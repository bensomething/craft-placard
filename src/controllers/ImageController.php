<?php

namespace bensomething\placard\controllers;

use bensomething\placard\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\FileHelper;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Streams generated OG cards: /og/<entryId>.png
 *
 * The card's content is resolved by the generator's DefineCard event (defaults +
 * per-project overrides); this controller only handles lookup, caching and
 * streaming, so it carries no site-specific logic.
 */
class ImageController extends Controller
{
    protected array|bool|int $allowAnonymous = ['generate'];

    public function actionGenerate(int $entryId): Response
    {
        // Include drafts / provisional drafts so SEO previews of unsaved entries
        // (slug `__temp_…`) resolve a card instead of 404ing.
        $entry = Entry::find()
            ->id($entryId)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->status(null)
            ->one();

        if (!$entry) {
            throw new NotFoundHttpException('Entry not found.');
        }

        $generator = Plugin::getInstance()->getGenerator();
        $card = $generator->defineCard($entry);

        $background = $card->background;
        $token = $entry->dateUpdated?->getTimestamp() ?? 0;
        $bgKey = $background ? $background->id . ':' . ($background->dateModified?->getTimestamp() ?? 0) : '';
        // Fingerprint the card's own settings (overrides and handler changes
        // included) so brand changes (fonts, palette, images) bust the cache, not just changes to the card's content.
        $design = md5(serialize($card->settings->getAttributes()));
        $hash = substr(md5("$card->title|$card->label|$card->description|$card->meta|$card->backgroundColor|$token|$bgKey|$design"), 0, 10);

        $dir = Craft::$app->getPath()->getRuntimePath() . '/og-images';
        FileHelper::createDirectory($dir);
        $file = "$dir/og-$entryId-$hash.png";

        if (!is_file($file)) {
            file_put_contents($file, $generator->generate(
                $card->title,
                $card->label,
                $this->assetContents($background),
                $card->meta,
                $card->description,
                $card->backgroundColor,
                $card->settings,
            ));
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $response->content = file_get_contents($file);

        return $response;
    }

    /**
     * Streams a preview card for the plugin's settings page. Not cached, so a config
     * change shows on reload.
     *
     * With no `section` / `type` handles it's placeholder text on the base settings.
     * With them, it's an entry of that pair rendered for real — `entryId` if it's
     * one of them, else the most recent. Its colour field, image and DefineCard
     * handlers are what those overrides are usually about, and placeholder text
     * would show none of them. If there's no such entry, it falls back to
     * placeholder text with the pair's settings.
     */
    public function actionSample(): Response
    {
        $this->requireAdmin(false);

        $generator = Plugin::getInstance()->getGenerator();
        $sectionHandle = $this->request->getQueryParam('section') ?: null;
        $typeHandle = $this->request->getQueryParam('type') ?: null;
        $entryId = (int)$this->request->getQueryParam('entryId') ?: null;
        // Looked up within the pair, so the picker can't be pointed elsewhere.
        $entry = $entryId
            ? $generator->sampleEntryQuery($sectionHandle, $typeHandle)?->id($entryId)->one()
            : $generator->sampleEntry($sectionHandle, $typeHandle);

        if ($entry) {
            $card = $generator->defineCard($entry);
            $png = $generator->generate(
                $card->title,
                $card->label,
                $this->assetContents($card->background),
                $card->meta,
                $card->description,
                $card->backgroundColor,
                $card->settings,
            );
        } else {
            $type = $typeHandle ? Craft::$app->getEntries()->getEntryTypeByHandle($typeHandle) : null;
            $png = $generator->generate(
                Craft::t('placard', 'The quick brown fox jumps over the lazy dog'),
                $type?->name ?? Craft::t('placard', 'Label'),
                null,
                date('j F Y'),
                Craft::t('placard', 'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'),
                null,
                Plugin::getInstance()->getSettings()->forTypes($sectionHandle, $typeHandle),
            );
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'no-store');
        $response->content = $png;

        return $response;
    }

    /**
     * The entries the settings preview can pick from for a section / entry type
     * pair, as JSON: the 10 most recently updated, newest first.
     */
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

    /**
     * An asset's URL as a share image (cropped to the card's size), for the Social
     * Image field to preview a pick before the entry's saved. Logged-in only (the
     * controller only lets `generate` through anonymously), and only for assets the
     * user can view.
     */
    public function actionAssetUrl(): Response
    {
        $this->requireAcceptsJson();

        $asset = Asset::find()->id((int)$this->request->getRequiredQueryParam('assetId'))->kind('image')->one();
        if (!$asset || !Craft::$app->getElements()->canView($asset)) {
            throw new NotFoundHttpException('Asset not found.');
        }

        return $this->asJson(['url' => Plugin::getInstance()->getGenerator()->assetShareUrl($asset)]);
    }

    /** An asset's file contents, or null when there's no asset or it can't be read. */
    private function assetContents(?Asset $asset): ?string
    {
        if (!$asset) {
            return null;
        }

        try {
            return $asset->getContents();
        } catch (\Throwable) {
            return null;
        }
    }
}
