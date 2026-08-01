<?php

namespace bensomething\placard\models;

use craft\base\Model;

/**
 * Plugin settings — every knob the {@see \bensomething\placard\services\Generator} and
 * controller read. Override per project in `config/placard.php` (values there win
 * over the CP settings), e.g.:
 *
 *   return [
 *       'displayFontPath' => '@root/src/fonts/MyDisplay.otf',
 *       'logoPath' => '@webroot/img/logo.png',
 *       'titleColor' => '#ffffff',
 *       'imageFieldHandles' => ['seoImage', 'hero'],
 *   ];
 *
 * Font / logo / config paths may use Craft aliases (@root, @webroot, …) and env vars.
 */
class Settings extends Model
{
    /**
     * Text elements, in the fixed order the {@see cardElements} rows describe them.
     * The keys are machine names used by the generator; the values are the labels
     * shown in the CP settings table. Order here === row order there === the index
     * the generator reads, so the non-submitted heading column can't drift. (The
     * logo isn't a text element — it has its own size / position settings.)
     */
    public const ELEMENTS = [
        'label' => 'Label',
        'title' => 'Title',
        'description' => 'Description',
        'date' => 'Date',
    ];

    /** Display treatment offered per element. `hidden` drops the element entirely. */
    public const DISPLAYS = [
        'normal' => 'Normal',
        'uppercase' => 'Uppercase',
        'hidden' => 'Hidden',
    ];

    /** Position options offered per element (9 anchors). */
    public const POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'middle-left', 'middle-center', 'middle-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];

    // -- Canvas ---------------------------------------------------------------

    public int $width = 1200;
    public int $height = 630;
    public int $padding = 80;

    /** Line height for the wrapping elements. Max lines is set per element. */
    public float $lineHeight = 1.02;

    // -- Elements -------------------------------------------------------------

    /**
     * Elements that wrap over several lines, and so honour their `maxLines`. The
     * rest are drawn as a single line, and ignore it.
     */
    public const WRAPPING_ELEMENTS = ['title', 'description'];

    /**
     * Defaults for {@see $cardElements}, one row per entry in {@see ELEMENTS}, in
     * that order. Exposed as a constant so the settings screen can fall back to it
     * per cell when a stored row predates a key being added.
     */
    public const DEFAULT_CARD_ELEMENTS = [
        ['size' => 28, 'position' => 'top-left', 'color' => '#a3a3a3', 'display' => 'normal', 'maxLines' => 1],
        ['size' => 116, 'position' => 'bottom-left', 'color' => '#ffffff', 'display' => 'uppercase', 'maxLines' => 3],
        ['size' => 32, 'position' => 'middle-left', 'color' => '#a3a3a3', 'display' => 'hidden', 'maxLines' => 2],
        ['size' => 28, 'position' => 'top-right', 'color' => '#a3a3a3', 'display' => 'normal', 'maxLines' => 1],
    ];

    /**
     * Per-element size / position / colour / display, one row per entry in
     * {@see ELEMENTS}, in that order. `size` is the font size (px); `position` is
     * one of {@see POSITIONS}; `color` is a hex; `display` is one of
     * {@see DISPLAYS} and covers both text treatment and hiding the element;
     * `maxLines` clamps the wrapping elements (see {@see WRAPPING_ELEMENTS}).
     *
     * @var array<int, array{size:int, position:string, color:string, display:string, maxLines:int}>
     */
    public array $cardElements = self::DEFAULT_CARD_ELEMENTS;

    // -- Logo -----------------------------------------------------------------

    /** Logo height in pixels. */
    public int $logoSize = 48;

    /** Logo anchor — one of {@see POSITIONS}. */
    public string $logoPosition = 'top-left';

    // -- Palette --------------------------------------------------------------

    public string $backgroundColor = '#000000';

    /** Background photo opacity (0..1) once desaturated and blurred behind the card. */
    public float $backgroundOpacity = 0.25;

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

    /** Font for the date (defaults to bundled Inter Regular). */
    public string $dateFontPath = '';

    /** Logo PNG shown top-left. Leave empty to render no logo. */
    public string $logoPath = '';

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

    /** Use the picked share image as a faint background on the generated card. */
    public bool $useImageAsBackground = false;

    /**
     * Site-relative (or absolute) URL to a static brand fallback card, returned by
     * `craft.placard.default()` when there's no entry to generate from.
     */
    public string $defaultImagePath = 'img/og-default.png';

    protected function defineRules(): array
    {
        return [
            [['width', 'height', 'padding', 'logoSize'], 'integer'],
            [['lineHeight', 'backgroundOpacity'], 'number'],
            ['backgroundColor', 'string'],
            ['logoPosition', 'in', 'range' => self::POSITIONS],
            ['cardElements', 'normalizeCardElements'],
        ];
    }

    /**
     * Coerces {@see cardElements} to exactly one row per {@see ELEMENTS}, in order,
     * with typed keys. The CP table's heading column isn't submitted and rows arrive
     * keyed by row id, so we rebuild positionally against the defaults — the stored
     * shape can never drift out of step with the element list.
     */
    public function normalizeCardElements(): void
    {
        $raw = is_array($this->cardElements) ? $this->cardElements : [];
        // Numeric keys are row indices (map an element straight to its index, so a
        // sparse or re-ordered POST still lands correctly); otherwise fall back to
        // insertion (DOM submit) order.
        $numericKeys = $raw !== [] && array_keys($raw) === array_filter(array_keys($raw), 'is_numeric');
        $rows = $numericKeys ? $raw : array_values($raw);
        $defaults = self::DEFAULT_CARD_ELEMENTS;
        $normalized = [];

        foreach (array_values(self::ELEMENTS) as $i => $_label) {
            $default = $defaults[$i] ?? ['size' => 0, 'position' => 'top-left', 'color' => '', 'display' => 'normal', 'maxLines' => 1];
            $row = $rows[$i] ?? [];

            $position = (string)($row['position'] ?? $default['position']);
            $size = (int)($row['size'] ?? $default['size']);
            $display = (string)($row['display'] ?? $default['display']);
            // The cell is disabled for non-wrapping elements, so it submits nothing —
            // keep the default rather than reading a missing key as 0.
            $maxLines = (int)($row['maxLines'] ?? $default['maxLines']);

            $normalized[] = [
                'size' => $size > 0 ? $size : $default['size'],
                'position' => in_array($position, self::POSITIONS, true) ? $position : $default['position'],
                'color' => (string)($row['color'] ?? $default['color']),
                'display' => isset(self::DISPLAYS[$display]) ? $display : $default['display'],
                'maxLines' => $maxLines > 0 ? $maxLines : $default['maxLines'],
            ];
        }

        $this->cardElements = $normalized;
    }
}
