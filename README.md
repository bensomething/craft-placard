# Placard

Generates configurable social / OG cards with Imagick for Craft entries that have no share image. Cards are rendered on demand and cached under `storage/runtime/og-images`.

A card is four text elements — **label**, **title**, **description** and **meta** (a small line for a date, byline, read time, venue…) — in one stack over a solid background, with an optional logo beside the label and an optional background image. You choose where the stack sits and set the brand (colours, fonts, logo, background) in `config/placard.php`.

## Requirements

- Craft CMS 5.10 or later
- PHP 8.2 or later
- The `imagick` PHP extension

## Install

Install from the Craft Plugin Store, or with Composer:

```bash
composer require bensomething/craft-placard
php craft plugin/install placard
```

## Configure

Copy `example.config.php` to your project's `config/placard.php` and edit it there — it's version-controlled and env-aware.

**Settings → Plugins → Placard** shows the card your config produces, with placeholder text and a safe-area toggle. It's a preview, not a form: change the config and reload the page.

### Per section and entry type

`sections` and `entryTypes` change settings for some entries, keyed by handle. List only what differs; the rest comes from the base settings. An entry type's overrides win over its section's.

```php
return [
    'position' => 'bottom',

    'sections' => [
        'artists' => [
            'position' => 'middle',
            'align' => 'center',
            'titleUppercase' => false,
            'backgroundColorField' => 'colour',
            'useImageAsBackground' => true,
            'backgroundFit' => 'contain',
            'backgroundOpacity' => 1,
        ],
    ],
    'entryTypes' => [
        'featuredArtist' => ['showMeta' => false],
    ],
];
```

Every setting can be overridden except `defaultImagePath`, which is for pages with no entry. The settings page has a **Preview** dropdown with each section / entry type pair that has overrides; it renders that pair's most recent entry, or placeholder text if there isn't one. It also lists any handles that don't match a section or entry type, since those do nothing.

For anything a handle can't express, `DefineCard` handlers get the card's resolved settings as `$e->settings`, and can change them for that card alone. Font / logo / image paths accept Craft aliases (`@root`, `@webroot`) and env vars.

**Canvas size.** Fixed at **1200×630**. That's 1.91:1 — the shape every platform crops an `og:image` from — and the size they all recommend, matched to the ~600px-wide box link previews actually render in. Neither the shape nor the resolution is configurable on purpose: one image serves every platform, and anything larger is bytes nobody sees.

⚠️ **Fonts.** No commercial font is bundled. The plugin ships with **Inter** (SIL OFL) in three weights as the default for each text element. Point `displayFontPath` at your own licensed display font per project.

## Use in templates

```twig
<meta property="og:image" content="{{ entry is defined
    ? craft.placard.shareImageUrl(entry)
    : craft.placard.default() }}">
```

- `craft.placard.shareImageUrl(entry)` — the image the entry shares: whatever its **Social Image** field chooses, else the first image in the configured `imageFieldHandles` (cropped to the card size), else the generated card.
- `craft.placard.entryHasImage(entry)` — true when that's one of the entry's own images rather than the generated card.
- `craft.placard.url(entry)` — the generated card's URL (`/og/<id>.png`), whatever the entry would share. Cache-busted when the entry or its card settings change.
- `craft.placard.default()` — the static fallback URL (`defaultImagePath`), for pages with no entry.

## Social Image field

Add a **Social Image** field to an entry type to let editors choose what each entry shares, with a preview of it underneath:

- **Automatic** (the default) — the first image in `imageFieldHandles`, else the generated card. It's what entries without the field get.
- **Generated card**
- **One of the entry's image fields** — every asset field in the entry type's layout is listed by name.
- **Custom image** — pick a one-off image in the field itself.

The preview follows the form as you edit, before anything's saved: change the choice, or pick a different image in the field it points at, and it updates. A choice that turns out to have no image (an empty field, a deleted asset) falls back to Automatic, so an entry always shares something.

The preview is the image at the card's real aspect ratio, with a **Preview** button that opens it in a lightbox. It deliberately isn't a mock-up of anyone's card. Every consumer of an `og:image` crops it to its own box, and not one box per platform either — feed vs. compact card, mobile vs. desktop, and Slack / WhatsApp / iMessage crop tighter than the social networks do. Those boxes change without notice, so a per-platform mock-up is stale the moment someone ships a redesign.

Instead the settings page has a **Show safe area** switch: it shades the margin that some crop somewhere will eat, leaving the region every crop keeps. That's the central 1080×600 of a 1200×630 canvas — 5% in on each side, 2.4% top and bottom — expressed proportionally. Placard's fixed padding keeps its own text inside it; it's mostly useful for checking your own share images.

The preview markup lives in `templates/_preview/cards.twig`, and the field's in `templates/_field/input.twig`.

## What the card says

Each text element is an **object template** rendered against the entry — the same syntax as an entry type's title format — set in `config/placard.php`:

```php
'title' => '{title}',
'label' => '{type.name}',
'description' => '{summary ?: seoDescription}',
'meta' => '{postDate|date("j F Y")}',
```

A template can pull a field (`{summary}`), be fixed text (`'label' => 'News'`), format a value (`{postDate|date("M Y")}`), fall back (`{a ?: b}`) or reach into a relation (`{author.one().fullName}`). HTML is stripped, so a rich text field works. By default the title is the entry title and the label its entry type's name; **description and meta are empty**, so they don't appear until you give them a template. Like any setting, the templates can differ per section or entry type, e.g. `'sections' => ['news' => ['meta' => '{postDate|date("j F Y")} · {author.fullName}']]`.

A template that errors (a misspelt field, say) is logged and leaves just that element blank. `showDescription` and friends still turn an element off whatever its template says.

## Customise a card in PHP

For rules a template can't express, handle the `DefineCard` event. It fires after the templates have rendered, so a handler sees their output and can change any of it — this is where project-specific logic (singles, homepage, related-entry backgrounds, …) lives, keeping the plugin itself generic:

```php
use bensomething\placard\services\Generator;
use bensomething\placard\events\DefineCardEvent;
use yii\base\Event;

Event::on(Generator::class, Generator::EVENT_DEFINE_CARD, function (DefineCardEvent $e) {
    $entry = $e->entry;

    // Singles: use the brand as the label; homepage: brand as the title.
    if ($entry->section?->type === 'single') {
        $e->label = 'My Brand';
        if ($entry->section->handle === 'home') {
            $e->title = 'My Brand';
            $e->label = '';
        }
    }

    // e.g. a background from a related entry:
    // $e->background = $entry->relatedFestival->one()?->hero->one();
});
```

The event carries `title`, `label`, `description`, `meta`, `background` and `backgroundColor`, plus `settings`: the card's resolved settings (section and entry type overrides applied), which a handler can change for that card alone, e.g. `$e->settings->position = 'top'`. Setting one to an empty string (or `null`, for the nullable ones) drops that element from the card.

## Preview from the console

```bash
php craft placard/preview 123                     # a real entry's resolved card
php craft placard/preview --title="Hello world"   # a dummy card
php craft placard/preview --title="Hi" --image=/path/to/photo.jpg
```

Takes `--title`, `--label`, `--description`, `--meta` and `--image`. Output goes to `storage/runtime/og-images-preview/`.

## Settings reference

| Setting | Default | Notes |
|---|---|---|
| `position` | `bottom` | Where the stack sits: `top`, `middle`, `bottom`, or `between` (first and last pushed to the edges, the rest shared out) |
| `textEdges` | `caps` | What `position` places: `caps` (cap height to last baseline, so text sits at the same height on every card, with descenders hanging) or `ink` (exactly what each card's letters reach) |
| `align` | `left` | `left`, `center` or `right`. The logo stays beside the label either way |
| `titleMaxLines` | 3 | Most lines the title wraps to before it's clamped with an ellipsis |
| `showLabel` / `showTitle` / `showDescription` / `showMeta` / `showLogo` | `true` | Turn an element off. The stack closes up around it. Hiding the label keeps the logo |
| `titleUppercase` | `true` | Set the title in capitals, or as written |
| `titleSize` | 116 | Title font size, in pixels on the 1200×630 card. Long titles wrap at this size and clamp at `titleMaxLines`; they don't shrink |
| `title` / `label` / `description` / `meta` | `{title}` / `{type.name}` / *(empty)* / *(empty)* | What each element says, as an object template rendered against the entry — see *What the card says* |
| `backgroundColor` | `#000000` | Canvas colour, and the fallback for `backgroundColorField` |
| `backgroundColorField` | *(none)* | Handle of a Color field (or a text field holding a hex value) on the entry to take the canvas colour from |
| `labelColor` / `titleColor` / `descriptionColor` / `metaColor` | `#a3a3a3` / `#ffffff` / `#a3a3a3` / `#a3a3a3` | Text colour per element |
| `displayFontPath` | *(bundled Inter Bold)* | The title font. Point it at your licensed display font |
| `labelFontPath` / `descriptionFontPath` / `metaFontPath` | *(bundled Inter)* | One font per text element |
| `logoPath` | *(none)* | Logo (PNG), drawn beside the label |
| `backgroundImageField` | *(none)* | Handle of an asset field on the entry to take the background image from |
| `useImageAsBackground` | `false` | Use the entry's share image (from `imageFieldHandles`) when `backgroundImageField` gives none |
| `backgroundImagePath` | *(none)* | A fixed background image, used when the entry gives none |
| `backgroundFit` | `cover` | `cover` crops the image to fill the card; `contain` fits it whole, centred on the canvas colour |
| `backgroundPadding` | 0 | Inset around the image, in pixels. Both fits work inside the inset box, with the canvas colour in the margin |
| `backgroundRadius` | 0 | Corner radius for the image, in pixels |
| `backgroundGreyscale` / `backgroundOpacity` | `false` / `0.25` | Drain its colour, and how strongly it shows (`1` for full strength) |
| `imageFieldHandles` | `['seoImage','hero','featuredImage']` | Image fields checked, in order, for the **Automatic** share image (and by `useImageAsBackground`) |
| `defaultImagePath` | `img/og-default.png` | `craft.placard.default()` |
| `sections` / `entryTypes` | `[]` | Overrides keyed by section / entry type handle — see *Per section and entry type* |

### The layout

Every element flows in one stack, in the order label, title, description, meta, placed by `position` and `align`. That's what makes it collision-proof: a headline that wraps to an extra line pushes what follows it down instead of landing on top of it.

| Element | Size | Max lines | Treatment |
|---|---|---|---|
| Label | 28 | 1 | — (logo sits beside it) |
| Title | 116 (`titleSize`) | 3 (`titleMaxLines`) | Uppercase (`titleUppercase`); balanced across lines |
| Description | 32 | 2 | — |
| Meta | 28 | 1 | — |

Long text is clamped with a trailing ellipsis. Padding is 80px, the gap between elements 24px, and the logo 48px tall. An element with no text, or turned off with its `show…` setting, is skipped, so the card just closes up around it.

## Licence

MIT. Bundled Inter fonts are SIL OFL 1.1 (see `src/resources/fonts/OFL.txt`).
