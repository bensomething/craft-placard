<?php

namespace bensomething\herald\controllers;

use bensomething\herald\Plugin;
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
        $hash = substr(md5("$card->title|$card->label|$card->date|$token|$bgKey"), 0, 10);

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

            file_put_contents($file, $generator->generate($card->title, $card->label, $backgroundBlob, $card->date));
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $response->content = file_get_contents($file);

        return $response;
    }
}
