<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\services\Generator;
use craft\elements\Entry;
use craft\fields\data\ColorData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * The *ColorField settings read colours from entries. Anything that isn't a
 * colour must come back null, as Imagick throws on it.
 */
class EntryColorTest extends TestCase
{
    /**
     * @dataProvider valueProvider
     */
    public function testReadsColours(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, $this->entryColor($this->entryReturning($value), 'colour'));
    }

    public static function valueProvider(): array
    {
        return [
            'color field' => [new ColorData('#ff0000'), '#ff0000'],
            'hex text' => ['#00ff00', '#00ff00'],
            'bare hex' => ['00ff00', '00ff00'],
            'short hex' => ['#fff', '#fff'],
            'padded' => ['  #abcdef ', '#abcdef'],
            'empty' => ['', null],
            'null' => [null, null],
            'not a colour' => ['red', null],
            'bad hex' => ['#ggg', null],
            'array' => [['#fff'], null],
        ];
    }

    public function testEmptyHandleReadsNothing(): void
    {
        $entry = $this->createMock(Entry::class);
        $entry->expects($this->never())->method('getFieldValue');

        $this->assertNull($this->entryColor($entry, '  '));
    }

    public function testMissingFieldFallsBack(): void
    {
        $entry = $this->createStub(Entry::class);
        $entry->method('getFieldValue')->willThrowException(new RuntimeException('Unknown field'));

        $this->assertNull($this->entryColor($entry, 'missing'));
    }

    private function entryReturning(mixed $value): Entry
    {
        $entry = $this->createStub(Entry::class);
        $entry->method('getFieldValue')->willReturn($value);

        return $entry;
    }

    private function entryColor(Entry $entry, string $handle): ?string
    {
        // init() needs the plugin, which isn't installed in unit tests.
        $generator = (new ReflectionClass(Generator::class))->newInstanceWithoutConstructor();

        return (fn() => $this->entryColor($entry, $handle))->call($generator);
    }
}
