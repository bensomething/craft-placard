# Placard

Generates social (OG) cards with Imagick for Craft entries. Cards are rendered on demand and cached under `storage/runtime/og-images`.

A card is a stack of four text elements (label, title, description and meta) over a solid colour, with an optional logo beside the label and an optional background image.

## Requirements

- Craft CMS 5.10 or later
- PHP 8.2 or later
- The `imagick` PHP extension

## Install

Placard is available from Packagist while it's being tested:

```bash
composer require bensomething/craft-placard:^1.0@beta
php craft plugin/install placard
```

## Configure

Copy `example.config.php` to `config/placard.php`. **Settings → Plugins → Placard** previews the card your config produces.

`sections` and `entryTypes` override any setting except `defaultImagePath`, keyed by handle. Entry types win over sections.

```php
return [
    'position' => 'bottom',
    'sections' => [
        'artists' => [
            'position' => 'middle',
            'backgroundColorField' => 'colour',
        ],
    ],
    'entryTypes' => [
        'featuredArtist' => ['showMeta' => false],
    ],
];
```

Paths accept aliases (`@root`, `@webroot`) and env vars. The canvas is fixed at 1200×630, the size every platform recommends.

No commercial font is bundled. Inter (SIL OFL) is the default. Set `fontPath` to use your own font throughout, and `titleFontPath`, `labelFontPath`, `descriptionFontPath` or `metaFontPath` to give one element a different font.

## Templates

```twig
{% set ogImage = craft.placard.shareImageUrl(entry ?? null) %}
{% if ogImage %}
    <meta property="og:image" content="{{ ogImage }}">
{% endif %}
```

| Helper | Returns |
|---|---|
| `shareImageUrl(entry)` | The image the entry shares (its Social Image field's choice, else the first image in `imageFieldHandles`, else the card). Anything that isn't an entry gets `default()` |
| `entryHasImage(entry)` | Whether that's one of the entry's own images |
| `url(entry)` | The card's URL. Only live entries are served, so drafts can't be read from it |
| `default()` | `defaultImagePath`, for pages with no entry, or null when it isn't set |

### With SEOmatic

SEOmatic's image source settings only list Assets fields, so they can't read the Social Image field. Pass the URL to SEOmatic from your layout instead:

```twig
{% set ogImage = craft.placard.shareImageUrl(entry ?? null) %}
{% if ogImage %}
    {% do seomatic.meta.setAttributes({
        seoImage: ogImage,
        ogImage: ogImage,
        twitterImage: ogImage,
    }) %}
{% endif %}
```

This overrides the section's SEOmatic settings. SEOmatic doesn't transform an image given as a URL, and cards are already 1200×630. Use the `summary_large_image` Twitter card so they show at full size.

## Social Image field

Lets editors choose what each entry shares (the generated card, a custom image, or one of the entry's image fields) with a preview that updates as they edit. Its settings choose which sources are offered, which image fields are listed and the default. A layout can only hold one, and entries without URLs show a note instead.

## Card text

Each element is an object template rendered against the entry:

```php
'title' => '{title}',
'label' => '{type.name}',
'description' => '{summary ?: seoDescription}',
'meta' => '{postDate|date("j F Y")}',
```

HTML is stripped. Description and meta are empty by default, so they don't show until set. A template that errors is logged and leaves that element blank.

## DefineCard event

For anything a template can't do:

```php
use bensomething\placard\events\DefineCardEvent;
use bensomething\placard\services\Generator;
use yii\base\Event;

Event::on(Generator::class, Generator::EVENT_DEFINE_CARD, function (DefineCardEvent $e) {
    if ($e->entry->section?->handle === 'home') {
        $e->title = 'My Brand';
        $e->label = '';
    }
});
```

The event has `title`, `label`, `description`, `meta`, `background` and `backgroundColor`, plus the card's resolved `settings`, which can be changed for that card alone. An empty value drops that element.

## Console

```bash
php craft placard/preview 123                    # an entry's card
php craft placard/preview --title="Hello world"  # a dummy card
```

Also takes `--label`, `--description`, `--meta` and `--image`. Output goes to `storage/runtime/og-images-preview/`.

## Settings

| Setting | Default | Notes |
|---|---|---|
| `position` | `bottom` | `top`, `middle`, `bottom` or `between` (first and last at the edges) |
| `align` | `left` | `left`, `center` or `right` |
| `textEdges` | `caps` | Place by cap height to baseline (`caps`) or by the letters' ink (`ink`) |
| `titleMaxLines` | `3` | Lines before the title is clamped with an ellipsis |
| `titleSize` | `116` | Title size in px |
| `padding` | `80` | Inset for the text in px, as CSS shorthand: one value, `[vertical, horizontal]`, `[top, horizontal, bottom]` or `[top, right, bottom, left]`. Text wraps at the width between the sides, and `center` centres within them |
| `gap` | `24` | Space below each element in px, between line boxes, so the visible space also takes in line height and descenders. With `between` it's a minimum |
| `labelGap`, `titleGap`, `descriptionGap` | none | Space below one element, over `gap`. The last element shown has none below it |
| `titleUppercase` | `true` | Set the title in capitals |
| `showLabel`, `showTitle`, `showDescription`, `showMeta`, `showLogo` | `true` | Turn an element off |
| `title`, `label`, `description`, `meta` | `{title}`, `{type.name}`, empty, empty | Object templates, see *Card text* |
| `backgroundColor` | `#000000` | Canvas colour |
| `labelColor`, `titleColor`, `descriptionColor`, `metaColor` | `#a3a3a3`, `#ffffff`, `#a3a3a3`, `#a3a3a3` | Text colours |
| `backgroundColorField`, `labelColorField`, `titleColorField`, `descriptionColorField`, `metaColorField` | none | A Color field (or hex text field) to take that colour from, falling back to the setting above |
| `fontPath` | Inter | Font for every element. Unset, each element uses the bundled Inter in its own weight (Bold title, Medium label, Regular description and meta) |
| `titleFontPath`, `labelFontPath`, `descriptionFontPath`, `metaFontPath` | none | One element's font, over `fontPath` |
| `logoPath` | none | PNG drawn beside the label |
| `imageFieldHandles` | `['seoImage', 'hero', 'featuredImage']` | Share image fields for entries without a Social Image field |
| `backgroundImageField` | none | Asset field for the background image |
| `useImageAsBackground` | `false` | Fall back to the share image as the background |
| `backgroundImagePath` | none | Fixed background image, used last |
| `backgroundFit` | `cover` | `cover` (cropped around the focal point) or `contain` |
| `backgroundPadding`, `backgroundRadius` | `0` | Inset and corner radius in px |
| `backgroundGrayscale`, `backgroundOpacity` | `false`, `0.25` | Grayscale and strength of the background image |
| `backgroundBlur` | `0` | Blur strength of the background image in px, roughly as CSS `blur()` |
| `backgroundBlendMode` | `normal` | How the background image blends with `backgroundColor`: `multiply`, `screen`, `overlay`, `darken`, `lighten`, `color-dodge`, `color-burn`, `hard-light`, `soft-light`, `difference` or `exclusion`, as in CSS |
| `defaultImagePath` | `img/og-default.png` | Returned by `default()` |
| `sections`, `entryTypes` | `[]` | Overrides by handle |

## Licence

MIT. Bundled Inter fonts are SIL OFL 1.1 (see `src/resources/fonts/OFL.txt`).
