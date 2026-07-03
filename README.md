# OG Image (Craft plugin)

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
    { "type": "vcs", "url": "git@github.com:YOURNAME/craft-og-image.git" }
  ]
}
```

Then:

```bash
composer require bensomething/craft-og-image
php craft plugin/install og-image
```

## Configure

Copy `example.config.php` to your project's `config/og-image.php` and edit it —
those values override the CP settings and are the recommended place to configure
the plugin per project. Font / logo / config paths accept Craft aliases
(`@root`, `@webroot`) and env vars.

⚠️ **Fonts.** No commercial font is bundled. The plugin ships with **Inter**
(SIL OFL) as the default body + fallback display font. Point `displayFontPath` at
your own licensed display font per project.

## Use in templates

```twig
{% set og = craft.ogImage.entryHasImage(entry)
    ? entry.seoImage.one().url          {# your own share image #}
    : craft.ogImage.url(entry) %}        {# generated card #}

<meta property="og:image" content="{{ og ?? craft.ogImage.default() }}">
```

- `craft.ogImage.url(entry)` — generated card URL (`/og/<id>.png`), cache-busted.
- `craft.ogImage.entryHasImage(entry)` — true if the entry has an image in any of
  the configured `imageFieldHandles`.
- `craft.ogImage.default()` — the static fallback URL (`defaultImagePath`).

## Customise a card per project

By default a card uses the entry title, the entry-type name as the kicker, and
(optionally) the first image field as a faint background. To change how any entry
maps to a card, handle the `DefineCard` event — this is where project-specific
rules (singles, homepage, author bylines, related-entry backgrounds, section
labels, …) live, keeping the plugin itself generic:

```php
use bensomething\ogimage\services\Generator;
use bensomething\ogimage\events\DefineCardEvent;
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
php craft og-image/preview 123                     # a real entry's resolved card
php craft og-image/preview --title="Hello world"   # a dummy card
php craft og-image/preview --title="Hi" --image=/path/to/photo.jpg
```

Output goes to `storage/runtime/og-images-preview/`.

## Settings reference

| Setting | Default | Notes |
|---|---|---|
| `width` / `height` / `padding` | 1200 / 630 / 80 | Canvas |
| `titleFontSize` / `maxLines` / `lineHeight` | 116 / 3 / 1.02 | Title fitting |
| `uppercaseTitle` | `true` | |
| `metaFontSize` / `logoHeight` | 28 / 48 | Top row |
| `backgroundColor` / `titleColor` / `metaColor` | `#000000` / `#ffffff` / `#a3a3a3` | |
| `displayFontPath` | *(bundled Inter Bold)* | Your licensed display font |
| `labelFontPath` / `dateFontPath` | *(bundled Inter)* | |
| `logoPath` | *(none)* | PNG, top-left |
| `useImageAsBackground` / `backgroundOpacity` | `false` / `0.25` | |
| `imageFieldHandles` | `['seoImage','hero','featuredImage']` | "has image" + default background |
| `defaultImagePath` | `img/og-default.png` | `craft.ogImage.default()` |

## Licence

Proprietary. Bundled Inter fonts are SIL OFL 1.1 (see `src/resources/fonts/OFL.txt`).
