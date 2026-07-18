<?php

namespace bensomething\herald\events;

use craft\elements\Asset;
use craft\elements\Entry;
use yii\base\Event;

/**
 * Fired by {@see \bensomething\herald\services\Generator::EVENT_DEFINE_CARD} before a
 * card is rendered, so each project decides how an entry maps to a card. The
 * plugin pre-fills sensible defaults (entry title, entry-type name, first share
 * image); a handler mutates whatever it needs:
 *
 *   use bensomething\herald\services\Generator;
 *   use bensomething\herald\events\DefineCardEvent;
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

    /** Heading (bottom-left). Defaults to the entry title. */
    public string $title = '';

    /** Kicker above the title. Defaults to the entry type name. */
    public string $label = '';

    /** Optional date line, top-right. */
    public ?string $date = null;

    /** Optional background photo (rendered faint + desaturated behind the card). */
    public ?Asset $background = null;
}
