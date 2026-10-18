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

    public function testStyleOverridesWinOverEntryType(): void
    {
        $settings = new Settings([
            'sections' => ['news' => ['position' => 'top', 'align' => 'center']],
            'entryTypes' => ['article' => ['position' => 'middle']],
            'styles' => ['light' => [
                'name' => 'Light',
                'position' => 'between',
                'title' => ['color' => '#111111'],
            ]],
        ]);

        $resolved = $settings->forTypes('news', 'article', 'light');

        $this->assertSame('between', $resolved->position);
        $this->assertSame('center', $resolved->align);
        $this->assertSame('#111111', $resolved->titleColor);
        // An element array changes only the keys it names.
        $this->assertSame(116, $resolved->titleSize);
    }

    public function testUnknownOrEmptyStyleKeepsTypeSettings(): void
    {
        $settings = new Settings([
            'entryTypes' => ['article' => ['position' => 'middle']],
            'styles' => ['light' => ['position' => 'top']],
        ]);

        $this->assertSame('middle', $settings->forTypes(null, 'article', 'gone')->position);
        $this->assertSame('middle', $settings->forTypes(null, 'article', '')->position);
        $this->assertSame('middle', $settings->forTypes(null, 'article')->position);
    }

    public function testStylesCannotBeOverridden(): void
    {
        $settings = new Settings([
            'styles' => ['light' => ['styles' => [], 'position' => 'top']],
        ]);

        $this->assertSame($settings->styles, $settings->forTypes(null, null, 'light')->styles);
    }

    public function testStyleNamesFallBackToHandles(): void
    {
        $settings = new Settings([
            'styles' => [
                'light' => ['name' => 'Light Mode'],
                'dark' => ['position' => 'top'],
            ],
        ]);

        $this->assertSame(['light' => 'Light Mode', 'dark' => 'dark'], $settings->styleNames());
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

    public function testBorderReadsAsCssShorthand(): void
    {
        $box = fn(mixed $border): array => array_values((new Settings(['border' => $border]))->borderBox());

        $this->assertSame([0, 0, 0, 0], $box(0));
        $this->assertSame([8, 0, 0, 0], $box([8, 0, 0, 0]));
        $this->assertSame([4, 2, 4, 2], $box([4, 2]));
        // Unreadable values fall back to no border, not the padding default.
        $this->assertSame([0, 0, 0, 0], $box([1, 2, 3, 4, 5]));
        $this->assertSame([8, 0, 0, 0], $box([8, 'x', 0, 0]));
    }

    public function testTextSitsInsideTheBorder(): void
    {
        $settings = new Settings(['padding' => [60, 80], 'border' => [8, 0, 0, 4]]);

        $this->assertSame(['top' => 68, 'right' => 80, 'bottom' => 60, 'left' => 84], $settings->contentBox());
    }

    public function testValidatesBorder(): void
    {
        $this->assertTrue((new Settings(['border' => [8, 0, 0, 0]]))->validate());
        $this->assertFalse((new Settings(['border' => -1]))->validate());
        $this->assertFalse((new Settings(['border' => ['width' => []]]))->validate());
    }

    public function testElementArraysSetTheirSettings(): void
    {
        $settings = new Settings([
            'title' => [
                'text' => '{seoTitle}',
                'size' => 96,
                'lineHeight' => 1.1,
                'maxLines' => 2,
                'uppercase' => false,
                'color' => '#ff0000',
                'colorField' => 'seo.colour',
                'fontPath' => '@root/Display.otf',
                'gap' => 12,
            ],
            'description' => ['lineHeight' => 1.4, 'show' => false],
        ]);

        $this->assertSame('{seoTitle}', $settings->title);
        $this->assertSame(96, $settings->titleSize);
        $this->assertSame(1.1, $settings->titleLineHeight);
        $this->assertSame(2, $settings->titleMaxLines);
        $this->assertFalse($settings->titleUppercase);
        $this->assertSame('#ff0000', $settings->titleColor);
        $this->assertSame('seo.colour', $settings->titleColorField);
        $this->assertSame('@root/Display.otf', $settings->titleFontPath);
        $this->assertSame(12, $settings->titleGap);
        $this->assertSame(1.4, $settings->descriptionLineHeight);
        $this->assertFalse($settings->showDescription);
        // Untouched keys keep their defaults.
        $this->assertSame(32, $settings->descriptionSize);
    }

    public function testElementStringsAreTextAndBooleansShowOrHide(): void
    {
        $settings = new Settings(['label' => '{section.name}', 'meta' => false]);

        $this->assertSame('{section.name}', $settings->label);
        $this->assertFalse($settings->showMeta);
        $this->assertSame('', $settings->meta);
    }

    public function testFlatKeysStillWork(): void
    {
        $settings = new Settings(['titleSize' => 90, 'titleLineHeight' => 1.2, 'showMeta' => false]);

        $this->assertSame(90, $settings->titleSize);
        $this->assertSame(1.2, $settings->titleLineHeight);
        $this->assertFalse($settings->showMeta);
    }

    public function testElementKeysAnElementDoesNotHaveAreSkipped(): void
    {
        $settings = new Settings(['meta' => ['nonsense' => 1, 'size' => 20]]);

        $this->assertSame(20, $settings->metaSize);
        $this->assertFalse(property_exists($settings, 'metaNonsense'));
    }

    public function testLogoBackgroundAndBorderArraysSetTheirSettings(): void
    {
        $settings = new Settings([
            'logo' => ['path' => '@webroot/logo.svg', 'height' => 40],
            'background' => [
                'color' => '#1f1f1f',
                'colorField' => 'seo.colour',
                'imageField' => 'seo.seoImage',
                'useShareImage' => true,
                'imagePath' => '@webroot/bg.png',
                'fit' => 'contain',
                'padding' => 10,
                'radius' => 12,
                'grayscale' => true,
                'blur' => 4,
                'opacity' => 0.5,
                'blendMode' => 'multiply',
            ],
            'border' => ['width' => [8, 0, 0, 0], 'color' => '#f5b400', 'colorField' => 'accent'],
        ]);

        $this->assertSame('@webroot/logo.svg', $settings->logoPath);
        $this->assertSame(40, $settings->logoHeight);
        $this->assertSame('#1f1f1f', $settings->backgroundColor);
        $this->assertSame('seo.colour', $settings->backgroundColorField);
        $this->assertSame('seo.seoImage', $settings->backgroundImageField);
        $this->assertTrue($settings->useImageAsBackground);
        $this->assertSame('@webroot/bg.png', $settings->backgroundImagePath);
        $this->assertSame('contain', $settings->backgroundFit);
        $this->assertSame(10, $settings->backgroundPadding);
        $this->assertSame(12, $settings->backgroundRadius);
        $this->assertTrue($settings->backgroundGrayscale);
        $this->assertSame(4, $settings->backgroundBlur);
        $this->assertSame(0.5, $settings->backgroundOpacity);
        $this->assertSame('multiply', $settings->backgroundBlendMode);
        $this->assertSame([8, 0, 0, 0], $settings->border);
        $this->assertSame('#f5b400', $settings->borderColor);
        $this->assertSame('accent', $settings->borderColorField);
    }

    public function testGroupShorthands(): void
    {
        $settings = new Settings(['logo' => '@webroot/logo.png', 'background' => '#222222', 'border' => [8, 0, 0, 0]]);

        $this->assertSame('@webroot/logo.png', $settings->logoPath);
        $this->assertSame('#222222', $settings->backgroundColor);
        $this->assertSame(['top' => 8, 'right' => 0, 'bottom' => 0, 'left' => 0], $settings->borderBox());
        $this->assertSame(4, (new Settings(['border' => 4]))->borderBox()['left']);
        $this->assertFalse((new Settings(['logo' => false]))->showLogo);
    }

    public function testGroupOverridesChangeOnlyTheKeysTheyName(): void
    {
        $settings = new Settings([
            'background' => ['color' => '#000000', 'blur' => 8],
            'border' => ['width' => [8, 0, 0, 0], 'color' => '#ffffff'],
            'sections' => ['whatsOn' => ['border' => ['color' => '#33bbdd'], 'background' => ['blur' => 0]]],
        ]);

        $resolved = $settings->forTypes('whatsOn', null);

        $this->assertSame('#33bbdd', $resolved->borderColor);
        $this->assertSame([8, 0, 0, 0], $resolved->border);
        $this->assertSame(0, $resolved->backgroundBlur);
        $this->assertSame('#000000', $resolved->backgroundColor);
    }

    public function testSetAttributesExpandsElementArrays(): void
    {
        // How Craft applies config/placard.php.
        $settings = new Settings();
        $settings->setAttributes(['title' => ['size' => 80]], false);

        $this->assertSame(80, $settings->titleSize);
        $this->assertSame('{title}', $settings->title);
    }

    public function testElementOverridesChangeOnlyTheKeysTheyName(): void
    {
        $settings = new Settings([
            'title' => ['size' => 96, 'color' => '#ffffff'],
            'sections' => ['news' => ['title' => ['color' => '#ff0000']]],
            'entryTypes' => ['article' => ['title' => ['size' => 64], 'meta' => false]],
        ]);

        $resolved = $settings->forTypes('news', 'article');

        $this->assertSame('#ff0000', $resolved->titleColor);
        $this->assertSame(64, $resolved->titleSize);
        $this->assertFalse($resolved->showMeta);
        $this->assertSame(96, $settings->titleSize);
    }

    public function testValidatesElementSettings(): void
    {
        $this->assertTrue((new Settings(['description' => ['lineHeight' => 1.4, 'maxLines' => 3]]))->validate());
        $this->assertFalse((new Settings(['description' => ['lineHeight' => 0]]))->validate());
        $this->assertFalse((new Settings(['label' => ['maxLines' => 0]]))->validate());
    }

    public function testLayoutGroupsWrapSingleItems(): void
    {
        $this->assertSame(
            [['logoLabel'], ['title'], ['description'], ['meta']],
            (new Settings())->layoutGroups(),
        );
        $this->assertSame(
            [['logoLabel'], ['title', 'description', 'meta']],
            (new Settings(['layout' => [['logoLabel'], ['title', 'description', 'meta']]]))->layoutGroups(),
        );
        $this->assertSame(
            [['logo'], ['title', 'label']],
            (new Settings(['layout' => ['logo', ['title', 'label']]]))->layoutGroups(),
        );
    }

    public function testLayoutGroupsDropUnknownAndRepeatedItems(): void
    {
        $settings = new Settings(['layout' => [['logoLabel', 'logo'], ['sideways', 'title'], ['label'], [], 'title']]);

        $this->assertSame([['logoLabel'], ['title']], $settings->layoutGroups());
    }

    public function testLayoutIsOverridable(): void
    {
        $settings = new Settings([
            'styles' => ['split' => ['position' => 'between', 'layout' => [['logoLabel'], ['title', 'description']]]],
        ]);

        $this->assertSame([['logoLabel'], ['title', 'description']], $settings->forTypes(null, null, 'split')->layoutGroups());
        $this->assertCount(4, $settings->layoutGroups());
    }

    public function testValidatesLayout(): void
    {
        $this->assertTrue((new Settings(['layout' => [['logo', 'label'], ['title', 'meta']]]))->validate());
        $this->assertTrue((new Settings(['layout' => ['title']]))->validate());
        $this->assertFalse((new Settings(['layout' => ['title', 'subtitle']]))->validate());
        $this->assertFalse((new Settings(['layout' => [[], 'title']]))->validate());
        $this->assertFalse((new Settings(['layout' => ['title', 'title']]))->validate());
        $this->assertFalse((new Settings(['layout' => ['logoLabel', 'label']]))->validate());
        $this->assertFalse((new Settings(['layout' => [['logoLabel'], ['logo']]]))->validate());
    }

    public function testMetaHasItsOwnGap(): void
    {
        $settings = new Settings(['gap' => 20, 'meta' => ['gap' => 8]]);

        $this->assertSame(8, $settings->gapAfter('meta'));
        $this->assertSame(20, $settings->gapAfter('logo'));
    }

    public function testElementsAndLogoAlignOverTheCard(): void
    {
        $settings = new Settings([
            'align' => 'center',
            'meta' => ['align' => 'right'],
            'logo' => ['align' => 'left'],
            'styles' => ['flipped' => ['title' => ['align' => 'right']]],
        ]);

        $this->assertSame('right', $settings->alignFor('meta'));
        $this->assertSame('left', $settings->alignFor('logo'));
        $this->assertSame('center', $settings->alignFor('title'));
        $this->assertSame('right', $settings->forTypes(null, null, 'flipped')->alignFor('title'));
        $this->assertSame('right', $settings->forTypes(null, null, 'flipped')->alignFor('meta'));
    }

    public function testUnknownElementAlignFallsBack(): void
    {
        $settings = new Settings(['align' => 'right', 'title' => ['align' => 'sideways']]);

        $this->assertSame('right', $settings->alignFor('title'));
        $this->assertFalse($settings->validate());
        $this->assertTrue((new Settings(['label' => ['align' => 'center']]))->validate());
    }

    public function testCanvasIsFixed(): void
    {
        $settings = new Settings();

        $this->assertSame(1200, $settings->width);
        $this->assertSame(630, $settings->height);
    }
}
