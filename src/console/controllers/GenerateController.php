<?php

namespace bensomething\placard\console\controllers;

use bensomething\placard\Plugin;
use craft\elements\Entry;
use craft\helpers\Console;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Renders the cards entries share into the cache, skipping any already there.
 * For backfilling after a config change or a bulk resave, which change every card.
 *
 *   php craft placard/generate
 *   php craft placard/generate --section=news --site=en
 */
class GenerateController extends Controller
{
    /** Section handles, comma-separated. */
    public ?string $section = null;

    /** Entry type handles, comma-separated. */
    public ?string $type = null;

    /** Site handles, comma-separated. All sites when unset. */
    public ?string $site = null;

    public function options($actionID): array
    {
        return ['section', 'type', 'site'];
    }

    public function actionIndex(): int
    {
        $generator = Plugin::getInstance()->getGenerator();
        $split = fn(?string $handles): ?array => $handles !== null ? array_filter(array_map('trim', explode(',', $handles))) : null;

        $query = Entry::find()
            ->site($split($this->site) ?? '*')
            ->uri(':notempty:');
        if ($this->section !== null) {
            $query->section($split($this->section));
        }
        if ($this->type !== null) {
            $query->type($split($this->type));
        }

        $rendered = $cached = $skipped = 0;
        foreach ($query->each() as $entry) {
            /** @var Entry $entry */
            if (!$generator->servesCard($entry)) {
                $skipped++;
                continue;
            }

            $card = $generator->defineCard($entry);
            if ($generator->isCached($card)) {
                $cached++;
                continue;
            }

            $generator->cachedCard($card);
            $rendered++;
            $this->stdout("Rendered $entry->title ($entry->id, site $entry->siteId)\n");
        }

        $this->stdout("$rendered rendered, $cached already cached, $skipped sharing their own image or without a card.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
