<?php

namespace bensomething\placard\services;

use bensomething\placard\events\DefineCardEvent;
use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\Settings;
use bensomething\placard\models\SocialImageValue;
use bensomething\placard\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\fields\data\ColorData;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use yii\base\Component;

/**
 * Renders social cards with Imagick: label, title, description and meta in one
 * stack, with an optional logo beside the label and a faint background image.
 */
class Generator extends Component
{
    /**
     * @event DefineCardEvent Lets projects override a card's text and background for an entry.
     */
    public const EVENT_DEFINE_CARD = 'defineCard';

    private Settings $settings;

    public function init(): void
    {
        parent::init();
        $this->settings = Plugin::getInstance()->getSettings();
    }

    /**
     * Resolves an entry's card and fires EVENT_DEFINE_CARD. Pass the result's
     * `settings` to generate().
     */
    public function defineCard(Entry $entry, ?string $style = null): DefineCardEvent
    {
        $settings = $this->settings->forEntry($entry, $style);
        $resolved = $this->withSettings($settings);

        // Entry colours go into this card's copy of the settings, which is what draws it.
        foreach (['label', 'title', 'description', 'meta', 'border'] as $element) {
            $color = $resolved->entryColor($entry, $settings->{$element . 'ColorField'});
            if ($color !== null) {
                $settings->{$element . 'Color'} = $color;
            }
        }

        $event = new DefineCardEvent([
            'entry' => $entry,
            'settings' => $settings,
            'title' => (string)$resolved->renderText($settings->title, $entry, 'title'),
            'label' => (string)$resolved->renderText($settings->label, $entry, 'label'),
            'description' => $resolved->renderText($settings->description, $entry, 'description'),
            'meta' => $resolved->renderText($settings->meta, $entry, 'meta'),
            'background' => $resolved->backgroundImage($entry),
            'backgroundColor' => $resolved->entryColor($entry, $settings->backgroundColorField),
        ]);

        $this->trigger(self::EVENT_DEFINE_CARD, $event);

        return $event;
    }

    /**
     * Absolute URL to an entry's card. Cards are served immutable, so `v` changes
     * with the entry and its settings.
     */
    public function cardUrl(Entry $entry): string
    {
        return UrlHelper::siteUrl('og/' . $entry->id . '.png', ['v' => $this->cardToken($entry)]);
    }

    /**
     * Card URL for CP previews, which also serves drafts and disabled entries to
     * users who can view them. cardUrl() only serves live entries.
     */
    public function previewUrl(Entry $entry): string
    {
        return UrlHelper::actionUrl('placard/image/preview', [
            'entryId' => $entry->id,
            'siteId' => $entry->siteId,
            'v' => $this->cardToken($entry),
        ]);
    }

    private function cardToken(Entry $entry): string
    {
        $settings = $this->settings->forEntry($entry);

        return substr(md5(($entry->dateUpdated?->getTimestamp() ?? 0) . serialize($settings->getAttributes())), 0, 10);
    }

    /**
     * The image an entry shares, or null for the generated card. Follows the Social
     * Image field if the layout has one (a choice with no image gives the card),
     * else the first image in `imageFieldHandles`.
     */
    public function shareImage(Entry $entry): ?Asset
    {
        $value = SocialImage::valueFor($entry);

        return $value !== null
            ? $this->chosenImage($entry, $value)
            : $this->firstImage($entry, $this->settings->forEntry($entry)->imageFieldHandles);
    }

    /** Absolute URL of the image an entry shares, cropped to card size, or the card itself. */
    public function shareImageUrl(Entry $entry): string
    {
        $asset = $this->shareImage($entry);

        return ($asset ? $this->assetShareUrl($asset) : null) ?? $this->cardUrl($entry);
    }

    /**
     * As shareImageUrl(), but the card comes from the logged-in preview route, which
     * also serves drafts and disabled entries. For the CP, not front-end templates.
     */
    public function previewImageUrl(Entry $entry): string
    {
        $asset = $this->shareImage($entry);

        return ($asset ? $this->assetShareUrl($asset) : null) ?? $this->previewUrl($entry);
    }

    /** An asset's URL cropped to the card's size. */
    public function assetShareUrl(Asset $asset): ?string
    {
        return $asset->getUrl(['width' => Settings::WIDTH, 'height' => Settings::HEIGHT, 'mode' => 'crop']) ?: null;
    }

    /** Most recently updated entry of a section and/or entry type, for the settings preview. */
    public function sampleEntry(?string $sectionHandle, ?string $typeHandle): ?Entry
    {
        return $this->sampleEntryQuery($sectionHandle, $typeHandle)?->one();
    }

    /**
     * Entries for the settings preview's picker, newest first. A type without a
     * section also matches nested entries. Null when neither is given.
     */
    public function sampleEntryQuery(?string $sectionHandle, ?string $typeHandle): ?EntryQuery
    {
        if ($sectionHandle === null && $typeHandle === null) {
            return null;
        }

        $query = Entry::find()->status(null)->orderBy(['elements.dateUpdated' => SORT_DESC]);
        if ($sectionHandle !== null) {
            $query->section($sectionHandle);
        }
        if ($typeHandle !== null) {
            $query->type($typeHandle);
        }

        return $query;
    }

    /**
     * Renders the card as PNG binary, with an entry's settings from defineCard() or
     * the base ones. `$background` is an asset (cropped around its focal point) or
     * raw image contents.
     */
    public function generate(
        string $title,
        ?string $label = null,
        Asset|string|null $background = null,
        ?string $meta = null,
        ?string $description = null,
        ?string $backgroundColor = null,
        ?Settings $settings = null,
    ): string {
        if ($settings !== null && $settings !== $this->settings) {
            return $this->withSettings($settings)->generate($title, $label, $background, $meta, $description, $backgroundColor);
        }

        $s = $this->settings;
        $w = $s->width;
        $h = $s->height;
        $pad = $s->contentBox();
        $maxWidth = max(1, $w - $pad['left'] - $pad['right']);

        $backgroundColor = trim((string)$backgroundColor) ?: $s->backgroundColor;

        $img = new Imagick();
        $img->newImage($w, $h, $this->color($backgroundColor));
        $img->setImageFormat('png');

        $backgroundBlob = $background;
        $focalPoint = null;
        if ($background instanceof Asset) {
            try {
                $backgroundBlob = $background->getContents();
            } catch (\Throwable) {
                $backgroundBlob = null;
            }
            $focalPoint = $background->getFocalPoint();
        }

        if ($backgroundBlob === null || $backgroundBlob === '') {
            $backgroundBlob = $this->fileContents($s->backgroundImagePath);
            $focalPoint = null;
        }

        if ($backgroundBlob !== null && $backgroundBlob !== '') {
            $this->compositeBackground($img, $backgroundBlob, $backgroundColor, is_array($focalPoint) ? $focalPoint : null);
        }

        $this->drawBorder($img);

        // Drawn in the label's row rather than anchored on its own.
        $logo = $s->showLogo ? $this->loadLogo($s->logoHeight > 0 ? $s->logoHeight : Settings::LOGO_SIZE) : null;

        // Elements flow in one stack, so variable-height blocks push each other
        // along rather than overlap.
        $fonts = [];
        foreach (Settings::FONTS as $element => $bundled) {
            $fonts[$element] = $this->font($s->fontPathFor($element), $bundled);
        }
        $texts = ['label' => $label, 'title' => $title, 'description' => $description, 'meta' => $meta];

        $blocks = [];
        foreach (Settings::ELEMENTS as $element => $fallback) {
            // Config values skip validation, so hold the floors here.
            $size = (int)$s->{$element . 'Size'};
            $lineHeight = (float)$s->{$element . 'LineHeight'};
            $maxLines = (int)$s->{$element . 'MaxLines'};
            $style = [
                'color' => (string)$s->{$element . 'Color'},
                'size' => $size > 0 ? $size : $fallback['size'],
                'lineHeight' => $lineHeight > 0 ? $lineHeight : Settings::LINE_HEIGHT,
                'maxLines' => $maxLines > 0 ? $maxLines : $fallback['maxLines'],
                'uppercase' => (bool)$s->{$element . 'Uppercase'},
            ];
            // Hidden elements count as empty, so the stack closes up.
            $text = $s->{'show' . ucfirst($element)}
                ? $this->prepText($texts[$element] ?? null, $style['uppercase'])
                : '';
            $blockLogo = ($logo !== null && $element === 'label') ? $logo : null;

            if ($text === '') {
                // An empty or hidden label still leaves the logo its slot.
                if ($blockLogo === null) {
                    continue;
                }

                $block = [
                    'font' => $fonts[$element],
                    'lines' => [],
                    'style' => $style,
                    'size' => 0.0,
                    'lineHeight' => 0.0,
                    'baseline' => 0.0,
                    'height' => (float)$blockLogo->getImageHeight(),
                    'logo' => $blockLogo,
                    'logoTop' => 0.0,
                    'inkTop' => 0.0,
                    'inkBottom' => 0.0,
                    'gap' => (float)$s->gapAfter($element),
                ];
                $blocks[] = $block;

                continue;
            }

            $font = $fonts[$element];
            if ($element === 'title') {
                $lines = $this->fitTitle($img, $font, $text, $maxWidth, $style['size'], $style['maxLines']);
            } else {
                $measure = $this->measureDraw($font, $style['size']);
                $lines = $this->clampLines($img, $measure, $text, $maxWidth, $style['maxLines']);
                $lines = array_map(
                    fn(string $line): string => $this->truncateToWidth($img, $measure, $line, $maxWidth),
                    $lines,
                );
            }

            $size = $style['size'];
            $lineHeight = $size * $style['lineHeight'];
            // As in CSS, the space a line height adds is shared above and below the text.
            $baseline = $size + ($lineHeight - $size) / 2;
            $height = count($lines) * $lineHeight;
            // Empty space between the line box and the visible text, since letters reach
            // only ~70% of the font size. `caps` trims to cap height and last baseline
            // (consistent across cards), `ink` to the actual glyphs (negative below
            // when a descender overhangs).
            $lastBaseline = $baseline + (count($lines) - 1) * $lineHeight;
            if ($s->textEdges === 'ink') {
                $inkTop = max(0.0, $baseline - $this->inkExtent($img, $font, $size, $lines[0])[0]);
                $inkBottom = $height - $lastBaseline - $this->inkExtent($img, $font, $size, $lines[count($lines) - 1])[1];
            } else {
                $inkTop = max(0.0, $baseline - $this->capHeight($img, $font, $size));
                $inkBottom = max(0.0, $height - $lastBaseline);
            }

            $logoTop = 0.0;
            if ($blockLogo !== null) {
                // Centred on the text from cap height to last baseline, as the line
                // box has space above the caps that would sit the text low. If the
                // logo reaches further, its edge is the visible one (the inset goes
                // negative).
                $logoHeight = $blockLogo->getImageHeight();
                $capTop = $baseline - $this->capHeight($img, $font, $size);
                $logoTop = ($capTop + $lastBaseline - $logoHeight) / 2;
                $inkTop = min($inkTop, $logoTop);
                $inkBottom = min($inkBottom, $height - $logoTop - $logoHeight);
            }

            $blocks[] = [
                'font' => $font,
                'lines' => $lines,
                'style' => $style,
                'size' => $size,
                'lineHeight' => $lineHeight,
                'baseline' => $baseline,
                'height' => $height,
                'logo' => $blockLogo,
                'logoTop' => $logoTop,
                'inkTop' => $inkTop,
                'inkBottom' => $inkBottom,
                'gap' => (float)$s->gapAfter($element),
            ];
        }

        // Each element keeps its own gap below it, bar the last. `between` also shares
        // the slack between the padded ends equally across the gaps. Placed by visible
        // extent, not line boxes, which would sit text low.
        if ($blocks !== []) {
            $gaps = array_slice(array_column($blocks, 'gap'), 0, -1);
            $content = array_sum(array_column($blocks, 'height'));
            $trimTop = $blocks[0]['inkTop'];
            $trimBottom = $blocks[count($blocks) - 1]['inkBottom'];
            $visibleContent = $content - $trimTop - $trimBottom;

            if ($s->position === 'between' && $gaps !== []) {
                $slack = max(0, ($h - $pad['top'] - $pad['bottom'] - $visibleContent - array_sum($gaps)) / count($gaps));
                $gaps = array_map(fn(float $gap): float => $gap + $slack, $gaps);
            }

            $visible = $visibleContent + array_sum($gaps);
            // Config values skip validation, so unknown positions fall back to bottom.
            $top = match ($s->position) {
                'top', 'between' => (float)$pad['top'],
                'middle' => $pad['top'] + ($h - $pad['top'] - $pad['bottom'] - $visible) / 2,
                default => $h - $pad['bottom'] - $visible,
            };
            $top -= $trimTop;

            foreach ($blocks as $i => $block) {
                $this->drawBlock($img, $block, $top);
                $top += $block['height'] + ($gaps[$i] ?? 0);
            }
        }

        $logo?->destroy();

        $out = $img->getImageBlob();
        $img->destroy();

        return $out;
    }

    // -- Data helpers

    /** A clone that draws with other settings, so one card's settings never leak into the next. */
    private function withSettings(Settings $settings): self
    {
        $generator = clone $this;
        $generator->settings = $settings;

        return $generator;
    }

    /** The Social Image field's custom image or image field, or null for the card. */
    private function chosenImage(Entry $entry, SocialImageValue $value): ?Asset
    {
        if ($value->source === SocialImageValue::CUSTOM && $value->assetId) {
            return Asset::find()->id($value->assetId)->kind('image')->one();
        }

        if ($handle = $value->getFieldHandle()) {
            return $this->firstImage($entry, [$handle]);
        }

        return null;
    }

    /**
     * The background field's image, else (with `useImageAsBackground`) the image
     * picked in the Social Image field, else the first in `imageFieldHandles`. Null
     * leaves generate() to use `backgroundImagePath`.
     */
    private function backgroundImage(Entry $entry): ?Asset
    {
        $field = trim($this->settings->backgroundImageField);
        $asset = $field !== '' ? $this->firstImage($entry, [$field]) : null;

        if ($asset === null && $this->settings->useImageAsBackground) {
            // Choosing the card picks no image, so it falls through to the fields.
            $value = SocialImage::valueFor($entry);
            $asset = ($value ? $this->chosenImage($entry, $value) : null)
                ?? $this->firstImage($entry, $this->settings->imageFieldHandles);
        }

        return $asset;
    }

    /**
     * First asset across the given fields. Missing fields are skipped.
     *
     * @param string[] $handles Handles or `outer.inner` paths, see fieldValues().
     */
    private function firstImage(Entry $entry, array $handles): ?Asset
    {
        foreach ($handles as $handle) {
            foreach ($this->fieldValues($entry, $handle) as $value) {
                $asset = $value instanceof AssetQuery ? $value->one() : $value;
                if ($asset instanceof Asset) {
                    return $asset;
                }
            }
        }

        return null;
    }

    /**
     * A field's values. A dotted path like `seo.seoImage` reads `seoImage` from
     * each element in `seo` (a Matrix or Content Block field), in order, and can
     * go any number of levels deep. Missing fields give no values.
     *
     * @return mixed[]
     */
    private function fieldValues(ElementInterface $element, string $path): array
    {
        $parts = explode('.', trim($path), 2);
        if ($parts[0] === '') {
            return [];
        }

        try {
            $value = $element->getFieldValue($parts[0]);
        } catch (\Throwable) {
            return [];
        }

        if (!isset($parts[1])) {
            return [$value];
        }

        // A query when lazy, a collection when eager-loaded, an element for a Content Block.
        $nested = match (true) {
            $value instanceof ElementQueryInterface => $value->all(),
            $value instanceof ElementInterface => [$value],
            is_iterable($value) => $value,
            default => [],
        };

        $values = [];
        foreach ($nested as $child) {
            if ($child instanceof ElementInterface) {
                array_push($values, ...$this->fieldValues($child, $parts[1]));
            }
        }

        return $values;
    }

    /**
     * Renders a text template against the entry as plain text, so rich text works.
     * A template that throws is logged and leaves the element blank.
     */
    private function renderText(string $template, Entry $entry, string $element): ?string
    {
        if (trim($template) === '') {
            return null;
        }

        try {
            $text = Craft::$app->getView()->renderObjectTemplate($template, $entry);
        } catch (\Throwable $e) {
            Craft::warning("Placard couldn't render the $element template for entry $entry->id: {$e->getMessage()}", __METHOD__);

            return null;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));

        return $text !== '' ? $text : null;
    }

    /**
     * A colour from the entry's field, or null for the configured one. Only Color
     * fields and hex strings pass, as Imagick throws on anything else. A nested
     * path takes the first colour set.
     */
    private function entryColor(Entry $entry, string $handle): ?string
    {
        foreach ($this->fieldValues($entry, $handle) as $value) {
            if ($value instanceof ColorData) {
                return $value->getHex();
            }

            $value = is_scalar($value) || $value instanceof \Stringable ? trim((string)$value) : '';
            if (preg_match('/^#?([0-9a-f]{3}){1,2}$/i', $value)) {
                return $value;
            }
        }

        return null;
    }

    // -- Text wrapping

    /**
     * Wraps the title at its set size, balanced like `text-wrap: balance` when it
     * fits in $maxLines, else clamped with an ellipsis.
     *
     * @return string[]
     */
    private function fitTitle(Imagick $img, string $font, string $text, float $maxWidth, int $size, int $maxLines): array
    {
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

        return $lines;
    }

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

    /** Evens out line lengths by finding the narrowest width that still wraps to $targetLines. */
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

    /** Wraps to at most $maxLines, dropping words from the last until it fits with an ellipsis. */
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

    // -- Image helpers

    /**
     * Composites the background image over the base colour. Failures are swallowed
     * so the card still renders.
     *
     * @param array{x: float, y: float}|null $focalPoint Fractions of the image, null for the middle.
     */
    private function compositeBackground(Imagick $img, string $blob, string $backgroundColor, ?array $focalPoint = null): void
    {
        $w = $this->settings->width;
        $h = $this->settings->height;
        // Config values skip validation, so cap padding that would leave no box.
        $pad = min(max(0, $this->settings->backgroundPadding), (int)floor((min($w, $h) - 1) / 2));
        $boxW = $w - $pad * 2;
        $boxH = $h - $pad * 2;

        try {
            $bg = new Imagick();
            $bg->readImageBlob($blob);
            $bg->setImageBackgroundColor($this->color($backgroundColor));

            // Anything but `contain` (including unknown config values) crops to fill
            // the box around the focal point, as Craft's crop transforms do.
            if ($this->settings->backgroundFit === 'contain') {
                $bg->resizeImage($boxW, $boxH, Imagick::FILTER_LANCZOS, 1, true);
            } else {
                $this->coverCrop($bg, $boxW, $boxH, $focalPoint);
            }

            if ($this->settings->backgroundBlur > 0) {
                $this->blur($bg, $this->settings->backgroundBlur);
            }
            if ($this->settings->backgroundGrayscale || $this->settings->backgroundGreyscale) {
                $bg->modulateImage(100, 0, 100);
            }
            $bg->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);

            // Before the opacity drop, so both act on the same alpha.
            if ($this->settings->backgroundRadius > 0) {
                $this->roundCorners($bg, $this->settings->backgroundRadius);
            }

            $bg->evaluateImage(Imagick::EVALUATE_MULTIPLY, $this->settings->backgroundOpacity, Imagick::CHANNEL_ALPHA);

            $img->compositeImage(
                $bg,
                $this->blendMode($this->settings->backgroundBlendMode),
                (int)round(($w - $bg->getImageWidth()) / 2),
                (int)round(($h - $bg->getImageHeight()) / 2),
            );
            $bg->destroy();
        } catch (\Throwable) {
            // Render without a background.
        }
    }

    /**
     * Blurs at a reduced size and scales back up, which looks the same as a full-size
     * blur at these strengths. gaussianBlurImage() at full size took 14s a card at
     * 12px, and piled up under the settings preview's uncached re-renders.
     */
    /** Each side's border as a solid bar, over the background image. */
    private function drawBorder(Imagick $img): void
    {
        $border = $this->settings->borderBox();
        if (max($border) === 0) {
            return;
        }

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        $draw = new ImagickDraw();
        $draw->setFillColor($this->color($this->settings->borderColor));

        foreach ([
            [0, 0, $w, $border['top']],
            [0, $h - $border['bottom'], $w, $h],
            [0, 0, $border['left'], $h],
            [$w - $border['right'], 0, $w, $h],
        ] as [$x1, $y1, $x2, $y2]) {
            // Rectangle ends are inclusive.
            if ($x2 > $x1 && $y2 > $y1) {
                $draw->rectangle($x1, $y1, $x2 - 1, $y2 - 1);
            }
        }

        $img->drawImage($draw);
    }

    private function blur(Imagick $image, int $sigma): void
    {
        $iw = $image->getImageWidth();
        $ih = $image->getImageHeight();
        $factor = max(1, $sigma / 3);

        $image->resizeImage(max(1, (int)ceil($iw / $factor)), max(1, (int)ceil($ih / $factor)), Imagick::FILTER_TRIANGLE, 1);
        $image->blurImage(0, $sigma / $factor);
        $image->resizeImage($iw, $ih, Imagick::FILTER_TRIANGLE, 1);
    }

    /**
     * Unknown values (config skips validation) fall back to a normal blend.
     *
     * @return Imagick::COMPOSITE_*
     */
    private function blendMode(string $mode): int
    {
        return match ($mode) {
            'multiply' => Imagick::COMPOSITE_MULTIPLY,
            'screen' => Imagick::COMPOSITE_SCREEN,
            'overlay' => Imagick::COMPOSITE_OVERLAY,
            'darken' => Imagick::COMPOSITE_DARKEN,
            'lighten' => Imagick::COMPOSITE_LIGHTEN,
            'color-dodge' => Imagick::COMPOSITE_COLORDODGE,
            'color-burn' => Imagick::COMPOSITE_COLORBURN,
            'hard-light' => Imagick::COMPOSITE_HARDLIGHT,
            'soft-light' => Imagick::COMPOSITE_SOFTLIGHT,
            'difference' => Imagick::COMPOSITE_DIFFERENCE,
            'exclusion' => Imagick::COMPOSITE_EXCLUSION,
            default => Imagick::COMPOSITE_OVER,
        };
    }

    /**
     * Scales to cover the box, then crops around the focal point, clamped to the image.
     *
     * @param array{x: float, y: float}|null $focalPoint
     */
    private function coverCrop(Imagick $image, int $boxW, int $boxH, ?array $focalPoint): void
    {
        $scale = max($boxW / $image->getImageWidth(), $boxH / $image->getImageHeight());
        $scaledW = max($boxW, (int)ceil($image->getImageWidth() * $scale));
        $scaledH = max($boxH, (int)ceil($image->getImageHeight() * $scale));
        $image->resizeImage($scaledW, $scaledH, Imagick::FILTER_LANCZOS, 1);

        $fx = (float)($focalPoint['x'] ?? 0.5);
        $fy = (float)($focalPoint['y'] ?? 0.5);
        $x = (int)round(min(max($fx * $scaledW - $boxW / 2, 0), $scaledW - $boxW));
        $y = (int)round(min(max($fy * $scaledH - $boxH / 2, 0), $scaledH - $boxH));

        $image->cropImage($boxW, $boxH, $x, $y);
        // cropImage keeps the old canvas offset, which compositing would honour.
        $image->setImagePage(0, 0, 0, 0);
    }

    /** Masks an image to a rounded rectangle, radius capped at half the shorter side. */
    private function roundCorners(Imagick $image, int $radius): void
    {
        $iw = $image->getImageWidth();
        $ih = $image->getImageHeight();
        $radius = min($radius, (int)floor(min($iw, $ih) / 2));

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->roundRectangle(0, 0, $iw - 1, $ih - 1, $radius, $radius);

        $mask = new Imagick();
        $mask->newImage($iw, $ih, new ImagickPixel('transparent'), 'png');
        $mask->drawImage($draw);

        $image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $mask->destroy();
    }

    /** The logo at the given height, or null if it's missing or unreadable. */
    private function loadLogo(int $height): ?Imagick
    {
        $path = $this->path($this->settings->logoPath);
        if ($path === null || !is_file($path) || $height <= 0) {
            return null;
        }

        try {
            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['svg', 'svgz'], true)) {
                $logo = $this->readSvg($path, $height);
            } else {
                $logo = new Imagick();
                $logo->readImage($path);
            }

            $logo->resizeImage(0, $height, Imagick::FILTER_LANCZOS, 1);

            return $logo;
        } catch (\Throwable $e) {
            // Logged, not swallowed: a silent null here once hid a positioning bug.
            Craft::warning("Placard couldn't read the logo at $path: {$e->getMessage()}", __METHOD__);

            return null;
        }
    }

    /**
     * An SVG rasterised at 4x the given height, on a transparent background.
     *
     * Rasterised at its own size an SVG is blurry once scaled up, and ImageMagick's
     * own renderer aliases badly when small. That renderer also rounds its canvas
     * down but not the drawing, clipping the right and bottom edges, so the
     * viewBox gets a margin that's cropped back off once drawn.
     */
    private function readSvg(string $path, int $height): Imagick
    {
        $logo = new Imagick();
        $logo->setBackgroundColor(new ImagickPixel('transparent'));

        $xml = (string)file_get_contents($path);
        if (str_ends_with(strtolower($path), '.svgz')) {
            $xml = (string)gzdecode($xml);
        }

        $doc = new \DOMDocument();
        $svg = @$doc->loadXML($xml, LIBXML_NONET) ? $doc->documentElement : null;
        $box = $svg ? $this->svgBox($svg) : null;

        if ($svg === null || $box === null) {
            // Units we can't measure, so no margin, and edges may still clip.
            $probe = new Imagick();
            $probe->pingImage($path);
            $density = ($probe->getImageResolution()['y'] ?: 96) * $height * 4 / max(1, $probe->getImageHeight());
            $probe->destroy();

            $logo->setResolution($density, $density);
            $logo->readImage($path);

            return $logo;
        }

        [$x, $y, $w, $h] = $box;
        $pad = max($w, $h) * 0.05;
        $svg->setAttribute('viewBox', implode(' ', [$x - $pad, $y - $pad, $w + 2 * $pad, $h + 2 * $pad]));
        $svg->setAttribute('width', (string)($w + 2 * $pad));
        $svg->setAttribute('height', (string)($h + 2 * $pad));

        // Pixels per SVG unit; an SVG unit is a pixel at 96dpi.
        $scale = $height * 4 / $h;
        $logo->setResolution(96 * $scale, 96 * $scale);
        $logo->readImageBlob((string)$doc->saveXML(), 'logo.svg');

        $logo->cropImage(
            (int)round($w * $scale),
            (int)round($h * $scale),
            (int)round($pad * $scale),
            (int)round($pad * $scale),
        );
        $logo->setImagePage(0, 0, 0, 0);

        return $logo;
    }

    /**
     * The SVG's viewBox as [x, y, width, height], from its width and height when
     * there's no viewBox, or null if they're in units other than pixels.
     *
     * @return array{float, float, float, float}|null
     */
    private function svgBox(\DOMElement $svg): ?array
    {
        $box = preg_split('/[\s,]+/', trim($svg->getAttribute('viewBox')));
        if ($box !== false && count($box) === 4 && is_numeric($box[2]) && is_numeric($box[3]) && (float)$box[2] > 0 && (float)$box[3] > 0) {
            return array_map('floatval', $box);
        }

        $w = $svg->getAttribute('width');
        $h = $svg->getAttribute('height');
        if (preg_match('/^([\d.]+)(px)?$/', $w, $wm) && preg_match('/^([\d.]+)(px)?$/', $h, $hm) && (float)$wm[1] > 0 && (float)$hm[1] > 0) {
            return [0.0, 0.0, (float)$wm[1], (float)$hm[1]];
        }

        return null;
    }

    private function prepText(?string $text, bool $uppercase): string
    {
        $text = (string)$text;

        return trim($uppercase ? mb_strtoupper($text) : $text);
    }

    /**
     * Draws a block at a top edge. A logo and its text are aligned as one row, with
     * the text inset to make room.
     *
     * @param array{font:string, lines:string[], style:array, size:float, lineHeight:float, baseline:float, height:float, logo:?Imagick, logoTop:float} $block
     */
    private function drawBlock(Imagick $img, array $block, float $top): void
    {
        $logo = $block['logo'] ?? null;
        if ($block['lines'] === [] && $logo === null) {
            return;
        }

        $w = $this->settings->width;
        $pad = $this->settings->contentBox();

        $draw = new ImagickDraw();
        $draw->setFont($block['font']);
        $draw->setFontSize($block['size']);
        $draw->setFillColor($this->color($block['style']['color'] ?: '#ffffff'));

        $logoWidth = $logo?->getImageWidth() ?? 0;
        $gap = ($logo !== null && $block['lines'] !== []) ? Settings::LOGO_GAP : 0;
        $textWidth = $this->widestLine($img, $draw, $block['lines']);
        // Centred within the padding, so uneven sides shift it, as in CSS.
        $centre = $pad['left'] + ($w - $pad['left'] - $pad['right'] + $logoWidth + $gap) / 2;

        // [text anchor x, text alignment, logo x]
        [$x, $align, $logoX] = match ($this->settings->align) {
            'center' => [
                $centre,
                Imagick::ALIGN_CENTER,
                $centre - $textWidth / 2 - $gap - $logoWidth,
            ],
            'right' => [
                (float)($w - $pad['right']),
                Imagick::ALIGN_RIGHT,
                $w - $pad['right'] - $textWidth - $gap - $logoWidth,
            ],
            default => [
                (float)($pad['left'] + $logoWidth + $gap),
                Imagick::ALIGN_LEFT,
                (float)$pad['left'],
            ],
        };

        if ($logo !== null) {
            $img->compositeImage(
                $logo,
                Imagick::COMPOSITE_OVER,
                (int)round($logoX),
                (int)round($top + $block['logoTop']),
            );
        }

        $draw->setTextAlignment($align);
        $y = $top + $block['baseline'];
        foreach ($block['lines'] as $line) {
            $img->annotateImage($draw, $x, $y, 0, $line);
            $y += $block['lineHeight'];
        }
    }

    /**
     * Cap height measured from an "H", since fonts differ (Inter 0.72 of the size,
     * Retail Display 0.69). Falls back to 0.7.
     */
    private function capHeight(Imagick $img, string $font, float $size): float
    {
        try {
            $height = (float)$img->queryFontMetrics($this->measureDraw($font, $size), 'H')['boundingBox']['y2'];
        } catch (\Throwable) {
            $height = 0.0;
        }

        return $height > 0 ? $height : $size * 0.7;
    }

    /**
     * How far a line's glyphs reach [above, below] the baseline, both positive.
     * Measured per character, as Imagick's string bounding box covers only the last glyph.
     *
     * @return array{0: float, 1: float}
     */
    private function inkExtent(Imagick $img, string $font, float $size, string $line): array
    {
        $draw = $this->measureDraw($font, $size);
        $above = 0.0;
        $below = 0.0;

        foreach (array_unique(preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: []) as $char) {
            if (trim($char) === '') {
                continue;
            }
            $box = $img->queryFontMetrics($draw, $char)['boundingBox'];
            $above = max($above, (float)$box['y2']);
            $below = max($below, -(float)$box['y1']);
        }

        return [$above > 0 ? $above : $this->capHeight($img, $font, $size), $below];
    }

    private function widestLine(Imagick $img, ImagickDraw $draw, array $lines): float
    {
        $widest = 0.0;
        foreach ($lines as $line) {
            $widest = max($widest, (float)$img->queryFontMetrics($draw, $line)['textWidth']);
        }

        return $widest;
    }

    /** Adds the `#` the CP colour fields omit, as Imagick can't parse bare hex. Empty means black. */
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

    // -- Path resolution

    private function font(string $configured, string $fallback): string
    {
        return $this->path($configured) ?? $this->resource("fonts/$fallback");
    }

    /** A configured file's contents, or null when it's empty, missing or unreadable. */
    private function fileContents(string $configured): ?string
    {
        $path = $this->path($configured);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $blob = @file_get_contents($path);
        if ($blob === false) {
            Craft::warning("Placard couldn't read the background image at $path", __METHOD__);

            return null;
        }

        return $blob;
    }

    /** Parses env vars and aliases in a configured path. Null when empty. */
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
