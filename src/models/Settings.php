<?php

namespace bensomething\placard\models;

use craft\base\Model;
use craft\elements\Entry;

/**
 * Plugin settings — the brand and data knobs the {@see \bensomething\placard\services\Generator}
 * and controller read. There's no settings screen: set them per project in
 * `config/placard.php`, e.g.:
 *
 *   return [
 *       'displayFontPath' => '@root/src/fonts/MyDisplay.otf',
 *       'logoPath' => '@webroot/img/logo.png',
 *       'titleColor' => '#ffffff',
 *       'imageFieldHandles' => ['seoImage', 'hero'],
 *   ];
 *
 * Font / logo / image paths may use Craft aliases (@root, @webroot, …) and env vars.
 */
class Settings extends Model
{
    // -- Layout ---------------------------------------------------------------

    /**
     * The canvas is fixed at 1200×630. That's 1.91:1 — the shape every platform
     * crops an og:image from — and it's the size they all recommend, sized for the
     * ~600px-wide box link previews actually render in. Anything larger is bytes
     * nobody sees, so it isn't a setting.
     */
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /**
     * Text elements, in stack order, with their fixed style. They flow in one stack,
     * placed by {@see $position} and {@see $align}, which is what keeps the layout
     * collision-free: a title that wraps to an extra line pushes what follows it
     * down instead of overlapping it. `maxLines` clamps with a trailing ellipsis;
     * the title's size and max lines are overridden by {@see $titleSize} and
     * {@see $titleMaxLines}.
     */
    public const ELEMENTS = [
        'label' => ['size' => 28, 'maxLines' => 1, 'uppercase' => false],
        'title' => ['size' => 116, 'maxLines' => 3, 'uppercase' => true],
        'description' => ['size' => 32, 'maxLines' => 2, 'uppercase' => false],
        'meta' => ['size' => 28, 'maxLines' => 1, 'uppercase' => false],
    ];

    /**
     * Where the stack sits vertically. `between` pushes the first and last elements
     * to the padding edges and shares the space out evenly between the rest.
     */
    public const POSITIONS = ['top', 'middle', 'bottom', 'between'];

    /** How the stack aligns horizontally. */
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * What the stack's top and bottom edges are, for {@see $position}: `caps` is
     * the first line's cap height to the last line's baseline, so tall letters and
     * descenders hang outside and the text sits at the same height on every card;
     * `ink` is exactly what the card's letters reach, so each card is centred (or
     * placed) on its own ink.
     */
    public const TEXT_EDGES = ['caps', 'ink'];

    /**
     * How the background image fills the canvas: `cover` crops it to fill, `contain`
     * fits it whole, centred, with the background colour around it.
     */
    public const BACKGROUND_FITS = ['cover', 'contain'];

    /** Inset the stack is anchored to, on every side. */
    public const PADDING = 80;

    /** Gap between stacked elements, and between the logo and the label. */
    public const ELEMENT_GAP = 24;

    /** Line height for the wrapping elements, as a multiple of their size. */
    public const LINE_HEIGHT = 1.02;

    /** Logo height. The logo sits on the label's row. */
    public const LOGO_SIZE = 48;

    /** Where the stack sits vertically — one of {@see POSITIONS}. */
    public string $position = 'bottom';

    /** How the stack aligns horizontally — one of {@see ALIGNMENTS}. */
    public string $align = 'left';

    /** What the stack is positioned by — one of {@see TEXT_EDGES}. */
    public string $textEdges = 'caps';

    /**
     * Most lines the title wraps to before it's clamped with an ellipsis. A title
     * that needs fewer is balanced across the lines it does use.
     */
    public int $titleMaxLines = 3;

    /** Set the title in capitals. Off draws it as written. */
    public bool $titleUppercase = true;

    /**
     * Title font size, in pixels on the 1200×630 canvas. Long titles wrap at this
     * size and are clamped by {@see $titleMaxLines}; they don't shrink to fit.
     */
    public int $titleSize = 116;

    /**
     * Whether each element is drawn. A hidden element is skipped and the stack
     * closes up, as it does when an element has no text. Hiding the label keeps the
     * logo, which then sits on its own; hide that with {@see $showLogo}.
     */
    public bool $showLabel = true;
    public bool $showTitle = true;
    public bool $showDescription = true;
    public bool $showMeta = true;
    public bool $showLogo = true;

    // -- Text -----------------------------------------------------------------

    /*
     * What each element says, as an object template rendered against the entry —
     * the same syntax as an entry type's title format: `{summary}`, fixed text,
     * `{postDate|date("j F Y")}`, `{summary ?: seoDescription}`. HTML is stripped,
     * so a rich text field is fine. Empty means the element has no text and is
     * skipped. EVENT_DEFINE_CARD handlers see the result and can still change it.
     */

    public string $title = '{title}';
    public string $label = '{type.name}';
    public string $description = '';
    public string $meta = '';

    // -- Palette --------------------------------------------------------------

    /** Canvas colour, and the fallback when {@see $backgroundColorField} gives none. */
    public string $backgroundColor = '#000000';

    /**
     * Handle of a field on the entry to take the canvas colour from — a Color field,
     * or a plain text field holding a hex value. Empty, missing or invalid values
     * fall back to {@see $backgroundColor}.
     */
    public string $backgroundColorField = '';

    public string $labelColor = '#a3a3a3';
    public string $titleColor = '#ffffff';
    public string $descriptionColor = '#a3a3a3';
    public string $metaColor = '#a3a3a3';

    // -- Assets ---------------------------------------------------------------

    /**
     * Display font for the title. Leave empty to use the bundled Inter Bold
     * fallback. Point this at your own (licensed) display font per project — no
     * commercial font is bundled with the plugin.
     */
    public string $displayFontPath = '';

    /** Font for the label kicker (defaults to bundled Inter Medium). */
    public string $labelFontPath = '';

    /** Font for the description (defaults to bundled Inter Regular). */
    public string $descriptionFontPath = '';

    /** Font for the meta line (defaults to bundled Inter Regular). */
    public string $metaFontPath = '';

    /** Logo PNG shown beside the label. Leave empty to render no logo. */
    public string $logoPath = '';

    // -- Background -----------------------------------------------------------

    /**
     * Handle of an asset field on the entry to take the background image from — its
     * first asset. Takes precedence over {@see $useImageAsBackground}; when the field
     * is empty or missing, the card falls through to that, then to
     * {@see $backgroundImagePath}.
     */
    public string $backgroundImageField = '';

    /**
     * Use the entry's share image (the first found in {@see $imageFieldHandles}) as
     * the background, when {@see $backgroundImageField} gives none.
     */
    public bool $useImageAsBackground = false;

    /**
     * A fixed background image, used when the entry gives none — or for every card,
     * if neither of the settings above is set. Absolute path or Craft alias; leave
     * empty for none.
     */
    public string $backgroundImagePath = '';

    /** How the image fills the canvas — one of {@see BACKGROUND_FITS}. */
    public string $backgroundFit = 'cover';

    /**
     * Inset around the background image, in pixels. Both fits work within the
     * inset box rather than the whole canvas: `cover` fills it, `contain` fits
     * inside it. The canvas colour shows in the margin.
     */
    public int $backgroundPadding = 0;

    /** Corner radius for the background image, in pixels. */
    public int $backgroundRadius = 0;

    /** Drain the colour out of the backdrop before it's faded back. */
    public bool $backgroundGreyscale = false;

    /** Backdrop opacity (0..1) once it's behind the card. */
    public float $backgroundOpacity = 0.25;

    // -- Data derivation ------------------------------------------------------

    /**
     * Asset field handles checked, in order, to decide whether an entry already
     * has a share image (so the generated card can be skipped) and to pick a
     * default background photo. Customise the derived card further via
     * {@see \bensomething\placard\services\Generator::EVENT_DEFINE_CARD}.
     *
     * @var string[]
     */
    public array $imageFieldHandles = ['seoImage', 'hero', 'featuredImage'];

    /**
     * Site-relative (or absolute) URL to a static brand fallback card, returned by
     * `craft.placard.default()` when there's no entry to generate from.
     */
    public string $defaultImagePath = 'img/og-default.png';

    // -- Per-type overrides ---------------------------------------------------

    /**
     * Settings to change for entries in a section, keyed by section handle, e.g.
     * `['artists' => ['position' => 'middle']]`. Applied over the base settings.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $sections = [];

    /**
     * Settings to change for entries of an entry type, keyed by entry type handle.
     * Applied over the base and section settings, so the entry type wins.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $entryTypes = [];

    /**
     * Settings that can't be overridden per section or entry type: the override
     * lists themselves, and the fallback image, which is for pages with no entry.
     */
    public const NOT_OVERRIDABLE = ['sections', 'entryTypes', 'defaultImagePath'];

    /** The settings an entry's card is rendered with — see {@see forTypes()}. */
    public function forEntry(Entry $entry): self
    {
        // Nested entries have no section; their owner's doesn't apply to them.
        return $this->forTypes($entry->getSection()?->handle, $entry->getType()->handle);
    }

    /**
     * A copy of these settings with the section's overrides applied, then the entry
     * type's. Unknown handles and settings are ignored, as are those in
     * {@see NOT_OVERRIDABLE}.
     */
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

    /** Output width in pixels. Fixed — see {@see WIDTH}. */
    public function getWidth(): int
    {
        return self::WIDTH;
    }

    /** Output height in pixels. Fixed — see {@see HEIGHT}. */
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
            ['titleSize', 'integer', 'min' => 1],
            [['backgroundPadding', 'backgroundRadius'], 'integer', 'min' => 0],
            ['backgroundFit', 'in', 'range' => self::BACKGROUND_FITS],
            [['backgroundOpacity'], 'number', 'min' => 0, 'max' => 1],
            [['backgroundColor', 'labelColor', 'titleColor', 'descriptionColor', 'metaColor'], 'string'],
        ];
    }
}
