<?php

namespace bensomething\herald\services;

use bensomething\herald\events\DefineCardEvent;
use bensomething\herald\models\Settings;
use bensomething\herald\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\Entry;
use craft\helpers\App;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use yii\base\Component;

/**
 * Renders social / OG cards with Imagick, driven entirely by {@see Settings}.
 *
 * Black (configurable) background, title in the display font (optionally
 * uppercased, fixed size, shrink-to-fit-lines + word-wrapped), label + date in the
 * body fonts, optional logo top-left and optional faint background photo.
 */
class Generator extends Component
{
    /**
     * @event DefineCardEvent Fired to derive a card's title / label / date /
     * background from an entry. Handlers override the plugin's defaults.
     */
    public const EVENT_DEFINE_CARD = 'defineCard';

    private Settings $settings;

    public function init(): void
    {
        parent::init();
        $this->settings = Plugin::getInstance()->getSettings();
    }

    /**
     * Renders with ad-hoc settings instead of the saved ones — used by the CP
     * settings preview to reflect unsaved edits.
     */
    public function setSettings(Settings $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Builds a card spec for an entry: fills defaults (title, entry-type label,
     * first share image) then fires {@see self::EVENT_DEFINE_CARD} so the project
     * can customise it. Returns the resolved event.
     */
    public function defineCard(Entry $entry): DefineCardEvent
    {
        $event = new DefineCardEvent([
            'entry' => $entry,
            'title' => (string)$entry->title,
            'label' => (string)($entry->type->name ?? ''),
            'background' => $this->settings->useImageAsBackground ? $this->firstImage($entry) : null,
        ]);

        $this->trigger(self::EVENT_DEFINE_CARD, $event);

        return $event;
    }

    /**
     * Returns the rendered card as PNG binary.
     */
    public function generate(string $title, ?string $label = null, ?string $backgroundBlob = null, ?string $date = null): string
    {
        $s = $this->settings;
        $w = $s->width;
        $h = $s->height;
        $pad = $s->padding;
        $maxWidth = $w - ($pad * 2);

        $displayFont = $this->displayFont();
        $labelFont = $this->font($s->labelFontPath, 'Inter-Medium.ttf');
        $dateFont = $this->font($s->dateFontPath, 'Inter-Regular.ttf');

        $img = new Imagick();
        $img->newImage($w, $h, $this->color($s->backgroundColor));
        $img->setImageFormat('png');

        // Optional background image: cover-fit, grayscale, faint, behind everything.
        if ($backgroundBlob !== null && $backgroundBlob !== '') {
            $this->compositeBackground($img, $backgroundBlob);
        }

        // Logo has its own size + position; the text elements read their rows from
        // the settings table (size, position, colour, uppercase).
        $logo = $this->compositeLogo($img, $s->logoPosition, $s->logoSize);

        $labelStyle = $this->elementStyle('label');
        $this->drawText(
            $img,
            $labelFont,
            $this->prepText($label, $labelStyle['uppercase']),
            $labelStyle,
            $this->logoInset($labelStyle['position'], $logo),
        );

        $dateStyle = $this->elementStyle('date');
        $this->drawText(
            $img,
            $dateFont,
            $this->prepText($date, $dateStyle['uppercase']),
            $dateStyle,
            $this->logoInset($dateStyle['position'], $logo),
        );

        // Title: display font, multi-line fitted + wrapped, anchored per its row.
        $titleStyle = $this->elementStyle('title');
        $title = $this->prepText($title, $titleStyle['uppercase']);
        if ($title !== '' && !$titleStyle['hide']) {
            [$lines, $fontSize, $lineHeight] = $this->fitTitle($img, $displayFont, $title, $maxWidth, $titleStyle['size']);
            $totalHeight = count($lines) * $lineHeight;
            [$vAlign, $hAlign] = $this->anchor($titleStyle['position']);

            $draw = new ImagickDraw();
            $draw->setFont($displayFont);
            $draw->setFontSize($fontSize);
            $draw->setFillColor($this->color($titleStyle['color'] ?: '#ffffff'));

            $firstBaseline = match ($vAlign) {
                'top' => $pad + $fontSize,
                'middle' => (int)round(($h - $totalHeight) / 2) + $fontSize,
                default => $h - $pad - $totalHeight + $fontSize,
            };
            [$titleX, $align] = $this->horizontal($hAlign);
            $draw->setTextAlignment($align);

            $y = $firstBaseline;
            foreach ($lines as $line) {
                $img->annotateImage($draw, $titleX, $y, 0, $line);
                $y += $lineHeight;
            }
        }

        $out = $img->getImageBlob();
        $img->destroy();

        return $out;
    }

    // -- Data helpers ---------------------------------------------------------

    /**
     * First asset found among the configured image field handles, or null.
     */
    private function firstImage(Entry $entry): ?Asset
    {
        foreach ($this->settings->imageFieldHandles as $handle) {
            try {
                $value = $entry->getFieldValue($handle);
            } catch (\Throwable) {
                continue;
            }

            $asset = $value instanceof AssetQuery ? $value->one() : $value;
            if ($asset instanceof Asset) {
                return $asset;
            }
        }

        return null;
    }

    // -- Title fitting (shrink-to-lines + balance / clamp) --------------------

    /**
     * Wraps the title at a fixed large size. Lines that fit within maxLines are
     * balanced to even lengths (à la `text-wrap: balance`); anything longer is
     * clamped to maxLines with a trailing ellipsis rather than scaled down.
     *
     * Returns [lines, fontSize, lineHeight].
     */
    private function fitTitle(Imagick $img, string $font, string $text, float $maxWidth, int $size): array
    {
        $maxLines = $this->settings->maxLines;
        $draw = $this->measureDraw($font, $size);

        $lines = $this->greedyWrap($img, $draw, $text, $maxWidth);

        if (count($lines) <= $maxLines) {
            $lines = $this->balanceLines($img, $draw, $text, $maxWidth, count($lines));
        } else {
            $lines = $this->clampLines($img, $draw, $text, $maxWidth, $maxLines);
        }

        // Safety net for a single word wider than the canvas: trim to characters.
        $lines = array_map(
            fn(string $line): string => $this->truncateToWidth($img, $draw, $line, $maxWidth),
            $lines,
        );

        return [$lines, $size, $size * $this->settings->lineHeight];
    }

    /**
     * Hard-trims a single line to characters + ellipsis if it still overflows
     * (e.g. one unbreakable word longer than the canvas).
     */
    private function truncateToWidth(Imagick $img, ImagickDraw $draw, string $line, float $maxWidth): string
    {
        if ($img->queryFontMetrics($draw, $line)['textWidth'] <= $maxWidth) {
            return $line;
        }

        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        while ($chars && $img->queryFontMetrics($draw, implode('', $chars) . '…')['textWidth'] > $maxWidth) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)) . '…';
    }

    /**
     * Balances $text across exactly $targetLines lines by finding the narrowest
     * width that still wraps to that many lines — which evens out their lengths.
     */
    private function balanceLines(Imagick $img, ImagickDraw $draw, string $text, float $maxWidth, int $targetLines): array
    {
        if ($targetLines <= 1) {
            return [trim($text)];
        }

        $best = $this->greedyWrap($img, $draw, $text, $maxWidth);
        $lo = 1;
        $hi = (int)ceil($maxWidth);

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $wrapped = $this->greedyWrap($img, $draw, $text, $mid);

            if (count($wrapped) <= $targetLines) {
                $best = $wrapped;
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        return $best;
    }

    /**
     * Greedy word-wrap against a pixel width limit.
     */
    private function greedyWrap(Imagick $img, ImagickDraw $draw, string $text, float $widthLimit): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            $width = $img->queryFontMetrics($draw, $candidate)['textWidth'];

            if ($width > $widthLimit && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Clamps to $maxLines lines, ending the last line with an ellipsis and
     * dropping trailing words until it fits.
     */
    private function clampLines(Imagick $img, ImagickDraw $draw, string $text, float $maxWidth, int $maxLines): array
    {
        $lines = $this->greedyWrap($img, $draw, $text, $maxWidth);

        if (count($lines) <= $maxLines) {
            return $lines;
        }

        $kept = array_slice($lines, 0, $maxLines);
        $words = preg_split('/\s+/', $kept[$maxLines - 1]) ?: [];

        while (count($words) > 1
            && $img->queryFontMetrics($draw, implode(' ', $words) . '…')['textWidth'] > $maxWidth) {
            array_pop($words);
        }

        $kept[$maxLines - 1] = implode(' ', $words) . '…';

        return $kept;
    }

    // -- Image helpers --------------------------------------------------------

    /**
     * Composites a cover-fitted, grayscale, faint background over the base colour.
     * Any failure is swallowed so the card still renders.
     */
    private function compositeBackground(Imagick $img, string $blob): void
    {
        try {
            $bg = new Imagick();
            $bg->readImageBlob($blob);

            // Scale + centre-crop to exactly fill the canvas.
            $bg->setImageBackgroundColor($this->color($this->settings->backgroundColor));
            $bg->cropThumbnailImage($this->settings->width, $this->settings->height);

            // Desaturate, soften slightly, then drop to the configured opacity.
            $bg->modulateImage(100, 0, 100);
            $bg->gaussianBlurImage(0, 10);
            $bg->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
            $bg->evaluateImage(Imagick::EVALUATE_MULTIPLY, $this->settings->backgroundOpacity, Imagick::CHANNEL_ALPHA);

            $img->compositeImage($bg, Imagick::COMPOSITE_OVER, 0, 0);
            $bg->destroy();
        } catch (\Throwable) {
            // Render without a background.
        }
    }

    /**
     * Composites the configured logo, scaled to $height, at the given anchor
     * position (one of the 9 anchors). Returns `['width','height','vAlign','hAlign']`
     * so the meta text can dodge it, or null when no logo is configured / readable.
     *
     * @return array{width:int,height:int,vAlign:string,hAlign:string}|null
     */
    private function compositeLogo(Imagick $img, string $position, int $height): ?array
    {
        $path = $this->path($this->settings->logoPath);
        if ($path === null || !is_file($path) || $height <= 0) {
            return null;
        }

        [$vAlign, $hAlign] = $this->anchor($position);
        $w = $this->settings->width;
        $h = $this->settings->height;
        $pad = $this->settings->padding;

        try {
            $logo = new Imagick();
            $logo->readImage($path);
            $logo->resizeImage(0, $height, Imagick::FILTER_LANCZOS, 1);

            $lw = $logo->getImageWidth();
            $lh = $logo->getImageHeight();
            $x = match ($hAlign) {
                'center' => (int)round(($w - $lw) / 2),
                'right' => $w - $pad - $lw,
                default => $pad,
            };
            $y = match ($vAlign) {
                'middle' => (int)round(($h - $lh) / 2),
                'bottom' => $h - $pad - $lh,
                default => $pad,
            };

            $img->compositeImage($logo, Imagick::COMPOSITE_OVER, $x, $y);
            $logo->destroy();

            return ['width' => $lw, 'height' => $lh, 'vAlign' => $vAlign, 'hAlign' => $hAlign];
        } catch (\Throwable) {
            return null;
        }
    }

    // -- Element placement ----------------------------------------------------

    /**
     * The resolved style row for an element, keyed by its position in
     * {@see Settings::ELEMENTS}. Falls back to sane values for missing cells.
     *
     * @return array{size:int, position:string, color:string, uppercase:bool}
     */
    private function elementStyle(string $key): array
    {
        $index = array_search($key, array_keys(Settings::ELEMENTS), true);
        $row = array_values($this->settings->cardElements)[$index] ?? [];

        return [
            'size' => (int)($row['size'] ?? 0),
            'position' => (string)($row['position'] ?? 'top-left'),
            'color' => (string)($row['color'] ?? ''),
            'uppercase' => !empty($row['uppercase']) && !in_array($row['uppercase'], ['0', 0, false], true),
            'hide' => !empty($row['hide']) && !in_array($row['hide'], ['0', 0, false], true),
        ];
    }

    /** Splits a position anchor into [vertical, horizontal] with safe fallbacks. */
    private function anchor(string $position): array
    {
        [$v, $h] = array_pad(explode('-', $position, 2), 2, '');

        return [
            in_array($v, ['top', 'middle', 'bottom'], true) ? $v : 'top',
            in_array($h, ['left', 'center', 'right'], true) ? $h : 'left',
        ];
    }

    /** Maps a horizontal align to [x, Imagick text alignment]. */
    private function horizontal(string $hAlign): array
    {
        $w = $this->settings->width;
        $pad = $this->settings->padding;

        return match ($hAlign) {
            'center' => [(int)round($w / 2), Imagick::ALIGN_CENTER],
            'right' => [$w - $pad, Imagick::ALIGN_RIGHT],
            default => [$pad, Imagick::ALIGN_LEFT],
        };
    }

    /** Uppercases + trims a nullable text value per the element's setting. */
    private function prepText(?string $text, bool $uppercase): string
    {
        $text = (string)$text;

        return trim($uppercase ? mb_strtoupper($text) : $text);
    }

    /**
     * Horizontal inset to keep a single-line element clear of the logo when they
     * share a left or right corner (the classic logo + kicker row). 0 otherwise.
     */
    private function logoInset(string $position, ?array $logo): int
    {
        if ($logo === null) {
            return 0;
        }

        [$v, $h] = $this->anchor($position);
        if ($v !== $logo['vAlign'] || $h !== $logo['hAlign'] || $h === 'center') {
            return 0;
        }

        return $logo['width'] + 48;
    }

    /** Draws a single line of text at its anchor, inset past the logo if needed. */
    private function drawText(Imagick $img, string $font, string $text, array $style, int $inset = 0): void
    {
        if ($text === '' || !empty($style['hide'])) {
            return;
        }

        $h = $this->settings->height;
        $pad = $this->settings->padding;
        $size = $style['size'];
        [$vAlign, $hAlign] = $this->anchor($style['position']);
        [$x, $align] = $this->horizontal($hAlign);

        // Push away from the logo (inward) on whichever side we're anchored to.
        $x += $hAlign === 'right' ? -$inset : $inset;

        $baseline = match ($vAlign) {
            'top' => $pad + $size,
            'middle' => (int)round(($h + $size) / 2),
            default => $h - $pad,
        };

        $draw = new ImagickDraw();
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setFillColor($this->color($style['color'] ?: '#ffffff'));
        $draw->setTextAlignment($align);
        $img->annotateImage($draw, $x, $baseline, 0, $text);
    }

    /**
     * Builds an ImagickPixel from a configured colour, tolerating bare hex — the CP
     * colour fields submit `000000`, not `#000000`, which Imagick can't parse. Named
     * colours and `#rgb`/`#rrggbb` pass through unchanged; empties fall back to black.
     */
    private function color(string $value): ImagickPixel
    {
        $value = trim($value);

        if ($value === '') {
            $value = '#000000';
        } elseif (preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            $value = "#$value";
        }

        return new ImagickPixel($value);
    }

    private function measureDraw(string $font, float $size): ImagickDraw
    {
        $draw = new ImagickDraw();
        $draw->setFont($font);
        $draw->setFontSize($size);

        return $draw;
    }

    // -- Path resolution ------------------------------------------------------

    /**
     * Display font: the configured path, or the bundled Inter Bold fallback (no
     * commercial display font ships with the plugin).
     */
    private function displayFont(): string
    {
        return $this->font($this->settings->displayFontPath, 'Inter-Bold.ttf');
    }

    /**
     * Resolves a configured font path, falling back to a bundled resource font.
     */
    private function font(string $configured, string $fallback): string
    {
        return $this->path($configured) ?? $this->resource("fonts/$fallback");
    }

    /**
     * Parses env vars + Craft aliases in a configured path. Returns null when empty.
     */
    private function path(string $configured): ?string
    {
        $configured = trim($configured);
        if ($configured === '') {
            return null;
        }

        return Craft::getAlias(App::parseEnv($configured));
    }

    private function resource(string $relative): string
    {
        return dirname(__DIR__) . '/resources/' . $relative;
    }
}
