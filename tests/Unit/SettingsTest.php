<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\models\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testEntryTypeOverridesWinOverSection(): void
    {
        $settings = new Settings([
            'position' => 'bottom',
            'sections' => ['news' => ['position' => 'top', 'align' => 'center']],
            'entryTypes' => ['article' => ['position' => 'middle']],
        ]);

        $resolved = $settings->forTypes('news', 'article');

        $this->assertSame('middle', $resolved->position);
        $this->assertSame('center', $resolved->align);
    }

    public function testUnmatchedHandlesKeepBaseSettings(): void
    {
        $settings = new Settings([
            'position' => 'bottom',
            'sections' => ['news' => ['position' => 'top']],
        ]);

        $this->assertSame('bottom', $settings->forTypes('blog', null)->position);
        $this->assertSame('bottom', $settings->forTypes(null, null)->position);
    }

    public function testIgnoresNonOverridableUnknownAndNonStringKeys(): void
    {
        $settings = new Settings([
            'sections' => ['news' => [
                'defaultImagePath' => 'img/other.png',
                'sections' => [],
                'notASetting' => true,
                0 => 'top',
                'titleColor' => '#ff0000',
            ]],
        ]);

        $resolved = $settings->forTypes('news', null);

        $this->assertSame('img/og-default.png', $resolved->defaultImagePath);
        $this->assertSame(['news' => $settings->sections['news']], $resolved->sections);
        $this->assertFalse(property_exists($resolved, 'notASetting'));
        $this->assertSame('#ff0000', $resolved->titleColor);
    }

    public function testReturnsACopy(): void
    {
        $settings = new Settings(['sections' => ['news' => ['position' => 'top']]]);

        $settings->forTypes('news', null)->position = 'middle';

        $this->assertSame('bottom', $settings->position);
    }

    public function testValidatesEnumSettings(): void
    {
        $this->assertTrue((new Settings())->validate());
        $this->assertFalse((new Settings(['position' => 'sideways']))->validate());
        $this->assertFalse((new Settings(['backgroundOpacity' => 2]))->validate());
        $this->assertTrue((new Settings(['backgroundBlendMode' => 'multiply']))->validate());
        $this->assertFalse((new Settings(['backgroundBlendMode' => 'hue']))->validate());
        $this->assertFalse((new Settings(['backgroundBlur' => -1]))->validate());
        $this->assertTrue((new Settings(['labelSize' => 40, 'logoHeight' => 64]))->validate());
        $this->assertFalse((new Settings(['metaSize' => 0]))->validate());
        $this->assertFalse((new Settings(['logoHeight' => -1]))->validate());
    }

    public function testFontPathSetsEveryElementUnlessOverridden(): void
    {
        $settings = new Settings([
            'fontPath' => '@root/Base.otf',
            'titleFontPath' => '@root/Title.otf',
        ]);

        $this->assertSame('@root/Title.otf', $settings->fontPathFor('title'));
        $this->assertSame('@root/Base.otf', $settings->fontPathFor('label'));
        $this->assertSame('@root/Base.otf', $settings->fontPathFor('description'));
        $this->assertSame('@root/Base.otf', $settings->fontPathFor('meta'));
        $this->assertSame('', (new Settings())->fontPathFor('title'));
    }

    public function testDisplayFontPathStillSetsTheTitle(): void
    {
        $settings = new Settings(['fontPath' => '@root/Base.otf', 'displayFontPath' => '@root/Display.otf']);

        $this->assertSame('@root/Display.otf', $settings->fontPathFor('title'));

        $settings->titleFontPath = '@root/Title.otf';
        $this->assertSame('@root/Title.otf', $settings->fontPathFor('title'));
    }

    public function testGapAfterFallsBackToGap(): void
    {
        $settings = new Settings(['gap' => 30, 'titleGap' => 0, 'descriptionGap' => -5]);

        $this->assertSame(30, $settings->gapAfter('label'));
        $this->assertSame(0, $settings->gapAfter('title'));
        $this->assertSame(0, $settings->gapAfter('description'));
        $this->assertSame(30, $settings->gapAfter('meta'));
        $this->assertSame(24, (new Settings())->gapAfter('title'));
        $this->assertFalse($settings->validate());
    }

    public function testPaddingReadsAsCssShorthand(): void
    {
        $box = fn(mixed $padding): array => array_values((new Settings(['padding' => $padding]))->paddingBox());

        $this->assertSame([80, 80, 80, 80], $box(80));
        $this->assertSame([60, 100, 60, 100], $box([60, 100]));
        $this->assertSame([10, 20, 30, 20], $box([10, 20, 30]));
        $this->assertSame([10, 20, 30, 40], $box([10, 20, 30, 40]));
        $this->assertSame([0, 80, 80, 80], $box([-5, 'x', 80, 80]));
        $this->assertSame([80, 80, 80, 80], $box([1, 2, 3, 4, 5]));
    }

    public function testValidatesPadding(): void
    {
        $this->assertTrue((new Settings(['padding' => [60, 100]]))->validate());
        $this->assertFalse((new Settings(['padding' => [60, -1]]))->validate());
        $this->assertFalse((new Settings(['padding' => []]))->validate());
        $this->assertFalse((new Settings(['padding' => [1, 2, 3, 4, 5]]))->validate());
    }

    public function testCanvasIsFixed(): void
    {
        $settings = new Settings();

        $this->assertSame(1200, $settings->width);
        $this->assertSame(630, $settings->height);
    }
}
