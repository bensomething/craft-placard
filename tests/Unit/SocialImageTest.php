<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\SocialImageValue;
use Craft;
use craft\base\ElementInterface;
use craft\console\Application;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\i18n\I18N;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\services\Fields;
use PHPUnit\Framework\TestCase;

class SocialImageTest extends TestCase
{
    protected function tearDown(): void
    {
        Craft::$app = null;
    }

    public function testNormalizesJsonArraysAndEmptyValues(): void
    {
        $field = new SocialImage(['defaultSource' => SocialImageValue::CUSTOM]);

        $fromJson = $field->normalizeValue('{"source":"field:hero","assetId":null}', null);
        $this->assertSame('field:hero', $fromJson->source);

        // The asset picker posts an array of ids.
        $fromPost = $field->normalizeValue(['source' => 'custom', 'assetId' => ['12']], null);
        $this->assertSame(12, $fromPost->assetId);

        $this->assertSame(SocialImageValue::CUSTOM, $field->normalizeValue(null, null)->source);
        $this->assertSame(SocialImageValue::CUSTOM, $field->normalizeValue(['source' => ''], null)->source);
    }

    public function testSerializeDropsAssetIdUnlessCustom(): void
    {
        $field = new SocialImage();

        $this->assertSame(
            ['source' => 'custom', 'assetId' => 5, 'style' => null],
            $field->serializeValue(['source' => 'custom', 'assetId' => 5], null),
        );
        $this->assertSame(
            ['source' => 'card', 'assetId' => null, 'style' => 'light'],
            $field->serializeValue(['source' => 'card', 'assetId' => 5, 'style' => 'light'], null),
        );
    }

    public function testNormalizesEmptyStyleToNull(): void
    {
        $field = new SocialImage();

        $this->assertNull($field->normalizeValue(['source' => 'card', 'style' => ''], null)->style);
        $this->assertSame('light', $field->normalizeValue('{"source":"card","style":"light"}', null)->style);
    }

    public function testCardCountsAsEmpty(): void
    {
        $field = new SocialImage();
        $element = $this->createStub(ElementInterface::class);

        $this->assertTrue($field->isValueEmpty(['source' => 'card'], $element));
        $this->assertFalse($field->isValueEmpty(['source' => 'custom'], $element));
        $this->assertFalse($field->isValueEmpty(['source' => 'card', 'style' => 'light'], $element));
    }

    public function testOptionsAreCardCustomThenGroupedFields(): void
    {
        $field = new SocialImage();
        $options = $field->options($this->entryWithAssetFields(['hero', 'gallery']));

        $this->assertSame(
            ['card', 'custom', null, 'field:hero', 'field:gallery'],
            array_map(fn(array $option) => $option['value'] ?? null, $options),
        );
        $this->assertSame('Image Fields', $options[2]['optgroup']);
    }

    public function testOptionsGroupLabelIsSingularForOneField(): void
    {
        $options = (new SocialImage())->options($this->entryWithAssetFields(['hero']));

        $this->assertSame('Image Field', $options[2]['optgroup']);
    }

    public function testOptionsFollowSourcesAndImageFields(): void
    {
        $entry = $this->entryWithAssetFields(['hero', 'gallery']);

        $field = new SocialImage([
            'sources' => [SocialImage::SOURCE_FIELDS],
            'imageFields' => ['uid-gallery'],
        ]);
        $values = array_column($field->options($entry), 'value');
        $this->assertSame(['field:gallery'], $values);

        $field = new SocialImage(['sources' => [SocialImage::SOURCE_CARD]]);
        $this->assertSame(['card'], array_column($field->options($entry), 'value'));
    }

    public function testEffectiveValueKeepsAllowedChoices(): void
    {
        $field = new SocialImage();
        $value = new SocialImageValue(['source' => 'field:hero']);

        $this->assertSame($value, $field->effectiveValue($value, $this->entryWithAssetFields(['hero'])));
    }

    public function testEffectiveValueFallsBackToDefaultThenCardThenField(): void
    {
        $entry = $this->entryWithAssetFields(['hero']);
        $missing = new SocialImageValue(['source' => 'field:gone', 'assetId' => 3]);

        $field = new SocialImage(['defaultSource' => 'field:hero']);
        $this->assertSame('field:hero', $field->effectiveValue($missing, $entry)->source);
        $this->assertNull($field->effectiveValue($missing, $entry)->assetId);

        $field = new SocialImage(['defaultSource' => 'field:gone']);
        $this->assertSame('card', $field->effectiveValue($missing, $entry)->source);

        // A custom image with nothing picked is the last resort.
        $field = new SocialImage([
            'sources' => [SocialImage::SOURCE_CUSTOM, SocialImage::SOURCE_FIELDS],
            'defaultSource' => 'card',
        ]);
        $this->assertSame('field:hero', $field->effectiveValue($missing, $entry)->source);
    }

    public function testEffectiveValueDropsStylesTheFieldDoesNotOffer(): void
    {
        $entry = $this->entryWithAssetFields(['hero']);
        $value = new SocialImageValue(['source' => 'card', 'style' => 'dark']);

        $this->assertSame('dark', (new SocialImage())->effectiveValue($value, $entry)->style);
        $this->assertSame('dark', (new SocialImage(['styles' => ['dark']]))->effectiveValue($value, $entry)->style);
        $this->assertNull((new SocialImage(['styles' => ['light']]))->effectiveValue($value, $entry)->style);
    }

    public function testEffectiveValueKeepsStyleWhenSourceFallsBack(): void
    {
        $value = new SocialImageValue(['source' => 'field:gone', 'style' => 'dark']);
        $resolved = (new SocialImage())->effectiveValue($value, $this->entryWithAssetFields(['hero']));

        $this->assertSame('card', $resolved->source);
        $this->assertSame('dark', $resolved->style);
    }

    public function testOptionsListAssetFieldsInsideMatrixFields(): void
    {
        $seoImage = $this->assetField('seoImage', 'SEO Image');
        $seo = $this->createStub(Matrix::class);
        $seo->handle = 'seo';
        $seo->name = 'SEO';
        // Two entry types sharing a field list it once.
        $seo->method('getFieldLayoutProviders')->willReturn([
            $this->entryType([$seoImage, $this->createStub(PlainText::class)]),
            $this->entryType([$seoImage]),
        ]);

        $entry = $this->entryWithFields([$seo, $this->assetField('hero')]);
        $options = (new SocialImage())->options($entry);

        // Top-level fields come first.
        $this->assertSame(
            ['card', 'custom', null, 'field:hero', 'field:seo.seoImage'],
            array_map(fn(array $option) => $option['value'] ?? null, $options),
        );
        $this->assertSame('SEO → SEO Image', $options[4]['label']);

        // Filtered by the inner field's UID.
        $field = new SocialImage(['imageFields' => ['uid-seoImage']]);
        $this->assertSame(['card', 'custom', null, 'field:seo.seoImage'], array_map(
            fn(array $option) => $option['value'] ?? null,
            $field->options($entry),
        ));
    }

    public function testHiddenLabelFallsBackToFieldNameThenHandle(): void
    {
        $global = $this->assetField('hero', 'Hero Image');
        $fields = $this->createStub(Fields::class);
        $fields->method('getFieldById')->willReturnMap([[1, $global], [2, null]]);
        $this->stubApp($fields);

        $hero = $this->assetField('hero', '__blank__');
        $hero->id = 1;
        $gone = $this->assetField('gone', '__blank__');
        $gone->id = 2;

        $options = (new SocialImage())->options($this->entryWithFields([$hero, $gone]));

        $this->assertSame('Hero Image', $options[3]['label']);
        $this->assertSame('gone', $options[4]['label']);
    }

    public function testIsSingleInstance(): void
    {
        $this->assertFalse(SocialImage::isMultiInstance());
    }

    /**
     * @param string[] $handles
     */
    private function entryWithAssetFields(array $handles): Entry
    {
        return $this->entryWithFields(array_map(fn(string $handle) => $this->assetField($handle), $handles));
    }

    /**
     * @param \craft\base\FieldInterface[] $fields
     */
    private function entryWithFields(array $fields): Entry
    {
        $entry = $this->createStub(Entry::class);
        $entry->method('getFieldLayout')->willReturn($this->layout($fields));

        return $entry;
    }

    /**
     * @param \craft\base\FieldInterface[] $fields
     */
    private function entryType(array $fields): EntryType
    {
        $entryType = $this->createStub(EntryType::class);
        $entryType->method('getFieldLayout')->willReturn($this->layout($fields));

        return $entryType;
    }

    /**
     * @param \craft\base\FieldInterface[] $fields
     */
    private function layout(array $fields): FieldLayout
    {
        $layout = $this->createStub(FieldLayout::class);
        $layout->method('getCustomFields')->willReturn($fields);

        return $layout;
    }

    /** An app with just the fields service, and translations passed through as-is. */
    private function stubApp(Fields $fields): void
    {
        $i18n = $this->createStub(I18N::class);
        $i18n->method('translate')->willReturnArgument(1);

        $app = $this->createStub(Application::class);
        $app->method('getFields')->willReturn($fields);
        $app->method('getI18n')->willReturn($i18n);
        Craft::$app = $app;
    }

    private function assetField(string $handle, ?string $name = null): Assets
    {
        $field = $this->createStub(Assets::class);
        $field->handle = $handle;
        $field->name = $name ?? ucfirst($handle);
        $field->uid = "uid-$handle";

        return $field;
    }
}
