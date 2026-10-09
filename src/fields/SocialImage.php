<?php

namespace bensomething\placard\fields;

use bensomething\placard\models\SocialImageValue;
use bensomething\placard\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\helpers\Json;
use craft\web\View;
use yii\db\Schema;

/**
 * Social Image field: lets editors choose, per entry, which image is shared — the
 * generated card, one of the entry's image fields, or a one-off image — with the
 * card preview alongside, updating as they choose.
 *
 * Resolved by {@see \bensomething\placard\services\Generator::shareImage()}, which is
 * what `craft.placard.shareImageUrl(entry)` returns. With no Social Image field in
 * an entry's layout, that's the `auto` behaviour.
 */
class SocialImage extends Field
{
    public static function displayName(): string
    {
        return Craft::t('placard', 'Social Image');
    }

    public static function icon(): string
    {
        return 'share-nodes';
    }

    public static function phpType(): string
    {
        return SocialImageValue::class;
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_JSON;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof SocialImageValue) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $value = Json::decodeIfJson($value);
        }

        $value = is_array($value) ? $value : [];
        $source = (string)($value['source'] ?? SocialImageValue::AUTO);
        // The asset picker posts an array of ids, even with a limit of one.
        $assetId = $value['assetId'] ?? null;
        if (is_array($assetId)) {
            $assetId = reset($assetId) ?: null;
        }

        return new SocialImageValue([
            'source' => $source !== '' ? $source : SocialImageValue::AUTO,
            'assetId' => $assetId ? (int)$assetId : null,
        ]);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);

        return [
            'source' => $value->source,
            // Only kept while it's in use, so switching away and saving clears it.
            'assetId' => $value->source === SocialImageValue::CUSTOM ? $value->assetId : null,
        ];
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);

        // `auto` is what an entry gets without the field, so it counts as unset.
        return $value->source === SocialImageValue::AUTO;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);
        $view = Craft::$app->getView();

        if (!$element instanceof Entry) {
            return '';
        }

        $imageFields = $this->imageFields($element);
        $options = [
            ['value' => SocialImageValue::AUTO, 'label' => Craft::t('placard', 'Automatic')],
            ['value' => SocialImageValue::CARD, 'label' => Craft::t('placard', 'Generated card')],
        ];
        foreach ($imageFields as $field) {
            $options[] = [
                'value' => SocialImageValue::FIELD_PREFIX . $field->handle,
                'label' => Craft::t('site', $field->name),
            ];
        }
        $options[] = ['value' => SocialImageValue::CUSTOM, 'label' => Craft::t('placard', 'Custom image')];

        $asset = $value->assetId ? Asset::find()->id($value->assetId)->kind('image')->one() : null;
        $settings = Plugin::getInstance()->getSettings()->forEntry($element);

        return $view->renderTemplate('placard/_field/input', [
            'field' => $this,
            'value' => $value,
            'entry' => $element,
            'options' => $options,
            'asset' => $asset,
            // What `auto` checks, in order — the configured handles this layout has.
            'autoHandles' => array_values(array_intersect(
                $settings->imageFieldHandles,
                array_map(fn(Assets $field) => $field->handle, $imageFields),
            )),
            'imageUrl' => $element->id ? Plugin::getInstance()->getGenerator()->shareImageUrl($element) : null,
            'cardUrl' => $element->id ? Plugin::getInstance()->getGenerator()->cardUrl($element) : null,
        ], View::TEMPLATE_MODE_CP);
    }

    /**
     * The asset fields in the entry's layout, other than this one, for the source
     * dropdown.
     *
     * @return Assets[]
     */
    private function imageFields(Entry $entry): array
    {
        $fields = [];
        foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof Assets) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
