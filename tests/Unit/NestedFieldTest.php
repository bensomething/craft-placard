<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\services\Generator;
use craft\elements\Asset;
use craft\elements\db\EntryQuery;
use craft\elements\ElementCollection;
use craft\elements\Entry;
use craft\fields\data\ColorData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Field settings take `outer.inner` paths into Matrix and Content Block fields.
 */
class NestedFieldTest extends TestCase
{
    public function testTopLevelHandleReadsTheField(): void
    {
        $asset = $this->createStub(Asset::class);

        $this->assertSame($asset, $this->firstImage($this->entry(['hero' => $asset]), ['hero']));
    }

    public function testPathReadsTheFirstNestedEntryWithAValue(): void
    {
        $asset = $this->createStub(Asset::class);
        $entry = $this->entry(['seo' => $this->query([
            $this->entry(['seoImage' => null]),
            $this->entry(['seoImage' => $asset]),
        ])]);

        $this->assertSame($asset, $this->firstImage($entry, ['seo.seoImage']));
    }

    public function testPathReadsEagerLoadedCollectionsAndContentBlocks(): void
    {
        $asset = $this->createStub(Asset::class);

        $eager = $this->entry(['seo' => new ElementCollection([$this->entry(['seoImage' => $asset])])]);
        $this->assertSame($asset, $this->firstImage($eager, ['seo.seoImage']));

        $block = $this->entry(['seo' => $this->entry(['seoImage' => $asset])]);
        $this->assertSame($asset, $this->firstImage($block, ['seo.seoImage']));
    }

    public function testPathsGoMoreThanOneLevelDeep(): void
    {
        $asset = $this->createStub(Asset::class);
        $entry = $this->entry(['builder' => [
            $this->entry(['hero' => $this->entry(['image' => $asset])]),
        ]]);

        $this->assertSame($asset, $this->firstImage($entry, ['builder.hero.image']));
    }

    public function testMissingFieldsFallThroughToTheNextHandle(): void
    {
        $asset = $this->createStub(Asset::class);
        $entry = $this->entry([
            'seo' => [$this->entry([])],
            'hero' => $asset,
        ]);

        $this->assertSame($asset, $this->firstImage($entry, ['missing.seoImage', 'seo.seoImage', 'hero']));
    }

    public function testNonElementValuesHaveNothingNested(): void
    {
        $entry = $this->entry(['title' => 'Hello']);

        $this->assertNull($this->firstImage($entry, ['title.image']));
    }

    public function testColourPathTakesTheFirstColourSet(): void
    {
        $entry = $this->entry(['seo' => [
            $this->entry(['colour' => 'not a colour']),
            $this->entry(['colour' => new ColorData('#ff0000')]),
            $this->entry(['colour' => '#00ff00']),
        ]]);

        $this->assertSame('#ff0000', $this->call('entryColor', $entry, 'seo.colour'));
    }

    /**
     * @param array<string, mixed> $values Fields not listed throw, as Craft does.
     */
    private function entry(array $values): Entry
    {
        $entry = $this->createStub(Entry::class);
        $entry->method('getFieldValue')->willReturnCallback(function(string $handle) use ($values) {
            if (!array_key_exists($handle, $values)) {
                throw new RuntimeException("Unknown field $handle");
            }

            return $values[$handle];
        });

        return $entry;
    }

    /**
     * @param Entry[] $entries
     */
    private function query(array $entries): EntryQuery
    {
        $query = $this->createStub(EntryQuery::class);
        $query->method('all')->willReturn($entries);

        return $query;
    }

    /**
     * @param string[] $handles
     */
    private function firstImage(Entry $entry, array $handles): ?Asset
    {
        return $this->call('firstImage', $entry, $handles);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        // init() needs the plugin, which isn't installed in unit tests.
        $generator = (new ReflectionClass(Generator::class))->newInstanceWithoutConstructor();

        return (fn() => $this->$method(...$args))->call($generator);
    }
}
