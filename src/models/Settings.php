<?php

namespace bensomething\placard\models;

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
     * pushes what follows down rather than overlapping it. Sizes are defaults for
     * {@see $titleSize} and the like, and the title's max lines come from
     * {@see $titleMaxLines}.
     */
    public const ELEMENTS = [
        'label' => ['size' => 28, 'maxLines' => 1, 'uppercase' => false],
        'title' => ['size' => 116, 'maxLines' => 3, 'uppercase' => true],
        'description' => ['size' => 32, 'maxLines' => 2, 'uppercase' => false],
        'meta' => ['size' => 28, 'maxLines' => 1, 'uppercase' => false],
    ];

    /** `between` pushes the first and last elements to the padding edges. */
    public const POSITIONS = ['top', 'middle', 'bottom', 'between'];

    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * `caps` runs from the first line's cap height to the last baseline, so text sits
     * at the same height on every card. `ink` uses each card's actual letter extents.
     */
    public const TEXT_EDGES = ['caps', 'ink'];

    public const BACKGROUND_FITS = ['cover', 'contain'];

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

    /** A multiple of the font size. */
    public const LINE_HEIGHT = 1.02;

    public const LOGO_SIZE = 48;

    /** The bundled Inter weight each element falls back to. */
    public const FONTS = [
        'label' => 'Inter-Medium.ttf',
        'title' => 'Inter-Bold.ttf',
        'description' => 'Inter-Regular.ttf',
        'meta' => 'Inter-Regular.ttf',
    ];

    public string $position = 'bottom';

    public string $align = 'left';

    public string $textEdges = 'caps';

    /** A title needing fewer lines is balanced across the ones it uses. */
    public int $titleMaxLines = 3;

    public bool $titleUppercase = true;

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
     * Space below each element in px, measured between line boxes, so the visible
     * space also takes in line height and descenders. With `between` these are
     * minimums, and the leftover space is shared equally between them.
     */
    public int $gap = 24;

    /** The space below one element, over {@see $gap}. Null uses $gap. Meta is always last, so it has none. */
    public ?int $labelGap = null;
    public ?int $titleGap = null;
    public ?int $descriptionGap = null;

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

    /** As {@see $backgroundColorField}, falling back to the matching colour above. */
    public string $labelColorField = '';
    public string $titleColorField = '';
    public string $descriptionColorField = '';
    public string $metaColorField = '';

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

    /** Both fits work within this inset, with the canvas colour in the margin. */
    public int $backgroundPadding = 0;

    public int $backgroundRadius = 0;

    public bool $backgroundGrayscale = false;

    /** @deprecated in 1.0.0-beta.2. Use {@see $backgroundGrayscale}. */
    public bool $backgroundGreyscale = false;

    /** Blur strength in px, roughly CSS `blur()`. 0 is sharp. */
    public int $backgroundBlur = 0;

    public float $backgroundOpacity = 0.25;

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

    /** The fallback image is for pages with no entry, so it can't vary by type. */
    public const NOT_OVERRIDABLE = ['sections', 'entryTypes', 'defaultImagePath'];

    public function forEntry(Entry $entry): self
    {
        // Nested entries have no section, and their owner's doesn't apply to them.
        return $this->forTypes($entry->getSection()?->handle, $entry->getType()->handle);
    }

    /** A copy with the section's overrides applied, then the entry type's. */
    public function forTypes(?string $sectionHandle, ?string $entryTypeHandle): self
    {
        $settings = clone $this;

        foreach ([
            $sectionHandle !== null ? ($this->sections[$sectionHandle] ?? null) : null,
            $entryTypeHandle !== null ? ($this->entryTypes[$entryTypeHandle] ?? null) : null,
        ] as $overrides) {
            if (!is_array($overrides)) {
                continue;
            }

            foreach ($overrides as $name => $value) {
                if (is_string($name) && property_exists($settings, $name) && !in_array($name, self::NOT_OVERRIDABLE, true)) {
                    $settings->$name = $value;
                }
            }
        }

        return $settings;
    }

    /** The configured font for an element, or empty for the bundled one. */
    public function fontPathFor(string $element): string
    {
        $own = match ($element) {
            'title' => $this->titleFontPath ?: $this->displayFontPath,
            'label' => $this->labelFontPath,
            'description' => $this->descriptionFontPath,
            'meta' => $this->metaFontPath,
            default => '',
        };

        return $own ?: $this->fontPath;
    }

    /**
     * {@see $padding} as one value per side.
     *
     * @return array{top: int, right: int, bottom: int, left: int}
     */
    public function paddingBox(): array
    {
        $values = array_values((array)$this->padding);
        // Config values skip validation, so anything unreadable falls back to the default.
        if ($values === [] || count($values) > 4) {
            $values = [80];
        }
        $values = array_map(fn(mixed $value): int => is_numeric($value) ? max(0, (int)$value) : 80, $values);

        [$top, $right, $bottom, $left] = match (count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => $values,
        };

        return ['top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left];
    }

    /** The space below an element. */
    public function gapAfter(string $element): int
    {
        $own = match ($element) {
            'label' => $this->labelGap,
            'title' => $this->titleGap,
            'description' => $this->descriptionGap,
            default => null,
        };

        // Config values skip validation, so hold the floor here.
        return max(0, $own ?? $this->gap);
    }

    public function validatePadding(string $attribute): void
    {
        $values = (array)$this->$attribute;
        $valid = $values !== [] && count($values) <= 4;
        foreach ($values as $value) {
            $valid = $valid && is_int($value) && $value >= 0;
        }

        if (!$valid) {
            $this->addError($attribute, 'Padding must be a non-negative number, or an array of one to four.');
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
            ['titleMaxLines', 'integer', 'min' => 1],
            [['titleSize', 'labelSize', 'descriptionSize', 'metaSize', 'logoHeight'], 'integer', 'min' => 1],
            [['backgroundPadding', 'backgroundRadius', 'backgroundBlur', 'gap', 'labelGap', 'titleGap', 'descriptionGap'], 'integer', 'min' => 0],
            ['backgroundFit', 'in', 'range' => self::BACKGROUND_FITS],
            ['backgroundBlendMode', 'in', 'range' => self::BLEND_MODES],
            ['padding', 'validatePadding', 'skipOnEmpty' => false],
            [['backgroundOpacity'], 'number', 'min' => 0, 'max' => 1],
            [['backgroundColor', 'labelColor', 'titleColor', 'descriptionColor', 'metaColor'], 'string'],
        ];
    }
}
