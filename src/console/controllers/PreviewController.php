<?php

namespace bensomething\placard\console\controllers;

use bensomething\placard\Plugin;
use Craft;
use craft\elements\Entry;
use craft\helpers\Console;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Writes cards to storage/runtime/og-images-preview/.
 *
 *   php craft placard/preview <entryId>
 *   php craft placard/preview --title="…" --image=/path/to/photo.jpg
 */
class PreviewController extends Controller
{
    public ?string $title = 'The quick brown fox jumps over the lazy dog';
    public ?string $label = 'Preview';
    public ?string $description = null;
    public ?string $meta = null;
    public ?string $image = null;
    public ?string $backgroundColor = null;

    public function options($actionID): array
    {
        return ['title', 'label', 'description', 'meta', 'image', 'backgroundColor'];
    }

    public function actionIndex(?int $entryId = null): int
    {
        $dir = Craft::$app->getPath()->getRuntimePath() . '/og-images-preview';
        @mkdir($dir, 0777, true);

        $generator = Plugin::getInstance()->getGenerator();

        if ($entryId !== null) {
            $entry = Entry::find()->id($entryId)->status(null)->one();
            if (!$entry) {
                $this->stderr("Entry $entryId not found\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }

            $card = $generator->defineCard($entry);
            // The asset itself, not its contents, so a cover crop honours its focal point.
            $png = $generator->generate($card->title, $card->label, $card->backgroundImage, $card->meta, $card->description, $card->backgroundColor, $card->settings);
            $file = "$dir/entry-$entryId.png";
            file_put_contents($file, $png);
            $this->stdout("Wrote $file\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $blob = $this->image && is_file($this->image) ? file_get_contents($this->image) : null;
        $png = $generator->generate((string)$this->title, $this->label, $blob, $this->meta, $this->description, $this->backgroundColor);
        $file = "$dir/sample.png";
        file_put_contents($file, $png);
        $this->stdout("Wrote $file\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
