<?php

namespace bensomething\placard\models;

use bensomething\placard\fields\SocialImage;
use bensomething\placard\services\GoogleFonts;
use Craft;
use craft\base\Model;
use craft\elements\Entry;

/**
 * Set per project in `config/placard.php`. Paths may use aliases and env vars.
 *
 * @property-read int $width
 * @property-read int $height
 */
class Settings extends Model
{
    // -- Layout ---------------------------------------------------------------

    /**
     * Fixed at 1200×630 (1.91:1), the size every platform recommends. Anything
     * larger is bytes nobody sees, so it isn't a setting.
     */
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /**
     * Text elements in stack order. They flow in one stack, so a title that wraps
     * pushes what follows down rather than overlapping it. The values are fallbacks
     * for config that skips validation, matching the defaults of {@see $titleSize}
     * and the like.
     */
    public const ELEMENTS = [
        'label' => ['size' => 28, 'maxLines' => 1],
        'title' => ['size' => 116, 'maxLines' => 3],
        'description' => ['size' => 32, 'maxLines' => 2],
        'meta' => ['size' => 28, 'maxLines' => 1],
    ];

    /**
     * Keys of an element's config array (`'title' => ['size' => 96]`) and the
     * settings they set, with `{e}` as the element and `{E}` capitalised.
     */
    public const ELEMENT_KEYS = [
        'text' => '{e}',
        'show' => 'show{E}',
        'size' => '{e}Size',
        'lineHeight' => '{e}LineHeight',
        'maxLines' => '{e}MaxLines',
        'uppercase' => '{e}Uppercase',
        'color' => '{e}Color',
        'colorField' => '{e}ColorField',
        'fontPath' => '{e}FontPath',
        'googleFont' => '{e}GoogleFont',
        'gap' => '{e}Gap',
        'align' => '{e}Align',
    ];

    /** Keys of the other config arrays and the settings they set. */
    public const GROUP_KEYS = [
        'logo' => [
            'path' => 'logoPath',
            'height' => 'logoHeight',
            'show' => 'showLogo',
            'align' => 'logoAlign',
        ],
        'background' => [
            'color' => 'backgroundColor',
            'colorField' => 'backgroundColorField',
            'imageField' => 'backgroundImageField',
            'useShareImage' => 'useImageAsBackground',
            'imagePath' => 'backgroundImagePath',
            'imagePathOpacity' => 'backgroundImagePathOpacity',
            'fit' => 'backgroundFit',
            'padding' => 'backgroundPadding',
            'radius' => 'backgroundRadius',
            'grayscale' => 'backgroundGrayscale',
            'blur' => 'backgroundBlur',
            'opacity' => 'backgroundOpacity',
            'blendMode' => 'backgroundBlendMode',
        ],
        'border' => [
            'width' => 'border',
            'color' => 'borderColor',
            'colorField' => 'borderColorField',
        ],
        'gradient' => [
            'color' => 'gradientColor',
            'colorField' => 'gradientColorField',
            'from' => 'gradientFrom',
            'extent' => 'gradientExtent',
            'opacity' => 'gradientOpacity',
        ],
    ];

    /** What each group's key sets when given a single value rather than an array. */
    public const GROUP_SHORTHANDS = [
        'logo' => 'path',
        'background' => 'color',
        'border' => 'width',
        'gradient' => 'color',
    ];

    /** The edge or corner a gradient starts from. */
    public const GRADIENT_ORIGINS = ['top', 'right', 'bottom', 'left', 'topLeft', 'topRight', 'bottomLeft', 'bottomRight'];

    /** `between` pushes the first and last groups to the padding edges. */
    public const POSITIONS = ['top', 'middle', 'bottom', 'between'];

    /**
     * What {@see $layout} can place: the text elements, the logo on its own row, and
     * `logoLabel` for the logo beside the label.
     */
    public const LAYOUT_ITEMS = ['logoLabel', 'logo', 'label', 'title', 'description', 'meta'];

    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * `caps` runs from the first line's cap height to the last baseline, so text sits
     * at the same height on every card. `ink` uses each card's actual letter extents.
     */
    public const TEXT_EDGES = ['caps', 'ink'];

    public const BACKGROUND_FITS = ['cover', 'contain'];

    /** `auto` is JPEG for cards with a background image and PNG otherwise. */
    public const FORMATS = ['auto', 'png', 'jpeg'];

    /**
     * Named as in CSS `mix-blend-mode`. Hue, saturation, color and luminosity are
     * left out because Imagick ignores the image's opacity for them.
     */
    public const BLEND_MODES = [
        'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge',
        'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion',
    ];

    /** Between the logo and the label. */
    public const LOGO_GAP = 24;

    /** The default line height, as a multiple of the font size. */
    public const LINE_HEIGHT = 1.02;

    public const LOGO_SIZE = 48;

    /** The bundled Inter weight each element falls back to. */
    public const FONTS = [
        'label' => 'Inter-Medium.ttf',
        'title' => 'Inter-Bold.ttf',
        'description' => 'Inter-Regular.ttf',
        'meta' => 'Inter-Regular.ttf',
    ];

    /** The weight each element takes from a Google font that doesn't name one, as with Inter. */
    public const WEIGHTS = [
        'label' => 500,
        'title' => 700,
        'description' => 400,
        'meta' => 400,
    ];

    public string $position = 'bottom';

    /**
     * What's drawn, in order, from the top. Each item is a group of one, or an array
     * groups several. Items left out aren't drawn. Elements in a group keep their own
     * gaps, and `between` shares the leftover space between groups, so
     * `[['logoLabel'], ['title', 'description']]` puts the label at the top and the
     * title and description together at the bottom.
     *
     * @var array<array-key, mixed>
     */
    public array $layout = ['logoLabel', 'title', 'description', 'meta'];

    public string $align = 'left';

    public string $textEdges = 'caps';

    // Each element's settings below, and the logo's, background's and border's,
    // can also be set as one array under its name, see expand(). That's the
    // documented form.

    /** Lines before the text is clamped with an ellipsis. A title needing fewer is balanced across the ones it uses. */
    public int $titleMaxLines = 3;
    public int $labelMaxLines = 1;
    public int $descriptionMaxLines = 2;
    public int $metaMaxLines = 1;

    public bool $titleUppercase = true;
    public bool $labelUppercase = false;
    public bool $descriptionUppercase = false;
    public bool $metaUppercase = false;

    /** A multiple of the font size, as in CSS. The extra space is shared above and below each line. */
    public float $titleLineHeight = self::LINE_HEIGHT;
    public float $labelLineHeight = self::LINE_HEIGHT;
    public float $descriptionLineHeight = self::LINE_HEIGHT;
    public float $metaLineHeight = self::LINE_HEIGHT;

    /** In pixels. Long titles wrap and clamp rather than shrink to fit. */
    public int $titleSize = 116;

    /** In pixels. */
    public int $labelSize = 28;
    public int $descriptionSize = 32;
    public int $metaSize = 28;

    /** The logo's height in pixels, scaled to keep its proportions. */
    public int $logoHeight = self::LOGO_SIZE;

    /**
     * The inset text sits within, in px, as CSS shorthand: one value for every side,
     * [vertical, horizontal], [top, horizontal, bottom] or [top, right, bottom, left].
     * Text wraps at the width left between the sides.
     *
     * @var int|array<array-key, mixed>
     */
    public int|array $padding = 80;

    /**
     * Border widths in px, as CSS shorthand like {@see $padding}. Drawn over the
     * background image, with the text inset by it as well as by the padding.
     *
     * @var int|array<array-key, mixed>
     */
    public int|array $border = 0;

    /**
     * Space below each element in px, measured between line boxes, so the visible
     * space also takes in line height and descenders. With `between`, gaps between
     * groups are minimums, and the leftover space is shared equally between them.
     */
    public int $gap = 24;

    /** The space below one element, over {@see $gap}. Null uses $gap. */
    public ?int $labelGap = null;
    public ?int $titleGap = null;
    public ?int $descriptionGap = null;
    public ?int $metaGap = null;

    /**
     * An element's own alignment, over {@see $align}. Null uses $align. The label's
     * also aligns a logo beside it, and the logo's applies when it has a row of its own.
     */
    public ?string $labelAlign = null;
    public ?string $titleAlign = null;
    public ?string $descriptionAlign = null;
    public ?string $metaAlign = null;
    public ?string $logoAlign = null;

    /** Hiding the label keeps the logo, which has its own {@see $showLogo}. */
    public bool $showLabel = true;
    public bool $showTitle = true;
    public bool $showDescription = true;
    public bool $showMeta = true;
    public bool $showLogo = true;

    // -- Text -----------------------------------------------------------------

    // Object templates rendered against the entry. HTML is stripped, and empty skips the element.

    public string $title = '{title}';
    public string $label = '{type.name}';
    public string $description = '';
    public string $meta = '';

    // -- Palette --------------------------------------------------------------

    public string $backgroundColor = '#000000';

    /** A Color or hex text field. Empty or invalid values fall back to {@see $backgroundColor}. */
    public string $backgroundColorField = '';

    public string $labelColor = '#a3a3a3';
    public string $titleColor = '#ffffff';
    public string $descriptionColor = '#a3a3a3';
    public string $metaColor = '#a3a3a3';

    public string $borderColor = '#ffffff';

    /** As {@see $backgroundColorField}, falling back to the matching colour above. */
    public string $labelColorField = '';
    public string $titleColorField = '';
    public string $descriptionColorField = '';
    public string $metaColorField = '';
    public string $borderColorField = '';

    /**
     * Colour of the gradient drawn over the background, fading from
     * {@see $gradientFrom} to clear. Empty means no gradient.
     */
    public string $gradientColor = '';

    public string $gradientColorField = '';

    public string $gradientFrom = 'bottom';

    /** How far the gradient reaches across the card, as a fraction from its edge. */
    public float $gradientExtent = 0.7;

    /** Strength where it starts. */
    public float $gradientOpacity = 0.8;

    // -- Assets ---------------------------------------------------------------

    /**
     * Every element's font, unless it has its own below. Empty uses the bundled
     * Inter, in a weight per element ({@see FONTS}).
     */
    public string $fontPath = '';

    /** Each element's own font, over {@see $fontPath}. */
    public string $titleFontPath = '';
    public string $labelFontPath = '';
    public string $descriptionFontPath = '';
    public string $metaFontPath = '';

    /** @deprecated in 1.0.0-beta.2. Use {@see $titleFontPath}. */
    public string $displayFontPath = '';

    /**
     * A Google Fonts family for every element, as `Fraunces` or `Fraunces:700`,
     * downloaded on first use. Without a weight, each element takes its own
     * ({@see WEIGHTS}). A font path at the same level wins.
     */
    public string $googleFont = '';

    /** Each element's own Google font, over {@see $fontPath} and {@see $googleFont}. */
    public string $titleGoogleFont = '';
    public string $labelGoogleFont = '';
    public string $descriptionGoogleFont = '';
    public string $metaGoogleFont = '';

    /** A PNG or SVG. Empty renders no logo. */
    public string $logoPath = '';

    // -- Background -----------------------------------------------------------

    /** Falls back to {@see $useImageAsBackground}, then {@see $backgroundImagePath}. */
    public string $backgroundImageField = '';

    /** Uses the entry's first image from {@see $imageFieldHandles}. */
    public bool $useImageAsBackground = false;

    /** Used when the entry gives no background image. */
    public string $backgroundImagePath = '';

    public string $backgroundFit = 'cover';

    /**
     * Both fits work within this inset, with the canvas colour in the margin. CSS
     * shorthand like {@see $padding}, so `[0, 0, 0, 600]` puts the image on the
     * right half.
     *
     * @var int|array<array-key, mixed>
     */
    public int|array $backgroundPadding = 0;

    public int $backgroundRadius = 0;

    public bool $backgroundGrayscale = false;

    /** @deprecated in 1.0.0-beta.2. Use {@see $backgroundGrayscale}. */
    public bool $backgroundGreyscale = false;

    /** Blur strength in px, roughly CSS `blur()`. 0 is sharp. */
    public int $backgroundBlur = 0;

    public float $backgroundOpacity = 0.25;

    /** For {@see $backgroundImagePath} only. Null uses {@see $backgroundOpacity}. */
    public ?float $backgroundImagePathOpacity = null;

    /** How the background image blends with the canvas colour. */
    public string $backgroundBlendMode = 'normal';

    // -- Data derivation ------------------------------------------------------

    /**
     * Checked in order for an entry's own share image and default background.
     *
     * @var string[]
     */
    public array $imageFieldHandles = ['seoImage', 'hero', 'featuredImage'];

    /** Returned by `craft.placard.default()` for pages with no entry. */
    public string $defaultImagePath = 'img/og-default.png';

    // -- Output ---------------------------------------------------------------

    /**
     * File format of served cards. Photos are several times smaller as JPEG, and flat
     * cards keep crisper text as PNG.
     */
    public string $format = 'auto';

    /** 1 to 100. Higher is sharper and larger. */
    public int $jpegQuality = 85;

    /**
     * Gives SEOmatic and SEOmate each entry's share image, replacing the image their
     * own settings choose. Off for templates that pass the image themselves.
     */
    public bool $seoPlugins = true;

    /**
     * Renders an entry's card in a queue job when it's saved, so the first crawler
     * to ask doesn't wait on it. Crawlers like Facebook's and LinkedIn's give up on
     * slow images.
     */
    public bool $renderOnSave = true;

    // -- Per-type overrides ---------------------------------------------------

    /**
     * @var array<string, array<array-key, mixed>>
     */
    public array $sections = [];

    /**
     * Applied after section overrides, so the entry type wins.
     *
     * @var array<string, array<array-key, mixed>>
     */
    public array $entryTypes = [];

    /**
     * Named override sets editors choose between in the Social Image field, keyed
     * by handle. Applied after section and entry type overrides, so the editor's
     * choice wins. `name` labels the style in the field, else the handle is used.
     *
     * @var array<string, array<array-key, mixed>>
     */
    public array $styles = [];

    /**
     * The fallback image is for pages with no entry, so it can't vary by type. SEO
     * plugins are hooked into once, at startup.
     */
    public const NOT_OVERRIDABLE = ['sections', 'entryTypes', 'styles', 'defaultImagePath', 'seoPlugins', 'renderOnSave'];

    public function __construct($config = [])
    {
        parent::__construct(self::expand($config));
    }

    public function setAttributes($values, $safeOnly = true): void
    {
        parent::setAttributes(self::expand($values), $safeOnly);
    }

    /**
     * Turns config arrays into the settings they stand for, so `'title' =>
     * ['size' => 96]` sets {@see $titleSize} and `'logo' => ['height' => 40]`
     * sets {@see $logoHeight}. A single value sets the group's main setting (an
     * element's text, the logo's path, the background's colour, the border's
     * widths), and a boolean shows or hides it. Keys a group doesn't have are
     * logged and skipped.
     *
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     */
    public static function expand(array $config): array
    {
        foreach (self::groups() as $group => [$keys, $shorthand]) {
            if (!array_key_exists($group, $config)) {
                continue;
            }

            $value = $config[$group];
            unset($config[$group]);

            if (is_bool($value) && isset($keys['show'])) {
                $value = ['show' => $value];
            } elseif (!is_array($value) || ($value !== [] && array_is_list($value))) {
                // A list is the border's widths, not a group.
                $value = [$shorthand => $value];
            }

            foreach ($value as $key => $setting) {
                $name = $keys[$key] ?? '';
                if ($name === '' || !property_exists(self::class, $name)) {
                    Craft::warning("Placard has no \"$key\" setting for the $group.", __METHOD__);
                    continue;
                }

                $config[$name] = $setting;
            }
        }

        return $config;
    }

    /**
     * The entry's settings, with the style chosen in its Social Image field. Pass
     * `$style` to use another instead, or `''` for none.
     */
    public function forEntry(Entry $entry, ?string $style = null): self
    {
        $style ??= SocialImage::valueFor($entry)?->style;

        // Nested entries have no section, and their owner's doesn't apply to them.
        return $this->forTypes($entry->getSection()?->handle, $entry->getType()->handle, $style);
    }

    /** A copy with the section's overrides applied, then the entry type's, then the style's. */
    public function forTypes(?string $sectionHandle, ?string $entryTypeHandle, ?string $style = null): self
    {
        $settings = clone $this;

        foreach ([
            $sectionHandle !== null ? ($this->sections[$sectionHandle] ?? null) : null,
            $entryTypeHandle !== null ? ($this->entryTypes[$entryTypeHandle] ?? null) : null,
            $style ? ($this->styles[$style] ?? null) : null,
        ] as $overrides) {
            if (!is_array($overrides)) {
                continue;
            }
            unset($overrides['name']);

            // Expanded, so an element array changes only the keys it names.
            foreach (self::expand($overrides) as $name => $value) {
                if (is_string($name) && property_exists($settings, $name) && !in_array($name, self::NOT_OVERRIDABLE, true)) {
                    $settings->$name = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Each style's label, keyed by handle.
     *
     * @return array<string, string>
     */
    public function styleNames(): array
    {
        $names = [];
        foreach ($this->styles as $handle => $style) {
            $name = $style['name'] ?? null;
            $names[(string)$handle] = is_string($name) && $name !== '' ? $name : (string)$handle;
        }

        return $names;
    }

    /**
     * The configured font for an element, as a file path or a Google font, or null for
     * the bundled one. The element's own font wins over the global one, and at each
     * level a path wins over a Google font.
     *
     * @return array{path: string}|array{google: string}|null
     */
    public function fontFor(string $element): ?array
    {
        $ownPath = match ($element) {
            'title' => $this->titleFontPath ?: $this->displayFontPath,
            'label' => $this->labelFontPath,
            'description' => $this->descriptionFontPath,
            'meta' => $this->metaFontPath,
            default => '',
        };
        $ownGoogle = isset(self::ELEMENTS[$element]) ? $this->{$element . 'GoogleFont'} : '';

        return match (true) {
            trim($ownPath) !== '' => ['path' => $ownPath],
            trim($ownGoogle) !== '' => ['google' => $ownGoogle],
            trim($this->fontPath) !== '' => ['path' => $this->fontPath],
            trim($this->googleFont) !== '' => ['google' => $this->googleFont],
            default => null,
        };
    }

    /**
     * {@see $padding} as one value per side.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    public function paddingBox(): array
    {
        return $this->sides($this->padding, 80);
    }

    /**
     * {@see $border} as one value per side.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    public function borderBox(): array
    {
        return $this->sides($this->border, 0);
    }

    /**
     * {@see $backgroundPadding} as one value per side.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    public function backgroundBox(): array
    {
        return $this->sides($this->backgroundPadding, 0);
    }

    /**
     * Where the text sits: the border and padding together, as in CSS.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    public function contentBox(): array
    {
        $padding = $this->paddingBox();
        $border = $this->borderBox();

        return array_map(fn(string $side): int => $padding[$side] + $border[$side], [
            'top' => 'top', 'right' => 'right', 'bottom' => 'bottom', 'left' => 'left',
        ]);
    }

    /** The space below an element. */
    /** An element's alignment, or the logo's. */
    public function alignFor(string $element): string
    {
        $own = match ($element) {
            'label' => $this->labelAlign,
            'title' => $this->titleAlign,
            'description' => $this->descriptionAlign,
            'meta' => $this->metaAlign,
            'logo' => $this->logoAlign,
            default => null,
        };

        // Config values skip validation, so anything unknown falls back.
        return in_array($own, self::ALIGNMENTS, true) ? $own : $this->align;
    }

    public function gapAfter(string $element): int
    {
        $own = match ($element) {
            'label' => $this->labelGap,
            'title' => $this->titleGap,
            'description' => $this->descriptionGap,
            'meta' => $this->metaGap,
            default => null,
        };

        // Config values skip validation, so hold the floor here.
        return max(0, $own ?? $this->gap);
    }

    /**
     * {@see $layout} as groups of items, in order. Config values skip validation, so
     * unknown items are dropped, as are repeats of an element already placed
     * (`logoLabel` places both the logo and the label).
     *
     * @return list<list<string>>
     */
    public function layoutGroups(): array
    {
        $placed = [];
        $groups = [];
        foreach ($this->layout as $group) {
            $items = [];
            foreach (is_array($group) ? $group : [$group] as $item) {
                if (!is_string($item) || !in_array($item, self::LAYOUT_ITEMS, true)) {
                    continue;
                }

                $parts = $item === 'logoLabel' ? ['logo', 'label'] : [$item];
                if (array_intersect($parts, $placed) !== []) {
                    continue;
                }

                $placed = [...$placed, ...$parts];
                $items[] = $item;
            }

            if ($items !== []) {
                $groups[] = $items;
            }
        }

        return $groups;
    }

    public function validateLayout(string $attribute): void
    {
        $items = [];
        foreach ($this->layout as $group) {
            $group = is_array($group) ? $group : [$group];
            if ($group === []) {
                $this->addError($attribute, "Layout groups can't be empty.");

                return;
            }
            $items = [...$items, ...$group];
        }

        foreach ($items as $item) {
            if (!is_string($item) || !in_array($item, self::LAYOUT_ITEMS, true)) {
                $this->addError($attribute, 'Layout items must be one of: ' . implode(', ', self::LAYOUT_ITEMS) . '.');

                return;
            }
        }

        $parts = array_merge(...array_map(fn(string $item): array => $item === 'logoLabel' ? ['logo', 'label'] : [$item], $items));
        if (count($parts) !== count(array_unique($parts))) {
            $this->addError($attribute, 'Layout can place each element once, and `logoLabel` places both the logo and the label.');
        }
    }

    public function validateSides(string $attribute): void
    {
        $values = (array)$this->$attribute;
        $valid = $values !== [] && count($values) <= 4;
        foreach ($values as $value) {
            $valid = $valid && is_int($value) && $value >= 0;
        }

        if (!$valid) {
            $this->addError($attribute, $this->getAttributeLabel($attribute) . ' must be a non-negative number, or an array of one to four.');
        }
    }

    public function getWidth(): int
    {
        return self::WIDTH;
    }

    public function getHeight(): int
    {
        return self::HEIGHT;
    }

    protected function defineRules(): array
    {
        return [
            ['position', 'in', 'range' => self::POSITIONS],
            ['align', 'in', 'range' => self::ALIGNMENTS],
            ['textEdges', 'in', 'range' => self::TEXT_EDGES],
            ['layout', 'validateLayout'],
            [['labelAlign', 'titleAlign', 'descriptionAlign', 'metaAlign', 'logoAlign'], 'in', 'range' => self::ALIGNMENTS],
            [['titleMaxLines', 'labelMaxLines', 'descriptionMaxLines', 'metaMaxLines'], 'integer', 'min' => 1],
            [['titleLineHeight', 'labelLineHeight', 'descriptionLineHeight', 'metaLineHeight'], 'number', 'min' => 0.1],
            [['titleSize', 'labelSize', 'descriptionSize', 'metaSize', 'logoHeight'], 'integer', 'min' => 1],
            [['backgroundRadius', 'backgroundBlur', 'gap', 'labelGap', 'titleGap', 'descriptionGap', 'metaGap'], 'integer', 'min' => 0],
            ['backgroundFit', 'in', 'range' => self::BACKGROUND_FITS],
            ['format', 'in', 'range' => self::FORMATS],
            ['jpegQuality', 'integer', 'min' => 1, 'max' => 100],
            [['seoPlugins', 'renderOnSave'], 'boolean'],
            ['backgroundBlendMode', 'in', 'range' => self::BLEND_MODES],
            [['padding', 'border', 'backgroundPadding'], 'validateSides', 'skipOnEmpty' => false],
            [['backgroundOpacity', 'backgroundImagePathOpacity', 'gradientOpacity'], 'number', 'min' => 0, 'max' => 1],
            ['gradientExtent', 'number', 'min' => 0, 'max' => 1],
            ['gradientFrom', 'in', 'range' => self::GRADIENT_ORIGINS],
            [['googleFont', 'titleGoogleFont', 'labelGoogleFont', 'descriptionGoogleFont', 'metaGoogleFont'], 'match', 'pattern' => GoogleFonts::PATTERN],
            [['backgroundColor', 'labelColor', 'titleColor', 'descriptionColor', 'metaColor', 'borderColor', 'gradientColor'], 'string'],
        ];
    }

    /**
     * Each config array's keys, mapped to settings, and the key a single value sets.
     *
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    private static function groups(): array
    {
        $groups = [];
        foreach (array_keys(self::ELEMENTS) as $element) {
            $replace = ['{e}' => $element, '{E}' => ucfirst($element)];
            $groups[$element] = [array_map(fn(string $name): string => strtr($name, $replace), self::ELEMENT_KEYS), 'text'];
        }
        foreach (self::GROUP_KEYS as $group => $keys) {
            $groups[$group] = [$keys, self::GROUP_SHORTHANDS[$group]];
        }

        return $groups;
    }

    /**
     * CSS-style shorthand as one value per side. Config values skip validation,
     * so anything unreadable falls back.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    private function sides(mixed $shorthand, int $fallback): array
    {
        $values = array_values((array)$shorthand);
        if ($values === [] || count($values) > 4) {
            $values = [$fallback];
        }
        $values = array_map(fn(mixed $value): int => is_numeric($value) ? max(0, (int)$value) : $fallback, $values);

        [$top, $right, $bottom, $left] = match (count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => $values,
        };

        return ['top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left];
    }
}
