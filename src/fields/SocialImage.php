<?php

namespace bensomething\placard\fields;

use bensomething\placard\models\SocialImageValue;
use bensomething\placard\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\ContentBlock;
use craft\fields\Matrix;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\models\FieldLayout;
use craft\web\View;
use yii\base\InvalidConfigException;
use yii\db\Schema;

/**
 * Chooses which image an entry shares. Resolved by
 * {@see \bensomething\placard\services\Generator::shareImage()}.
 */
class SocialImage extends Field
{
    public const SOURCE_CARD = 'card';
    public const SOURCE_FIELDS = 'fields';
    public const SOURCE_CUSTOM = 'custom';

    /**
     * With only one, the dropdown is hidden and the field is just the preview.
     *
     * @var string[]
     */
    public array $sources = [self::SOURCE_CARD, self::SOURCE_FIELDS, self::SOURCE_CUSTOM];

    /**
     * `*` or field UIDs. Only those in the entry's own layout, or one level down
     * in its Matrix and Content Block fields, appear.
     *
     * @var string|string[]
     */
    public string|array $imageFields = '*';

    /** Also the fallback for a choice the settings no longer allow. */
    public string $defaultSource = SocialImageValue::CARD;

    /**
     * `*` or handles from the `styles` config. With none to offer, there's no
     * style dropdown.
     *
     * @var string|string[]
     */
    public string|array $styles = '*';

    /**
     * The entry's Social Image value, narrowed by {@see effectiveValue()}, or null
     * when its layout has no Social Image field.
     */
    public static function valueFor(Entry $entry): ?SocialImageValue
    {
        foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof self) {
                $value = $entry->getFieldValue($field->handle);

                // A source the field no longer offers falls back to its default.
                return $value instanceof SocialImageValue ? $field->effectiveValue($value, $entry) : null;
            }
        }

        return null;
    }

    public static function displayName(): string
    {
        return Craft::t('placard', 'Social Image');
    }

    public static function icon(): string
    {
        return 'hexagon-image';
    }

    /** An entry has one share image, so a layout holds this field at most once (see Plugin::init()). */
    public static function isMultiInstance(): bool
    {
        return false;
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
        $source = (string)($value['source'] ?? $this->defaultSource);
        // The asset picker posts an array of ids, even with a limit of one.
        $assetId = $value['assetId'] ?? null;
        if (is_array($assetId)) {
            $assetId = reset($assetId) ?: null;
        }

        $style = $value['style'] ?? null;

        return new SocialImageValue([
            'source' => $source !== '' ? $source : $this->defaultSource,
            'assetId' => $assetId ? (int)$assetId : null,
            'style' => is_string($style) && $style !== '' ? $style : null,
        ]);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);

        return [
            'source' => $value->source,
            // Dropped when unused, so switching away and saving clears it.
            'assetId' => $value->source === SocialImageValue::CUSTOM ? $value->assetId : null,
            'style' => $value->style,
        ];
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);

        // The card is the fallback for every choice, so it counts as "nothing picked".
        return $value->source === SocialImageValue::CARD && $value->style === null;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var SocialImageValue $value */
        $value = $this->normalizeValue($value, $element);
        $view = Craft::$app->getView();

        if (!$element instanceof Entry) {
            return '';
        }

        // No URL, no page to share. URLs are set per section/site (or Matrix field),
        // not entry type, so this is checked per entry rather than on the layout.
        if (!$this->hasUrls($element)) {
            return Html::tag('div', Craft::t('placard', 'This entry has no URL, so an image has not been generated.'), [
                'class' => ['pane', 'no-border', 'zilch', 'small'],
            ]);
        }

        $options = $this->options($element);
        $value = $this->effectiveValue($value, $element);

        $asset = $value->assetId ? Asset::find()->id($value->assetId)->kind('image')->one() : null;
        // The logged-in preview route, unlike the public one, renders drafts.
        $generator = Plugin::getInstance()->getGenerator();
        $cardUrl = $element->id ? $generator->previewUrl($element) : null;
        $imageUrl = $element->id ? $generator->previewImageUrl($element) : null;

        return $view->renderTemplate('placard/_field/input', [
            'field' => $this,
            'value' => $value,
            'entry' => $element,
            'options' => $options,
            'styleOptions' => $this->styleOptions(),
            'asset' => $asset,
            'imageUrl' => $imageUrl,
            'cardUrl' => $cardUrl,
        ], View::TEMPLATE_MODE_CP);
    }

    public function getSettingsHtml(): ?string
    {
        $assetFields = [];
        $nested = [];
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($field instanceof Assets) {
                $assetFields[] = $field;
            } else {
                array_push($nested, ...$this->nestedAssetFields($field));
            }
        }

        // Every path a default could name, as the entry's own fields aren't known here.
        $defaultFields = array_map(fn(Assets $field) => [
            'value' => SocialImageValue::FIELD_PREFIX . $field->handle,
            'label' => $this->fieldName($field),
        ], $assetFields);
        foreach ($nested as $option) {
            $defaultFields[] = ['value' => SocialImageValue::FIELD_PREFIX . $option['path'], 'label' => $option['label']];
        }

        return Craft::$app->getView()->renderTemplate('placard/_field/settings', [
            'field' => $this,
            'assetFields' => $assetFields,
            'defaultFields' => $defaultFields,
            'styleNames' => Plugin::getInstance()->getSettings()->styleNames(),
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['sources'], 'required', 'message' => Craft::t('placard', 'Choose at least one source.')];
        $rules[] = [['sources'], 'each', 'rule' => ['in', 'range' => [
            self::SOURCE_CARD, self::SOURCE_FIELDS, self::SOURCE_CUSTOM,
        ]]];

        return $rules;
    }

    /**
     * @return array<int, array{value: string, label: string}|array{optgroup: string}>
     */
    public function options(Entry $entry): array
    {
        $options = [];
        if (in_array(self::SOURCE_CARD, $this->sources, true)) {
            $options[] = ['value' => SocialImageValue::CARD, 'label' => Craft::t('placard', 'Generated Card')];
        }
        if (in_array(self::SOURCE_CUSTOM, $this->sources, true)) {
            $options[] = ['value' => SocialImageValue::CUSTOM, 'label' => Craft::t('placard', 'Custom Image')];
        }

        $fieldOptions = [];
        if (in_array(self::SOURCE_FIELDS, $this->sources, true)) {
            foreach ($this->layoutAssetFields($entry->getFieldLayout()) as $option) {
                if ($this->imageFields === '*' || in_array($option['uid'], (array)$this->imageFields, true)) {
                    $fieldOptions[] = [
                        'value' => SocialImageValue::FIELD_PREFIX . $option['path'],
                        'label' => $option['label'],
                    ];
                }
            }
        }
        if ($fieldOptions) {
            $group = count($fieldOptions) === 1 ? 'Image Field' : 'Image Fields';
            $options[] = ['optgroup' => Craft::t('placard', $group)];
            array_push($options, ...$fieldOptions);
        }

        return $options;
    }

    /**
     * The styles editors can choose, as handle => label, after "Default" for none.
     * Empty when the config has none this field offers.
     *
     * @return array<string, string>
     */
    public function styleOptions(): array
    {
        $names = array_filter(
            Plugin::getInstance()?->getSettings()->styleNames() ?? [],
            fn(string $handle) => $this->allowsStyle($handle),
            ARRAY_FILTER_USE_KEY,
        );

        return $names ? ['' => Craft::t('placard', 'Default'), ...$names] : [];
    }

    /**
     * Applied when sharing as well as in the input, so narrowed settings or a
     * hand-made request can't share something the field doesn't offer.
     */
    public function effectiveValue(SocialImageValue $value, Entry $entry): SocialImageValue
    {
        // A style dropped from the field's settings is no style. One missing from
        // the config is skipped when the settings are resolved.
        if ($value->style !== null && !$this->allowsStyle($value->style)) {
            $value = new SocialImageValue(['source' => $value->source, 'assetId' => $value->assetId]);
        }

        $allowed = array_column($this->options($entry), 'value');
        if (in_array($value->source, $allowed, true)) {
            return $value;
        }

        // Prefer anything over a custom image, which has nothing chosen yet.
        $fallbacks = array_values(array_diff($allowed, [SocialImageValue::CUSTOM]));
        $source = in_array($this->defaultSource, $allowed, true)
            ? $this->defaultSource
            : ($fallbacks[0] ?? $allowed[0] ?? SocialImageValue::CARD);

        return new SocialImageValue(['source' => $source, 'assetId' => null, 'style' => $value->style]);
    }

    private function allowsStyle(string $handle): bool
    {
        return $this->styles === '*' || in_array($handle, (array)$this->styles, true);
    }

    /**
     * The layout's asset fields, then those one level down in its Matrix and
     * Content Block fields as `outer.inner` paths.
     *
     * @return array<int, array{path: string, uid: string, label: string}>
     */
    private function layoutAssetFields(?FieldLayout $layout): array
    {
        $fields = [];
        $nested = [];
        foreach ($layout?->getCustomFields() ?? [] as $field) {
            if ($field instanceof Assets) {
                $fields[] = [
                    'path' => $field->handle,
                    'uid' => (string)$field->uid,
                    'label' => $this->fieldName($field),
                ];
            } else {
                array_push($nested, ...$this->nestedAssetFields($field));
            }
        }

        return [...$fields, ...$nested];
    }

    /**
     * Asset fields in a Matrix or Content Block field's layouts, once each
     * however many entry types share them.
     *
     * @return array<int, array{path: string, uid: string, label: string}>
     */
    private function nestedAssetFields(mixed $field): array
    {
        if (!$field instanceof Matrix && !$field instanceof ContentBlock) {
            return [];
        }

        $options = [];
        foreach ($field->getFieldLayoutProviders() as $provider) {
            foreach ($provider->getFieldLayout()->getCustomFields() as $inner) {
                $path = "$field->handle.$inner->handle";
                if ($inner instanceof Assets && !isset($options[$path])) {
                    $options[$path] = [
                        'path' => $path,
                        'uid' => (string)$inner->uid,
                        'label' => $this->fieldName($field) . ' → ' . $this->fieldName($inner),
                    ];
                }
            }
        }

        return array_values($options);
    }

    /**
     * A layout's copy of a field takes its label as its name, and a hidden label is
     * `__blank__`, so that falls back to the field's own name.
     */
    private function fieldName(FieldInterface $field): string
    {
        $name = $field->name;
        if ($name === '__blank__' && $field->id) {
            $name = Craft::$app->getFields()->getFieldById($field->id)->name ?? $field->handle;
        }

        return Craft::t('site', $name);
    }

    /** A misconfigured section counts as yes, so the field isn't lost. */
    private function hasUrls(Entry $entry): bool
    {
        try {
            return $entry->getUriFormat() !== null;
        } catch (InvalidConfigException) {
            return true;
        }
    }
}
