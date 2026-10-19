<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\services\Generator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Editor-chosen background images only reach ImageMagick as photo formats, as it
 * picks a reader from the contents whatever the file's extension.
 */
class BackgroundFormatTest extends TestCase
{
    /**
     * @dataProvider blobProvider
     */
    public function testAcceptsOnlyRasterFormats(string $blob, bool $expected): void
    {
        $generator = (new ReflectionClass(Generator::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($generator, 'isRasterImage');

        $this->assertSame($expected, $method->invoke($generator, $blob));
    }

    public static function blobProvider(): array
    {
        return [
            'jpeg' => ["\xFF\xD8\xFF\xE0\0\x10JFIF", true],
            'png' => ["\x89PNG\r\n\x1A\n\0\0\0\x0DIHDR", true],
            'gif87a' => ['GIF87a' . "\x01\0\x01\0", true],
            'gif89a' => ['GIF89a' . "\x01\0\x01\0", true],
            'webp' => ['RIFF' . "\x24\0\0\0" . 'WEBPVP8 ', true],
            'avif' => ["\0\0\0\x1Cftypavif\0\0\0\0avifmif1miaf", true],
            'avif as a compatible brand' => ["\0\0\0\x1Cftypmif1\0\0\0\0mif1avifmiaf", true],
            'heic' => ["\0\0\0\x18ftypheic\0\0\0\0mif1heic", false],
            'avif only as the minor version' => ["\0\0\0\x14ftypmif1avifmif1", false],
            'riff but not webp' => ['RIFF' . "\x24\0\0\0" . 'WAVEfmt ', false],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', false],
            'svg with xml prolog' => ['<?xml version="1.0"?><svg/>', false],
            'mvg' => ["push graphic-context\nviewbox 0 0 1 1\n", false],
            'msl' => ['<?xml version="1.0"?><image><read filename="x"/></image>', false],
            'pdf' => ['%PDF-1.7', false],
            'text' => ['hello', false],
            'empty' => ['', false],
        ];
    }
}
