<?php

namespace bensomething\placard\events;

use bensomething\placard\models\Settings;
use craft\elements\Asset;
use craft\elements\Entry;
use yii\base\Event;

/**
 * Pre-filled from the settings before a card is rendered. Handlers change what they need.
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
    /** Read-only. */
    public Entry $entry;

    /** With the entry's overrides and colour fields applied. A copy, so safe to change for this card. */
    public Settings $settings;

    public string $title = '';

    public string $label = '';

    public ?string $description = null;

    public ?string $meta = null;

    public ?Asset $backgroundImage = null;

    /** A hex value. Null falls back to the `backgroundColor` setting. */
    public ?string $backgroundColor = null;

    /** @deprecated in 1.0.0-beta.16. Use {@see $backgroundImage}. */
    public function getBackground(): ?Asset
    {
        self::deprecated();
        return $this->backgroundImage;
    }

    /** @deprecated in 1.0.0-beta.16. Use {@see $backgroundImage}. */
    public function setBackground(?Asset $asset): void
    {
        self::deprecated();
        $this->backgroundImage = $asset;
    }

    private static function deprecated(): void
    {
        Settings::deprecated('DefineCardEvent.background', "Placard's `DefineCardEvent::\$background` is deprecated. Use `\$backgroundImage` instead.");
    }
}
