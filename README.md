# Herald (Craft plugin)

Generates configurable social / OG cards with Imagick for Craft entries that have
no share image. A black (configurable) card with the entry title set large in a
display font, an optional kicker + date, an optional logo, and an optional faint
background photo. Cards are rendered on demand and cached under
`storage/runtime/og-images`.

Craft 5 · PHP 8.2+ · requires the `imagick` PHP extension.

This is a **private plugin** — installed via Composer from a private repo, not the
Craft Plugin Store.

## Install

Add the repo to the consuming project's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:YOURNAME/craft-herald.git" }
  ]
}
```

Then:

```bash
composer require bensomething/craft-herald
php craft plugin/install herald
```

## Configure

Copy `example.config.php` to your project's `config/herald.php` and edit it —
those values override the CP settings and are the recommended place to configure
the plugin per project. Font / logo / config paths accept Craft aliases
(`@root`, `@webroot`) and env vars.

⚠️ **Fonts.** No commercial font is bundled. The plugin ships with **Inter**
(SIL OFL) as the default body + fallback display font. Point `displayFontPath` at
your own licensed display font per project.

## Use in templates

```twig
{% set og = craft.herald.entryHasImage(entry)
    ? entry.seoImage.one().url          {# your own share image #}
    : craft.herald.url(entry) %}        {# generated card #}

<meta property="og:image" content="{{ og ?? craft.herald.default() }}">
```

- `craft.herald.url(entry)` — generated card URL (`/og/<id>.png`), cache-busted.
- `craft.herald.entryHasImage(entry)` — true if the entry has an image in any of
  the configured `imageFieldHandles`.
- `craft.herald.shareImageUrl(entry)` — the image that will actually be shared:
  the first real image field (cropped to the card size), or the generated card
  when there is none. This is what the share preview frames.
- `craft.herald.default()` — the static fallback URL (`defaultImagePath`).

## Share preview

Herald can show how an entry's share image will *sit* when the page is shared. In
the field-layout designer for any entry type, drag in the **Social Preview** UI
element — the entry editor then shows the resolved share image framed as it
appears on Facebook, X and Bluesky. Title / description / brand text are rendered
as skeleton placeholders, so the preview is purely a viewer for the image and
needs no SEO or brand configuration. The plugin settings screen shows the same
preview against a sample card, so palette / font / dimension changes can be
eyeballed after saving.

The preview markup lives in `templates/_preview/cards.twig` in the plugin.

## Customise a card per project

By default a card uses the entry title, the entry-type name as the kicker, and
(optionally) the first image field as a faint background. To change how any entry
maps to a card, handle the `DefineCard` event — this is where project-specific
rules (singles, homepage, author bylines, related-entry backgrounds, section
labels, …) live, keeping the plugin itself generic:

```php
use bensomething\herald\services\Generator;
use bensomething\herald\events\DefineCardEvent;
use yii\base\Event;

Event::on(Generator::class, Generator::EVENT_DEFINE_CARD, function (DefineCardEvent $e) {
    $entry = $e->entry;

    // Singles: use the brand as the kicker; homepage: brand as the title.
    if ($entry->section?->type === 'single') {
        $e->label = 'My Brand';
        if ($entry->section->handle === 'home') {
            $e->title = 'My Brand';
            $e->label = '';
        }
    }

    // e.g. a date line, or a background from a related entry:
    // $e->date = $entry->postDate?->format('F j, Y');
    // $e->background = $entry->relatedFestival.one()?.hero.one();
});
```

## Preview from the console

```bash
php craft herald/preview 123                     # a real entry's resolved card
php craft herald/preview --title="Hello world"   # a dummy card
php craft herald/preview --title="Hi" --image=/path/to/photo.jpg
```

Output goes to `storage/runtime/og-images-preview/`.

## Settings reference

| Setting | Default | Notes |
|---|---|---|
| `width` / `height` / `padding` | 1200 / 630 / 80 | Canvas |
| `maxLines` / `lineHeight` | 3 / 1.02 | Title wrapping |
| `cardElements` | *(see below)* | Per-text-element size / position / colour / uppercase / hide |
| `logoSize` / `logoPosition` / `logoPath` | 48 / `top-left` / *(none)* | Logo (PNG) |
| `backgroundColor` | `#000000` | Canvas colour |
| `displayFontPath` | *(bundled Inter Bold)* | Your licensed display font |
| `labelFontPath` / `dateFontPath` | *(bundled Inter)* | |
| `useImageAsBackground` / `backgroundOpacity` | `false` / `0.25` | |
| `imageFieldHandles` | `['seoImage','hero','featuredImage']` | "has image" + default background |
| `defaultImagePath` | `img/og-default.png` | `craft.herald.default()` |

### `cardElements`

One row per **text** element, in a fixed order — **Label, Date, Title** — each
with `size`, `position`, `color`, `uppercase` and `hide`. Edit them as a grid on
the settings screen, or in `config/herald.php`:

```php
'cardElements' => [
    ['size' => 28,  'position' => 'top-left',    'color' => '#a3a3a3', 'uppercase' => false, 'hide' => false], // Label
    ['size' => 28,  'position' => 'top-right',   'color' => '#a3a3a3', 'uppercase' => false, 'hide' => false], // Date
    ['size' => 116, 'position' => 'bottom-left', 'color' => '#ffffff', 'uppercase' => true,  'hide' => false], // Title
],
```

`size` is the font size; `position` is one of the nine anchors (`top`/`middle`/`bottom`
× `left`/`center`/`right`); `hide` drops the element. The logo has its own
`logoSize` / `logoPosition` / `logoPath`. The settings-page preview updates live
from the fields before you save.

## Licence

Proprietary. Bundled Inter fonts are SIL OFL 1.1 (see `src/resources/fonts/OFL.txt`).
