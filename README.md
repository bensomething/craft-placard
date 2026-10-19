<img src="src/icon.svg" width="96" alt="Placard">

# Placard

Generates social (OG) cards with Imagick for Craft entries. Cards are rendered on demand and cached under `storage/runtime/og-images`, which keeps only each entry's latest card and can be emptied from Utilities → Caches.

A card is a combination of four text elements (label, title, description and meta) over a solid colour, with an optional logo and background image.

> [!NOTE]
> Placard is in beta. It's ready to try, but its config settings, Twig API and Social Image field may still change before 1.0.0. Please [report anything you hit](https://github.com/bensomething/craft-placard/issues).

## Requirements

- Craft CMS 5.10 or later
- PHP 8.2 or later
- The `imagick` PHP extension. SVG logos also need ImageMagick's SVG support (see *Logo*)

## Installation

```bash
composer require bensomething/craft-placard:^1.0.0-beta
```

The `-beta` in the constraint is what lets Composer install it under a project's default `stable` minimum stability.

Or install from the control panel: **Settings → Plugins**, or from the terminal:

```bash
php craft plugin/install placard
```

## Configure

Copy `example.config.php` to `config/placard.php`. **Settings → Plugins → Placard** previews the card your config produces.

`sections` and `entryTypes` override any setting except `defaultImagePath`, keyed by handle. Entry types win over sections. `styles` are named overrides editors choose between per entry (see *Styles*).

```php
return [
    'position' => 'bottom',
    'sections' => [
        'artists' => [
            'position' => 'middle',
            'background' => ['colorField' => 'colour'],
        ],
    ],
    'entryTypes' => [
        'featuredArtist' => ['meta' => false],
    ],
];
```

Paths accept aliases (`@root`, `@webroot`) and env vars. The canvas is fixed at 1200×630, the size every platform recommends.

No commercial font is bundled. Inter (SIL OFL) is the default. Set `fontPath` to use your own font throughout, and an element's own `fontPath` (see *Elements*) to give it a different font.

### Nested fields

Settings that name a field (`imageFieldHandles`, the background's `imageField` and every `colorField`) also take a path into a Matrix or Content Block field. `seo.seoImage` reads `seoImage` from the entries in `seo` and uses the first one with a value. Paths can go more than one level deep.

```php
'imageFieldHandles' => ['seo.seoImage', 'hero'],
'background' => ['colorField' => 'seo.colour'],
```

Card text templates reach nested fields themselves: `'{seo.one().seoTitle ?? title}'`.

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
| `previewImageUrl(entry)` | As `shareImageUrl()`, but the card also renders drafts for logged-in users. For the CP, like a field layout's Template element (`craft.placard.previewImageUrl(element)`), not front-end templates |
| `entryHasImage(entry)` | Whether that's one of the entry's own images |
| `url(entry)` | The card's URL. Only live entries are served, so drafts can't be read from it |
| `default()` | `defaultImagePath`, for pages with no entry, or null when it isn't set |

### With SEOmatic or SEOmate

With SEOmatic or SEOmate installed, Placard gives it each entry's share image. There's nothing to add to your templates: their `og:image` and `twitter:image` tags show the Social Image field's choice, or the card.

- **SEOmatic** gets it as the entry's SEO, Open Graph and X image, replacing the images its section settings choose. Pages that aren't entries keep SEOmatic's own.
- **SEOmate** gets it as `image`, `og:image` and `twitter:image`, as though your layout had set them in the `seomate` variable. A template that sets `seomate.meta.image` itself keeps its own. Pages that aren't entries keep SEOmate's own.

Cards are already 1200×630, so neither plugin resizes them. With SEOmatic, use the `summary_large_image` X card so they show at full size.

Set `imageFieldHandles` to the image fields your SEO plugin used, so entries without a Social Image field share the same image as before. Placard reads paths like `seo.seoImage`, but not SEOmate's `field.type:subField` form.

To pass the image yourself instead, set `'seoPlugins' => false`, then use `craft.placard.shareImageUrl()` wherever you like.

## Social Image field

Lets editors choose what each entry shares (the generated card, a custom image, or one of the entry's image fields) with a preview that updates as they edit. Its settings choose which sources are offered, which image fields are listed and the default. Asset fields inside the entry's Matrix and Content Block fields are listed too, as *SEO → SEO Image*. A layout can only hold one, and entries without URLs show a note instead.

The field must be in the entry type's own layout (a tab is fine), not inside a Matrix or Content Block field.

### Styles

With `styles` in the config, the field also offers a Style dropdown for the generated card. Each style is a set of overrides like `sections`, applied after the section's and entry type's, so the editor's choice wins. `name` labels it in the dropdown, else the handle is used.

```php
'styles' => [
    'light' => [
        'name' => 'Light',
        'background' => '#ffffff',
        'title' => ['color' => '#111111'],
    ],
    'photo' => [
        'name' => 'Photo',
        'background' => ['imageField' => 'hero', 'opacity' => 0.6],
    ],
],
```

The field's settings choose which styles it offers. The dropdown only shows while the card is what's shared, and **Settings → Plugins → Placard** previews each style.

## Card text

Each element's text is an object template rendered against the entry, given as its `text` key or as a string in place of the element's array:

```php
'title' => ['text' => '{title}', 'size' => 96],
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
| `position` | `bottom` | `top`, `middle`, `bottom` or `between` (first and last groups at the edges) |
| `layout` | `['logoLabel', 'title', 'description', 'meta']` | What's drawn, in order, and how it's grouped, see *Layout* |
| `align` | `left` | `left`, `center` or `right` |
| `textEdges` | `caps` | Place by cap height to baseline (`caps`) or by the letters' ink (`ink`) |
| `padding` | `80` | Inset for the text in px, as CSS shorthand: one value, `[vertical, horizontal]`, `[top, horizontal, bottom]` or `[top, right, bottom, left]`. Text wraps at the width between the sides, and `center` centres within them |
| `gap` | `24` | Space below each element in px, between line boxes, so the visible space also takes in line height and descenders. With `between`, gaps between groups are minimums. Elements can set their own |
| `fontPath` | Inter | Font for every element, unless it sets its own. Unset, each element uses the bundled Inter in its own weight (Bold title, Medium label, Regular description and meta) |
| `imageFieldHandles` | `['seoImage', 'hero', 'featuredImage']` | Share image fields for entries without a Social Image field |
| `defaultImagePath` | `img/og-default.png` | Returned by `default()` |
| `format` | `auto` | `png`, `jpeg`, or `auto` for JPEG when the card has a background image and PNG otherwise. Photos are several times smaller as JPEG, and flat cards keep crisper text as PNG |
| `jpegQuality` | `85` | 1 to 100, for cards saved as JPEG |
| `seoPlugins` | `true` | Give SEOmatic and SEOmate each entry's share image, see *With SEOmatic or SEOmate* |
| `sections`, `entryTypes` | `[]` | Overrides by handle |
| `styles` | `[]` | Named overrides editors choose per entry, see *Styles* |
| `label`, `title`, `description`, `meta` | | Each element's settings, see *Elements* |
| `logo`, `background`, `border` | | See below |

Element, logo, background and border settings are each an array under their name. In `sections` and `entryTypes`, an array changes only the keys it names, so `'border' => ['color' => '#ff0000']` keeps the border's widths.

### Layout

`layout` lists what's drawn, from the top: `label`, `title`, `description`, `meta`, `logo` for the logo on a row of its own, and `logoLabel` for the logo beside the label. An array groups items. Anything left out isn't drawn.

```php
'position' => 'between',
'layout' => [
    ['logoLabel'],
    ['title', 'description', 'meta'],
],
```

Items in a group keep their own gaps. With `between`, the first group sits at the top, the last at the bottom, and any between are spaced evenly, so this puts the logo and label at the top and the rest together at the bottom. Other positions stack the groups as one. Each item can appear once, and `logoLabel` counts as both the logo and the label.

Leave `layout` unset and cards stack as before, with each element its own group and in the order of: `logoLabel`, `title`, `description`, and `meta`. Like any setting, `sections`, `entryTypes` and `styles` can override it.

### Elements

`label`, `title`, `description` and `meta` each take an array of their settings. A string sets just the text, and `false` hides the element.

```php
'title' => [
    'text' => '{title}',
    'size' => 96,
    'lineHeight' => 1.05,
    'maxLines' => 2,
],
'description' => ['text' => '{summary}', 'lineHeight' => 1.3],
'meta' => false,
```

| Key | Default (label, title, description, meta) | Notes |
|---|---|---|
| `text` | `{type.name}`, `{title}`, empty, empty | Object template, see *Card text* |
| `show` | `true` | Turn the element off. Hiding the label keeps the logo |
| `size` | `28`, `116`, `32`, `28` | Text size in px |
| `lineHeight` | `1.02` | A multiple of the size, as in CSS. The extra space is shared above and below each line |
| `maxLines` | `1`, `3`, `2`, `1` | Lines before the text is clamped with an ellipsis. A title needing fewer is balanced across the lines it uses |
| `uppercase` | `false`, `true`, `false`, `false` | Set the text in capitals |
| `color` | `#a3a3a3`, `#ffffff`, `#a3a3a3`, `#a3a3a3` | Text colour |
| `colorField` | none | A Color field (or hex text field) to take the colour from, falling back to `color` |
| `fontPath` | none | The element's font, over `fontPath` |
| `gap` | none | Space below the element, over `gap` |
| `align` | none | `left`, `center` or `right`, over `align`. The label's also aligns a logo beside it |

### Logo

```php
'logo' => ['path' => '@webroot/img/logo.png', 'height' => 40],
```

A string sets just the path, and `false` hides the logo.

| Key | Default | Notes |
|---|---|---|
| `path` | none | PNG or SVG drawn beside the label, or on its own row (see *Layout*) |
| `height` | `48` | Height in px. Its width follows its proportions |
| `show` | `true` | Turn the logo off |
| `align` | none | `left`, `center` or `right` on its own row, over `align`. Beside the label, it follows the label's |

A PNG works on every server. Export it at about four times `height` so it stays sharp. SVG logos need ImageMagick with SVG support, which many servers don't have; without it the card is drawn with no logo and a warning is logged. Where it is available, ImageMagick's built-in SVG renderer can't draw masks, gradients or filters, so an SVG using them may come out wrong.

### Background

```php
'background' => [
    'color' => '#1f1f1f',
    'imageField' => 'hero',
    'opacity' => 0.4,
    'blendMode' => 'multiply',
],
```

A string sets just the colour. The image is the `imageField`'s, then the share image (with `useShareImage`), then `imagePath`. With a Social Image field, the share image is the custom image or image field the editor picked. With the card picked, or no field, it's the first image in `imageFieldHandles`.

Images from asset fields must be JPEG, PNG, GIF, WebP or AVIF. Anything else, such as an SVG, is skipped with a warning in the log, as ImageMagick chooses how to read a file from its contents rather than its extension. `imagePath` is set in config, so it isn't checked.

| Key | Default | Notes |
|---|---|---|
| `color` | `#000000` | Canvas colour |
| `colorField` | none | A Color field (or hex text field) to take the colour from, falling back to `color` |
| `imageField` | none | Asset field for the background image |
| `useShareImage` | `false` | Fall back to the share image as the background: the Social Image field's pick, else the first image in `imageFieldHandles` |
| `imagePath` | none | Fixed background image, used last |
| `fit` | `cover` | `cover` (cropped around the focal point) or `contain` |
| `padding`, `radius` | `0` | Inset and corner radius in px |
| `grayscale` | `false` | Set the image in grayscale |
| `opacity` | `0.25` | Strength of the image. A transparent PNG keeps its transparency, scaled by this |
| `imagePathOpacity` | none | `opacity` for the `imagePath` image only, so a section can show its photos stronger than the pattern it falls back to |
| `blur` | `0` | Blur strength in px, roughly as CSS `blur()` |
| `blendMode` | `normal` | How the image blends with the colour: `multiply`, `screen`, `overlay`, `darken`, `lighten`, `color-dodge`, `color-burn`, `hard-light`, `soft-light`, `difference` or `exclusion`, as in CSS |

### Border

```php
'border' => ['width' => [8, 0, 0, 0], 'color' => '#f5b400'],
```

A number or list sets just the widths, so `'border' => [8, 0, 0, 0]` is a bar along the top in the default colour.

| Key | Default | Notes |
|---|---|---|
| `width` | `0` | Widths in px, as CSS shorthand like `padding`. Drawn over the background image, and the text sits inside it |
| `color` | `#ffffff` | Border colour |
| `colorField` | none | A Color field (or hex text field) to take the colour from, falling back to `color` |

## Licence

MIT. Bundled Inter fonts are SIL OFL 1.1 (see `src/resources/fonts/OFL.txt`).
