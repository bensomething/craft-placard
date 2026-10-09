<?php

namespace bensomething\placard\services;

use bensomething\placard\events\DefineCardEvent;
use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\Settings;
use bensomething\placard\models\SocialImageValue;
use bensomething\placard\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
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
 * Renders social / OG cards with Imagick.
 *
 * One layout: label, title, description and meta line in a single stack over a solid
 * background, with an optional logo beside the label and an optional faint
 * background photo. {@see Settings} supplies where the stack sits and the brand:
 * colours, fonts and images.
 */
class Generator extends Component
{
    /**
     * @event DefineCardEvent Fired to derive a card's title / label / description / meta /
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
     * Builds a card spec for an entry: resolves the settings for its section and
     * entry type, fills defaults (the text templates, background image, colour
     * field) then fires {@see self::EVENT_DEFINE_CARD} so the project can
     * customise it. Returns the resolved event; pass its `settings` to
     * {@see generate()}.
     */
    public function defineCard(Entry $entry): DefineCardEvent
    {
        $settings = $this->settings->forEntry($entry);
        $resolved = $this->withSettings($settings);

        $event = new DefineCardEvent([
            'entry' => $entry,
            'settings' => $settings,
            'title' => (string)$resolved->renderText($settings->title, $entry, 'title'),
            'label' => (string)$resolved->renderText($settings->label, $entry, 'label'),
            'description' => $resolved->renderText($settings->description, $entry, 'description'),
            'meta' => $resolved->renderText($settings->meta, $entry, 'meta'),
            'background' => $resolved->backgroundImage($entry),
            'backgroundColor' => $resolved->entryColor($entry),
        ]);

        $this->trigger(self::EVENT_DEFINE_CARD, $event);

        return $event;
    }

    /**
     * Absolute URL to the generated card for an entry. The card is served as
     * immutable, so anything that changes it has to change the URL: the `v` token
     * covers the entry and the settings it's drawn with — otherwise a config change
     * would leave browsers and social platforms on the old card.
     */
    public function cardUrl(Entry $entry): string
    {
        $settings = $this->settings->forEntry($entry);
        $token = substr(md5(($entry->dateUpdated?->getTimestamp() ?? 0) . serialize($settings->getAttributes())), 0, 10);

        return UrlHelper::siteUrl('og/' . $entry->id . '.png', ['v' => $token]);
    }

    /**
     * The image an entry shares, or null when that's the generated card.
     *
     * Follows the entry's Social Image field, if its layout has one: the card, one
     * of its image fields, or a picked image. Anything that gives no image — and
     * entries without the field — falls back to `auto`: the first image in the
     * configured `imageFieldHandles`, else the card.
     */
    public function shareImage(Entry $entry): ?Asset
    {
        $value = $this->socialImageValue($entry);

        if ($value?->source === SocialImageValue::CARD) {
            return null;
        }

        if ($value?->source === SocialImageValue::CUSTOM && $value->assetId) {
            $asset = Asset::find()->id($value->assetId)->kind('image')->one();
            if ($asset) {
                return $asset;
            }
        }

        if ($handle = $value?->getFieldHandle()) {
            $asset = $this->firstImage($entry, [$handle]);
            if ($asset) {
                return $asset;
            }
        }

        return $this->firstImage($entry, $this->settings->forEntry($entry)->imageFieldHandles);
    }

    /**
     * Absolute URL of the image an entry shares: {@see shareImage()} cropped to the
     * card's size, or the generated card.
     */
    public function shareImageUrl(Entry $entry): string
    {
        $asset = $this->shareImage($entry);

        return ($asset ? $this->assetShareUrl($asset) : null) ?? $this->cardUrl($entry);
    }

    /** An asset as a share image: cropped to the card's 1200×630. */
    public function assetShareUrl(Asset $asset): ?string
    {
        return $asset->getUrl(['width' => Settings::WIDTH, 'height' => Settings::HEIGHT, 'mode' => 'crop']) ?: null;
    }

    /** The entry's Social Image field value, if its layout has one. */
    private function socialImageValue(Entry $entry): ?SocialImageValue
    {
        foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof SocialImage) {
                $value = $entry->getFieldValue($field->handle);

                return $value instanceof SocialImageValue ? $value : null;
            }
        }

        return null;
    }

    /**
     * The most recently updated entry of a section / entry type pair, or null when
     * neither is given or there isn't one. Used by the settings-page preview.
     */
    public function sampleEntry(?string $sectionHandle, ?string $typeHandle): ?Entry
    {
        return $this->sampleEntryQuery($sectionHandle, $typeHandle)?->one();
    }

    /**
     * Entries of a section / entry type pair, most recently updated first, for the
     * settings-page preview's entry picker. An entry type with no section is looked
     * for anywhere, which takes in nested entries. Null when neither is given.
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
     * Returns the rendered card as PNG binary, drawn with the given settings — an
     * entry's resolved ones, from {@see defineCard()} — or the base settings.
     */
    public function generate(
        string $title,
        ?string $label = null,
        ?string $backgroundBlob = null,
        ?string $meta = null,
        ?string $description = null,
        ?string $backgroundColor = null,
        ?Settings $settings = null,
    ): string {
        if ($settings !== null && $settings !== $this->settings) {
            return $this->withSettings($settings)->generate($title, $label, $backgroundBlob, $meta, $description, $backgroundColor);
        }

        $s = $this->settings;
        $w = $s->width;
        $h = $s->height;
        $maxWidth = $w - (Settings::PADDING * 2);

        $displayFont = $this->displayFont();
        $labelFont = $this->font($s->labelFontPath, 'Inter-Medium.ttf');
        $metaFont = $this->font($s->metaFontPath, 'Inter-Regular.ttf');
        $descriptionFont = $this->font($s->descriptionFontPath, 'Inter-Regular.ttf');

        $backgroundColor = trim((string)$backgroundColor) ?: $s->backgroundColor;

        $img = new Imagick();
        $img->newImage($w, $h, $this->color($backgroundColor));
        $img->setImageFormat('png');

        // The fixed image is the fallback for when the entry gives none.
        if ($backgroundBlob === null || $backgroundBlob === '') {
            $backgroundBlob = $this->fileContents($s->backgroundImagePath);
        }

        // Optional background image: cover-fit, grayscale, faint, behind everything.
        if ($backgroundBlob !== null && $backgroundBlob !== '') {
            $this->compositeBackground($img, $backgroundBlob, $backgroundColor);
        }

        // The logo isn't anchored on its own — it rides alongside the label, so it's
        // prepared here and drawn with the label's row.
        $logo = $s->showLogo ? $this->loadLogo(Settings::LOGO_SIZE) : null;

        // Every element is wrapped into a block of lines, and the blocks flow in one
        // stack. Stacking is what keeps the layout collision-free: the blocks whose
        // height varies with the text push each other along instead of overlapping.
        $fonts = [
            'label' => $labelFont,
            'title' => $displayFont,
            'description' => $descriptionFont,
            'meta' => $metaFont,
        ];
        $texts = ['label' => $label, 'title' => $title, 'description' => $description, 'meta' => $meta];

        $blocks = [];
        foreach (Settings::ELEMENTS as $element => $style) {
            $style['color'] = (string)$s->{$element . 'Color'};
            if ($element === 'title') {
                // Config values skip validation, so hold the floor here.
                $style['maxLines'] = max(1, $s->titleMaxLines);
                $style['size'] = $s->titleSize > 0 ? $s->titleSize : $style['size'];
                $style['uppercase'] = $s->titleUppercase;
            }
            // A hidden element is treated as empty, so the stack closes up the same way.
            $text = $s->{'show' . ucfirst($element)}
                ? $this->prepText($texts[$element] ?? null, $style['uppercase'])
                : '';
            $blockLogo = ($logo !== null && $element === 'label') ? $logo : null;

            if ($text === '') {
                // The label isn't drawing, but the logo beside it still should — an
                // empty or hidden label shouldn't take the brand mark with it. It keeps the
                // label's slot in the stack, to itself.
                if ($blockLogo === null) {
                    continue;
                }

                $block = [
                    'font' => $fonts[$element],
                    'lines' => [],
                    'style' => $style,
                    'size' => 0.0,
                    'lineHeight' => 0.0,
                    'height' => (float)$blockLogo->getImageHeight(),
                    'logo' => $blockLogo,
                    'inkTop' => 0.0,
                    'inkBottom' => 0.0,
                ];
                $blocks[] = $block;

                continue;
            }

            $font = $fonts[$element];
            if ($element === 'title') {
                // Only the title shrinks to fit its line budget; the rest wrap at the
                // size they're given.
                [$lines, $size, $lineHeight] = $this->fitTitle($img, $font, $text, $maxWidth, $style['size'], $style['maxLines']);
            } else {
                $size = $style['size'];
                $lineHeight = $size * Settings::LINE_HEIGHT;
                $measure = $this->measureDraw($font, $size);
                $lines = $this->clampLines($img, $measure, $text, $maxWidth, $style['maxLines']);
                $lines = array_map(
                    fn(string $line): string => $this->truncateToWidth($img, $measure, $line, $maxWidth),
                    $lines,
                );
            }

            $height = count($lines) * $lineHeight;
            // The empty space inside the block's box, above its top edge and below
            // its bottom one. A line box starts a full font size above its baseline,
            // and letters only reach ~70% of that, so positioning by the box would sit
            // text low. With `caps` the edges are the cap height and the last
            // baseline: descenders hang below, and text sits at the same height on
            // every card. With `ink` they're what these lines' letters actually reach
            // (negative below when a descender runs past the box).
            $lastBaseline = $size + (count($lines) - 1) * $lineHeight;
            if ($s->textEdges === 'ink') {
                $inkTop = max(0.0, $size - $this->inkExtent($img, $font, $size, $lines[0])[0]);
                $inkBottom = $height - $lastBaseline - $this->inkExtent($img, $font, $size, $lines[count($lines) - 1])[1];
            } else {
                $inkTop = max(0.0, $size - $this->capHeight($img, $font, $size));
                $inkBottom = max(0.0, $height - $lastBaseline);
            }

            if ($blockLogo !== null) {
                // The logo is centred on the block; if it reaches past the text, the
                // block's visible edge is the logo's.
                $logoInset = ($height - $blockLogo->getImageHeight()) / 2;
                // Negative when the logo is taller than the text and overhangs it.
                $inkTop = min($inkTop, $logoInset);
                $inkBottom = min($inkBottom, $logoInset);
            }

            $blocks[] = [
                'font' => $font,
                'lines' => $lines,
                'style' => $style,
                'size' => $size,
                'lineHeight' => $lineHeight,
                'height' => $height,
                'logo' => $blockLogo,
                'inkTop' => $inkTop,
                'inkBottom' => $inkBottom,
            ];
        }

        // One stack, laid out top to bottom. `between` pushes the ends to the padding
        // edges and shares the slack out evenly; the rest anchor the block as a whole
        // and keep a fixed gap. The stack is placed by its visible extent — the first
        // block's cap top to the last block's baseline — not its line boxes, which
        // carry empty space above the caps and would sit the text low.
        if ($blocks !== []) {
            $padY = Settings::PADDING;
            $gap = Settings::ELEMENT_GAP;
            $content = array_sum(array_column($blocks, 'height'));
            $trimTop = $blocks[0]['inkTop'];
            $trimBottom = $blocks[count($blocks) - 1]['inkBottom'];
            $visibleContent = $content - $trimTop - $trimBottom;

            if ($s->position === 'between' && count($blocks) > 1) {
                $gap = max($gap, ($h - ($padY * 2) - $visibleContent) / (count($blocks) - 1));
            }

            $visible = $visibleContent + $gap * (count($blocks) - 1);
            // Config values skip validation, so anything unrecognised falls back to
            // the default rather than throwing.
            $top = match ($s->position) {
                'top', 'between' => (float)$padY,
                'middle' => ($h - $visible) / 2,
                default => $h - $padY - $visible,
            } - $trimTop;

            foreach ($blocks as $block) {
                $this->drawBlock($img, $block, $top);
                $top += $block['height'] + $gap;
            }
        }

        $logo?->destroy();

        $out = $img->getImageBlob();
        $img->destroy();

        return $out;
    }

    // -- Data helpers ---------------------------------------------------------

    /**
     * A copy of this generator that draws with other settings. Every helper reads
     * `$this->settings`, so swapping the whole generator keeps one card's settings
     * from leaking into the next.
     */
    private function withSettings(Settings $settings): self
    {
        $generator = clone $this;
        $generator->settings = $settings;

        return $generator;
    }

    /**
     * The entry's background image: the first asset in the background image field,
     * else (when the switch is on) its share image, else null — leaving the fixed
     * `backgroundImagePath` to {@see generate()}.
     */
    private function backgroundImage(Entry $entry): ?Asset
    {
        $field = trim($this->settings->backgroundImageField);
        $asset = $field !== '' ? $this->firstImage($entry, [$field]) : null;

        if ($asset === null && $this->settings->useImageAsBackground) {
            $asset = $this->firstImage($entry, $this->settings->imageFieldHandles);
        }

        return $asset;
    }

    /**
     * First asset found among the given asset field handles, or null. Missing
     * fields are skipped.
     *
     * @param string[] $handles
     */
    private function firstImage(Entry $entry, array $handles): ?Asset
    {
        foreach ($handles as $handle) {
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

    /**
     * Renders one element's text template against the entry, as plain text: tags
     * stripped (so rich text fields work), entities decoded, whitespace collapsed.
     * Null when the template is empty or renders nothing. A template that throws —
     * a misspelt field, say — is logged and leaves the element blank rather than
     * taking the card down.
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
     * The canvas colour from the entry's {@see Settings::$backgroundColorField}, or
     * null to fall back to the configured colour. Takes a Color field, or any field
     * whose value is a hex string; anything else is ignored rather than handed to
     * Imagick, which would throw on it and take the card down.
     */
    private function entryColor(Entry $entry): ?string
    {
        $handle = trim($this->settings->backgroundColorField);
        if ($handle === '') {
            return null;
        }

        try {
            $value = $entry->getFieldValue($handle);
        } catch (\Throwable) {
            return null;
        }

        if ($value instanceof ColorData) {
            return $value->getHex();
        }

        $value = is_scalar($value) || $value instanceof \Stringable ? trim((string)$value) : '';

        return preg_match('/^#?([0-9a-f]{3}){1,2}$/i', $value) ? $value : null;
    }

    // -- Title fitting (shrink-to-lines + balance / clamp) --------------------

    /**
     * Wraps the title at a fixed large size. Lines that fit within maxLines are
     * balanced to even lengths (à la `text-wrap: balance`); anything longer is
     * clamped to maxLines with a trailing ellipsis rather than scaled down.
     *
     * Returns [lines, fontSize, lineHeight].
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

        return [$lines, $size, $size * Settings::LINE_HEIGHT];
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
    private function compositeBackground(Imagick $img, string $blob, string $backgroundColor): void
    {
        $w = $this->settings->width;
        $h = $this->settings->height;
        // The box the image fits into: the canvas, less the padding. Config values
        // skip validation, so a padding that would leave no box is capped.
        $pad = min(max(0, $this->settings->backgroundPadding), (int)floor((min($w, $h) - 1) / 2));
        $boxW = $w - $pad * 2;
        $boxH = $h - $pad * 2;

        try {
            $bg = new Imagick();
            $bg->readImageBlob($blob);
            $bg->setImageBackgroundColor($this->color($backgroundColor));

            // `contain` fits the whole image inside the box, scaling up or down,
            // and centres it on the canvas colour. Anything else (`cover`, or an
            // unrecognised config value) centre-crops to fill the box.
            if ($this->settings->backgroundFit === 'contain') {
                $bg->resizeImage($boxW, $boxH, Imagick::FILTER_LANCZOS, 1, true);
            } else {
                $bg->cropThumbnailImage($boxW, $boxH);
            }

            // Optionally desaturate, then drop to the configured opacity. No blur:
            // at this opacity it changed little, and a full-canvas gaussian cost
            // ~14s a card — which the settings preview, re-rendering uncached on
            // every poll, turned into a pile-up that starved everything else.
            if ($this->settings->backgroundGreyscale) {
                $bg->modulateImage(100, 0, 100);
            }
            $bg->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);

            // Round the corners of the image as placed (the fitted image for
            // `contain`, the box for `cover`), before the opacity drop so both
            // apply to the same alpha.
            if ($this->settings->backgroundRadius > 0) {
                $this->roundCorners($bg, $this->settings->backgroundRadius);
            }

            $bg->evaluateImage(Imagick::EVALUATE_MULTIPLY, $this->settings->backgroundOpacity, Imagick::CHANNEL_ALPHA);

            $img->compositeImage(
                $bg,
                Imagick::COMPOSITE_OVER,
                (int)round(($w - $bg->getImageWidth()) / 2),
                (int)round(($h - $bg->getImageHeight()) / 2),
            );
            $bg->destroy();
        } catch (\Throwable) {
            // Render without a background.
        }
    }

    /**
     * Masks an image to a rounded rectangle. The radius is capped at half the
     * shorter side, where the corners meet.
     */
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

    /**
     * Loads the logo, resized to the given height. Returns null when there's no
     * logo configured, the file is missing, or it can't be read — the card is still
     * worth rendering without it.
     */
    private function loadLogo(int $height): ?Imagick
    {
        $path = $this->path($this->settings->logoPath);
        if ($path === null || !is_file($path) || $height <= 0) {
            return null;
        }

        try {
            $logo = new Imagick();
            $logo->readImage($path);
            $logo->resizeImage(0, $height, Imagick::FILTER_LANCZOS, 1);

            return $logo;
        } catch (\Throwable $e) {
            // Don't let a bad logo take the card down, but don't swallow it either:
            // a silent null here once hid a bug in our own positioning code.
            Craft::warning("Placard couldn't read the logo at $path: {$e->getMessage()}", __METHOD__);

            return null;
        }
    }

    /** Uppercases + trims a nullable text value per the element's setting. */
    private function prepText(?string $text, bool $uppercase): string
    {
        $text = (string)$text;

        return trim($uppercase ? mb_strtoupper($text) : $text);
    }

    /**
     * Draws one block at a given top edge, with its logo alongside if it has one.
     *
     * The logo and the text are laid out as a single row — the pair is positioned
     * according to {@see Settings::$align}, so the text is inset to make room rather
     * than the logo being anchored separately and hoped to miss.
     *
     * @param array{font:string, lines:string[], style:array, size:float, lineHeight:float, height:float, logo:?Imagick} $block
     */
    private function drawBlock(Imagick $img, array $block, float $top): void
    {
        $logo = $block['logo'] ?? null;
        if ($block['lines'] === [] && $logo === null) {
            return;
        }

        $w = $this->settings->width;
        $padX = Settings::PADDING;

        $draw = new ImagickDraw();
        $draw->setFont($block['font']);
        $draw->setFontSize($block['size']);
        $draw->setFillColor($this->color($block['style']['color'] ?: '#ffffff'));

        $logoWidth = $logo?->getImageWidth() ?? 0;
        // No text beside it means no gap to hold open.
        $gap = ($logo !== null && $block['lines'] !== []) ? Settings::ELEMENT_GAP : 0;
        $textWidth = $this->widestLine($img, $draw, $block['lines']);

        // Where the text anchors, and where the logo goes relative to it.
        [$x, $align, $logoX] = match ($this->settings->align) {
            'center' => [
                ($w + $logoWidth + $gap) / 2,
                Imagick::ALIGN_CENTER,
                ($w + $logoWidth + $gap) / 2 - $textWidth / 2 - $gap - $logoWidth,
            ],
            'right' => [
                (float)($w - $padX),
                Imagick::ALIGN_RIGHT,
                $w - $padX - $textWidth - $gap - $logoWidth,
            ],
            default => [
                (float)($padX + $logoWidth + $gap),
                Imagick::ALIGN_LEFT,
                (float)$padX,
            ],
        };

        if ($logo !== null) {
            // Centred on the block, so it reads as sitting with the text rather than
            // hanging off its first line.
            $img->compositeImage(
                $logo,
                Imagick::COMPOSITE_OVER,
                (int)round($logoX),
                (int)round($top + ($block['height'] - $logo->getImageHeight()) / 2),
            );
        }

        $draw->setTextAlignment($align);
        $y = $top + $block['size'];
        foreach ($block['lines'] as $line) {
            $img->annotateImage($draw, $x, $y, 0, $line);
            $y += $block['lineHeight'];
        }
    }

    /**
     * Height of a capital above the baseline, for a font at a size — measured from
     * an "H", since fonts don't agree on it (Inter's is 0.72 of the size, Retail
     * Display's 0.69). Falls back to the usual 0.7 if the font won't measure.
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
     * How far a line's letters reach above and below the baseline: [above, below],
     * both positive. Measured a character at a time, because Imagick's bounding box
     * for a whole string only describes its last glyph.
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

    /** Width of the longest line in a block, used to lay a logo out beside it. */
    private function widestLine(Imagick $img, ImagickDraw $draw, array $lines): float
    {
        $widest = 0.0;
        foreach ($lines as $line) {
            $widest = max($widest, (float)$img->queryFontMetrics($draw, $line)['textWidth']);
        }

        return $widest;
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
     * Reads a configured image path, or null when it's empty, missing or unreadable.
     * A bad path shouldn't cost you the whole card.
     */
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
