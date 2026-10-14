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

    public function testCanvasIsFixed(): void
    {
        $settings = new Settings();

        $this->assertSame(1200, $settings->width);
        $this->assertSame(630, $settings->height);
    }
}
