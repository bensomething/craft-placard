<?php

namespace bensomething\placard\events;

use bensomething\placard\models\Settings;
use craft\elements\Asset;
use craft\elements\Entry;
use yii\base\Event;

/**
 * Fired by {@see \bensomething\placard\services\Generator::EVENT_DEFINE_CARD} before a
 * card is rendered, so each project decides how an entry maps to a card. The
 * plugin pre-fills it from the settings (the text templates, background image
 * and colour fields); a handler mutates whatever it needs:
 *
 *   use bensomething\placard\services\Generator;
 *   use bensomething\placard\events\DefineCardEvent;
 *
 *   Event::on(Generator::class, Generator::EVENT_DEFINE_CARD, function (DefineCardEvent $e) {
 *       if ($e->entry->section?->handle === 'home') {
 *           $e->title = 'My Brand';
 *           $e->label = '';
 *       }
 *   });
 */
class DefineCardEvent extends Event
{
    /** The entry the card is being generated for (read-only input). */
    public Entry $entry;

    /**
     * The settings the card is drawn with: the plugin's, with the entry's section
     * and entry type overrides applied. A copy, so handlers can change it for this
     * card alone — e.g. `$e->settings->position = 'top'`.
     */
    public Settings $settings;

    /** Heading. From the `title` template, which defaults to the entry title. */
    public string $title = '';

    /** Kicker above the title. From the `label` template — the entry type name by default. */
    public string $label = '';

    /** Optional standfirst under the title, from the `description` template (none by default). */
    public ?string $description = null;

    /**
     * Optional meta line under the description — a date, byline, read time,
     * venue… From the `meta` template (none by default).
     */
    public ?string $meta = null;

    /** Optional background photo, drawn behind the text per the background settings. */
    public ?Asset $background = null;

    /**
     * Canvas colour for this card, as a hex value. Defaults to the entry's
     * `backgroundColorField`, if one is set; null falls back to `backgroundColor`.
     */
    public ?string $backgroundColor = null;
}
