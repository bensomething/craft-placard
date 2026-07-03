<?php

namespace bensomething\ogimage\services;

use bensomething\ogimage\events\DefineCardEvent;
use bensomething\ogimage\models\Settings;
use bensomething\ogimage\Plugin;
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
        $img->newImage($w, $h, new ImagickPixel($s->backgroundColor));
        $img->setImageFormat('png');

        // Optional background image: cover-fit, grayscale, faint, behind everything.
        if ($backgroundBlob !== null && $backgroundBlob !== '') {
            $this->compositeBackground($img, $backgroundBlob);
        }

        // Logo (top-left), if configured. Its width offsets the label.
        $logoWidth = $this->compositeLogo($img, $pad, $pad);

        // Top row text, vertically centred on the logo band.
        $topBaseline = $pad + (int)round($s->logoHeight / 2) + 10;

        // Type label, to the right of the logo.
        if ($label !== null && $label !== '') {
            $labelX = $pad + ($logoWidth > 0 ? $logoWidth + 48 : 0);
            $draw = new ImagickDraw();
            $draw->setFont($labelFont);
            $draw->setFontSize($s->metaFontSize);
            $draw->setFillColor(new ImagickPixel($s->metaColor));
            $img->annotateImage($draw, $labelX, $topBaseline, 0, $label);
        }

        // Date, right-aligned.
        if ($date !== null && $date !== '') {
            $draw = new ImagickDraw();
            $draw->setFont($dateFont);
            $draw->setFontSize($s->metaFontSize);
            $draw->setFillColor(new ImagickPixel($s->metaColor));
            $draw->setTextAlignment(Imagick::ALIGN_RIGHT);
            $img->annotateImage($draw, $w - $pad, $topBaseline, 0, $date);
        }

        // Title (bottom-left): display font, fitted + wrapped.
        $title = trim($s->uppercaseTitle ? mb_strtoupper($title) : $title);
        if ($title !== '') {
            [$lines, $fontSize, $lineHeight] = $this->fitTitle($img, $displayFont, $title, $maxWidth);

            $draw = new ImagickDraw();
            $draw->setFont($displayFont);
            $draw->setFontSize($fontSize);
            $draw->setFillColor(new ImagickPixel($s->titleColor));

            // Anchor the block to the bottom padding.
            $totalHeight = count($lines) * $lineHeight;
            $firstBaseline = $h - $pad - $totalHeight + $fontSize;

            $y = $firstBaseline;
            foreach ($lines as $line) {
                $img->annotateImage($draw, $pad, $y, 0, $line);
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
    private function fitTitle(Imagick $img, string $font, string $text, float $maxWidth): array
    {
        $size = $this->settings->titleFontSize;
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
            $bg->setImageBackgroundColor(new ImagickPixel($this->settings->backgroundColor));
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
     * Composites the configured logo at ($x, $y), scaled to the settings height.
     * Returns the scaled logo width, or 0 when no logo is configured / readable.
     */
    private function compositeLogo(Imagick $img, int $x, int $y): int
    {
        $path = $this->path($this->settings->logoPath);
        if ($path === null || !is_file($path)) {
            return 0;
        }

        try {
            $logo = new Imagick();
            $logo->readImage($path);
            $logo->resizeImage(0, $this->settings->logoHeight, Imagick::FILTER_LANCZOS, 1);

            $img->compositeImage($logo, Imagick::COMPOSITE_OVER, $x, $y);
            $width = $logo->getImageWidth();
            $logo->destroy();

            return $width;
        } catch (\Throwable) {
            return 0;
        }
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
