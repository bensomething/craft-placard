<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\integrations\FeedMe;
use craft\elements\Entry;
use PHPUnit\Framework\TestCase;

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

    /** An entry without its constructor, which needs a Craft app. */
    private function entry(): Entry
    {
        return (new \ReflectionClass(Entry::class))->newInstanceWithoutConstructor();
    }
}
