<?php

namespace bensomething\herald\models;

use craft\base\Model;

/**
 * Plugin settings — every knob the {@see \bensomething\herald\services\Generator} and
 * controller read. Override per project in `config/herald.php` (values there win
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
    // -- Canvas ---------------------------------------------------------------

    public int $width = 1200;
    public int $height = 630;
    public int $padding = 80;

    // -- Title ----------------------------------------------------------------

    /** Titles render at this fixed size; anything past maxLines is ellipsised, not scaled. */
    public int $titleFontSize = 116;
    public int $maxLines = 3;
    public float $lineHeight = 1.02;
    public bool $uppercaseTitle = true;

    // -- Top row (label + date) ----------------------------------------------

    public int $metaFontSize = 28;
    public int $logoHeight = 48;

    // -- Palette --------------------------------------------------------------

    public string $backgroundColor = '#000000';
    public string $titleColor = '#ffffff';
    public string $metaColor = '#a3a3a3';

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

    /** Font for the date (defaults to bundled Inter Regular). */
    public string $dateFontPath = '';

    /** Logo PNG shown top-left. Leave empty to render no logo. */
    public string $logoPath = '';

    // -- Data derivation ------------------------------------------------------

    /**
     * Asset field handles checked, in order, to decide whether an entry already
     * has a share image (so the generated card can be skipped) and to pick a
     * default background photo. Customise the derived card further via
     * {@see \bensomething\herald\services\Generator::EVENT_DEFINE_CARD}.
     *
     * @var string[]
     */
    public array $imageFieldHandles = ['seoImage', 'hero', 'featuredImage'];

    /** Use the picked share image as a faint background on the generated card. */
    public bool $useImageAsBackground = false;

    /**
     * Site-relative (or absolute) URL to a static brand fallback card, returned by
     * `craft.herald.default()` when there's no entry to generate from.
     */
    public string $defaultImagePath = 'img/og-default.png';

    protected function defineRules(): array
    {
        return [
            [['width', 'height', 'padding', 'titleFontSize', 'maxLines', 'metaFontSize', 'logoHeight'], 'integer'],
            [['lineHeight', 'backgroundOpacity'], 'number'],
            [['backgroundColor', 'titleColor', 'metaColor'], 'string'],
        ];
    }
}
