<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\services\Generator;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Large JPEG backgrounds decode at a reduced scale, so memory follows the card's
 * size rather than the photo's (issue #1), while staying big enough to cover it.
 */
#[RequiresPhpExtension('imagick')]
class BackgroundDecodeTest extends TestCase
{
    public function testDecodesLargeJpegsAtAReducedScale(): void
    {
        $bg = $this->read($this->image(4000, 3000, 'jpeg'), 600, 315);

        $this->assertLessThan(4000, $bg->getImageWidth());
        $this->assertGreaterThanOrEqual(600, $bg->getImageWidth());
        $this->assertGreaterThanOrEqual(315, $bg->getImageHeight());
    }

    /** Both sides still cover the box, whichever way ImageMagick rounds the scale. */
    public function testKeepsWideAndTallJpegsCoveringTheBox(): void
    {
        foreach ([[8000, 1000], [1000, 8000]] as [$width, $height]) {
            $bg = $this->read($this->image($width, $height, 'jpeg'), 600, 315);

            $this->assertGreaterThanOrEqual(600, $bg->getImageWidth());
            $this->assertGreaterThanOrEqual(315, $bg->getImageHeight());
        }
    }

    public function testDecodesSmallJpegsAtFullSize(): void
    {
        $bg = $this->read($this->image(800, 400, 'jpeg'), 600, 315);

        $this->assertSame([800, 400], [$bg->getImageWidth(), $bg->getImageHeight()]);
    }

    public function testDecodesOtherFormatsAtFullSize(): void
    {
        $bg = $this->read($this->image(4000, 3000, 'png'), 600, 315);

        $this->assertSame([4000, 3000], [$bg->getImageWidth(), $bg->getImageHeight()]);
    }

    private function image(int $width, int $height, string $format): string
    {
        $image = new Imagick();
        $image->newPseudoImage($width, $height, 'gradient:red-blue');
        $image->setImageFormat($format);

        return $image->getImageBlob();
    }

    private function read(string $blob, int $boxW, int $boxH): Imagick
    {
        $generator = (new ReflectionClass(Generator::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($generator, 'readBackground'))->invoke($generator, $blob, $boxW, $boxH);
    }
}
