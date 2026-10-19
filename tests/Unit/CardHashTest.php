<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\events\DefineCardEvent;
use bensomething\placard\models\Settings;
use bensomething\placard\services\Generator;
use craft\elements\Asset;
use craft\elements\Entry;
use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Card URLs are served immutable, so the hash that versions them must change
 * whenever the card would, including changes that don't touch the entry.
 */
class CardHashTest extends TestCase
{
    public function testIsStableForTheSameCard(): void
    {
        $this->assertSame($this->hash($this->card()), $this->hash($this->card()));
    }

    public function testChangesWithTheBackgroundsFile(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        $card->background->dateModified = new DateTime('2026-10-02');

        $this->assertNotSame($before, $this->hash($card));
    }

    public function testChangesWithTheBackgroundsFocalPoint(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        // A focal point change saves the asset, but leaves its file's date alone.
        $card->background->dateUpdated = new DateTime('2026-10-02');

        $this->assertNotSame($before, $this->hash($card));
    }

    public function testChangesWithADifferentBackground(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        $card->background->id = 2;

        $this->assertNotSame($before, $this->hash($card));
    }

    public function testChangesWithTheText(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        // As from a DefineCard handler or a related entry, without the entry changing.
        $card->title = 'Another title';

        $this->assertNotSame($before, $this->hash($card));
    }

    public function testChangesWithTheSettings(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        $card->settings->titleColor = '#ff0000';

        $this->assertNotSame($before, $this->hash($card));
    }

    public function testChangesWithTheEntry(): void
    {
        $card = $this->card();
        $before = $this->hash($card);

        $card->entry->dateUpdated = new DateTime('2026-10-02');

        $this->assertNotSame($before, $this->hash($card));
    }

    private function card(): DefineCardEvent
    {
        $entry = (new ReflectionClass(Entry::class))->newInstanceWithoutConstructor();
        $entry->dateUpdated = new DateTime('2026-10-01');

        $background = (new ReflectionClass(Asset::class))->newInstanceWithoutConstructor();
        $background->id = 1;
        $background->dateModified = new DateTime('2026-10-01');
        $background->dateUpdated = new DateTime('2026-10-01');

        return new DefineCardEvent([
            'entry' => $entry,
            'settings' => new Settings(),
            'title' => 'A title',
            'label' => 'A label',
            'background' => $background,
        ]);
    }

    private function hash(DefineCardEvent $card): string
    {
        $generator = (new ReflectionClass(Generator::class))->newInstanceWithoutConstructor();

        return $generator->cardHash($card);
    }
}
