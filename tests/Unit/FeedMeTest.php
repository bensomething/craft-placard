<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\integrations\FeedMe;
use craft\elements\Entry;
use craft\feedme\services\Process;
use PHPUnit\Framework\TestCase;
use yii\base\Event;

/**
 * Saves from a Feed Me import are told apart from other saves, including the
 * copies Craft saves in the entry's other sites.
 */
class FeedMeTest extends TestCase
{
    public function testUnmarkedEntryIsNotImporting(): void
    {
        $this->assertFalse(FeedMe::isImporting($this->entry()));
    }

    public function testMarkedEntryIsImporting(): void
    {
        $entry = $this->entry();
        FeedMe::markImporting($entry);

        $this->assertTrue(FeedMe::isImporting($entry));
    }

    public function testOtherSiteCopyIsImporting(): void
    {
        $entry = $this->entry();
        FeedMe::markImporting($entry);

        $copy = $this->entry();
        $copy->propagatingFrom = $entry;

        $this->assertTrue(FeedMe::isImporting($copy));
    }

    public function testOtherEntriesAreNotImporting(): void
    {
        FeedMe::markImporting($this->entry());

        $this->assertFalse(FeedMe::isImporting($this->entry()));
    }

    public function testIgnoresNonElements(): void
    {
        FeedMe::markImporting(null);

        $this->assertFalse(FeedMe::isImporting($this->entry()));
    }

    /**
     * Registering runs on every site with render-on-save on, so it mustn't load any
     * Feed Me class. Reading a constant off Process once took sites without Feed Me down.
     */
    public function testRegistersWithoutFeedMeInstalled(): void
    {
        if (class_exists(Process::class)) {
            $this->markTestSkipped('Feed Me is installed, so its absence can\'t be tested.');
        }

        try {
            FeedMe::register();
        } finally {
            Event::off(Process::class, 'onStepBeforeElementSave');
        }

        $this->assertFalse(class_exists(Process::class, false));
    }

    /** An entry without its constructor, which needs a Craft app. */
    private function entry(): Entry
    {
        return (new \ReflectionClass(Entry::class))->newInstanceWithoutConstructor();
    }
}
