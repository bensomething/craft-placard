<?php

namespace bensomething\herald\console\controllers;

use bensomething\herald\Plugin;
use Craft;
use craft\elements\Entry;
use craft\helpers\Console;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Renders sample cards to files for visual iteration.
 *
 *   php craft herald/preview <entryId>       # a real entry's resolved card
 *   php craft herald/preview --title="…"     # a dummy card from flags
 *   php craft herald/preview --title="…" --image=/path/to/photo.jpg
 *
 * Output goes to storage/runtime/og-images-preview/.
 */
class PreviewController extends Controller
{
    public ?string $title = 'The quick brown fox jumps over the lazy dog';
    public ?string $label = 'Preview';
    public ?string $date = null;
    public ?string $image = null;

    public function options($actionID): array
    {
        return ['title', 'label', 'date', 'image'];
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
            $blob = null;
            if ($card->background) {
                try {
                    $blob = $card->background->getContents();
                } catch (\Throwable) {
                }
            }

            $png = $generator->generate($card->title, $card->label, $blob, $card->date);
            $file = "$dir/entry-$entryId.png";
            file_put_contents($file, $png);
            $this->stdout("Wrote $file\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $blob = $this->image && is_file($this->image) ? file_get_contents($this->image) : null;
        $png = $generator->generate((string)$this->title, $this->label, $blob, $this->date);
        $file = "$dir/sample.png";
        file_put_contents($file, $png);
        $this->stdout("Wrote $file\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
