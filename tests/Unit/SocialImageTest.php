<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\SocialImageValue;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use PHPUnit\Framework\TestCase;

class SocialImageTest extends TestCase
{
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
            ['source' => 'custom', 'assetId' => 5],
            $field->serializeValue(['source' => 'custom', 'assetId' => 5], null),
        );
        $this->assertSame(
            ['source' => 'card', 'assetId' => null],
            $field->serializeValue(['source' => 'card', 'assetId' => 5], null),
        );
    }

    public function testCardCountsAsEmpty(): void
    {
        $field = new SocialImage();
        $element = $this->createStub(ElementInterface::class);

        $this->assertTrue($field->isValueEmpty(['source' => 'card'], $element));
        $this->assertFalse($field->isValueEmpty(['source' => 'custom'], $element));
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

    private function assetField(string $handle, ?string $name = null): Assets
    {
        $field = $this->createStub(Assets::class);
        $field->handle = $handle;
        $field->name = $name ?? ucfirst($handle);
        $field->uid = "uid-$handle";

        return $field;
    }
}
