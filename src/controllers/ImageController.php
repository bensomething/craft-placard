<?php

namespace bensomething\placard\controllers;

use bensomething\placard\models\Settings;
use bensomething\placard\Plugin;
use bensomething\placard\services\Generator;
use Craft;
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
    protected array|bool|int $allowAnonymous = true;

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
        // Fingerprint the settings so design changes (placement, fonts, palette,
        // dimensions) bust the cache, not just changes to the card's content.
        $design = md5(serialize(Plugin::getInstance()->getSettings()->getAttributes()));
        $hash = substr(md5("$card->title|$card->label|$card->description|$card->date|$token|$bgKey|$design"), 0, 10);

        $dir = Craft::$app->getPath()->getRuntimePath() . '/og-images';
        FileHelper::createDirectory($dir);
        $file = "$dir/og-$entryId-$hash.png";

        if (!is_file($file)) {
            $backgroundBlob = null;
            if ($background) {
                try {
                    $backgroundBlob = $background->getContents();
                } catch (\Throwable) {
                    $backgroundBlob = null;
                }
            }

            file_put_contents($file, $generator->generate(
                $card->title,
                $card->label,
                $backgroundBlob,
                $card->date,
                $card->description,
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
     * Streams a card rendered from sample text, so the CP settings page can preview
     * the design. Any query params matching settings attributes override the saved
     * value, letting the preview update live as fields change (before saving). Not
     * cached — it re-renders every request.
     */
    public function actionSample(): Response
    {
        $settings = $this->sampleSettings();

        $generator = new Generator();
        $generator->setSettings($settings);

        $png = $generator->generate(
            'The quick brown fox jumps over the lazy dog',
            Craft::t('placard', 'Article'),
            null,
            null,
            Craft::t('placard', 'A short standfirst, so the description row previews with something in it.'),
        );

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'no-store');
        $response->content = $png;

        return $response;
    }

    /**
     * Clones the saved settings and applies any query-param overrides, cast to each
     * attribute's type. Unknown params and array/asset settings are ignored.
     */
    private function sampleSettings(): Settings
    {
        $settings = clone Plugin::getInstance()->getSettings();
        $params = Craft::$app->getRequest()->getQueryParams();

        // The CP namespaces plugin-settings inputs under `settings`, which is how
        // the live-preview form serialises them; unwrap so keys match our attributes.
        if (isset($params['settings']) && is_array($params['settings'])) {
            $params = $params['settings'];
        }

        foreach ($params as $key => $value) {
            if (!property_exists($settings, $key) || is_array($value)) {
                continue;
            }

            $current = $settings->$key;
            $settings->$key = match (true) {
                is_int($current) => (int)$value,
                is_float($current) => (float)$value,
                is_bool($current) => in_array($value, ['1', 'true', 'on', true, 1], true),
                default => (string)$value,
            };
        }

        // The element table arrives as a nested array; normalize it into shape.
        if (isset($params['cardElements']) && is_array($params['cardElements'])) {
            $settings->cardElements = $params['cardElements'];
            $settings->normalizeCardElements();
        }

        return $settings;
    }
}
