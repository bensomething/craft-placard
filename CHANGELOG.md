# Release Notes for Placard

## Unreleased

### Added
- The background's `padding` takes CSS shorthand like the main `padding`, as `[0, 0, 0, 600]` for an image on the right half. A single number works as before.

## 1.0.0-beta.15 - 2026-10-19

### Added
- `googleFont` setting, and a `googleFont` key for each element, to use a Google Fonts family, as `Fraunces` or `Fraunces:700`. Each family and weight is downloaded once into `storage/runtime/placard/fonts/`. Without a weight, each element takes Inter's: Bold title, Medium label, Regular description and meta.
- Utilities → Caches can clear the downloaded Google Fonts.

### Changed
- A font that can't be drawn falls back to Inter and logs why, instead of failing the card. That covers a missing or unreadable `fontPath`, a WOFF2 file on a server whose FreeType can't decode it, and a Google family that doesn't exist.

## 1.0.0-beta.14 - 2026-10-19

### Added
- Card previews show a spinner while the image loads, on the same grey as the empty Custom Image placeholder, so a card that's still rendering no longer looks like a blank card.

### Changed
- Requests for a card that isn't cached yet render it once. Others arriving meanwhile, like the burst of crawlers a newly shared entry draws, wait for that render instead of each running their own.

### Security
- Card URLs only serve entries with a URL of their own. Nested entries and entries in sections without URLs now 404, as their content may not be shown publicly, and stepping through entry IDs could make the server render a card for every entry.

## 1.0.0-beta.13 - 2026-10-19

### Added
- SEOmatic and SEOmate get each entry's share image (the Social Image field's choice, or the card) with nothing added to templates. SEOmatic gets it as the entry's SEO, Open Graph and X image. SEOmate gets it as `image`, `og:image` and `twitter:image`, unless a template sets `seomate.meta.image` itself. `seoPlugins` turns this off.

### Changed
- With SEOmatic, the image replaces the one its section settings choose, as the README's snippet did. Templates using the snippet keep working, and it can be removed.
- Both plugins cache each page's tags. After updating, clear SEOmatic's or SEOmate's caches (Utilities → Caches) for pages to show the new image before their entries are next saved.

### Fixed
- A card's URL now changes whenever the card does. Replacing a background image's file, moving its focal point, or a `DefineCard` handler or related entry changing the text changed the card but not its URL, so a CDN or crawler that had cached the URL kept the old card.

## 1.0.0-beta.12 - 2026-10-19

### Security
- Background images from asset fields are only read if they're JPEG, PNG, GIF, WebP or AVIF. Others, including SVG, TIFF and BMP, are skipped with a logged warning and the card falls back to `imagePath`. ImageMagick picks a reader from a file's contents, so an editor's upload could reach readers like SVG's.

### Changed
- The README recommends a PNG logo.

## 1.0.0-beta.11 - 2026-10-19

### Added
- `imagePathOpacity` background setting, for the `imagePath` image's opacity. Without it, a section's `opacity` for its image field also applied to the fallback image on entries with no image.

### Fixed
- The Social Image field listed fields with a hidden label in the entry's layout as *__blank__*, such as *__blank__ → Photo*. They now show the field's name.

## 1.0.0-beta.10 - 2026-10-18

### Added
- `format` setting, for the file format of served cards. The default, `auto`, saves cards with a background image as JPEG, several times smaller than PNG for photos, and others as PNG. `jpegQuality` sets their quality, 85 by default.
- Placard's cached cards can be cleared from Utilities → Caches.

### Changed
- When an entry's card re-renders, its older cached versions are deleted, so `storage/runtime/og-images` no longer keeps a copy for every change to the entry or the config. Cards cached before this update stay until the cache is cleared.
- Cached cards are kept per site, so a multi-site entry's cards don't overwrite each other.

## 1.0.0-beta.9 - 2026-10-18

### Changed
- Background images keep their own transparency, and `opacity` scales it, so a transparent PNG works as a pattern over the colour. Opaque images, such as photos, render as before.
- The settings page's Preview only shows an entry dropdown when there's more than one entry to pick.

## 1.0.0-beta.8 - 2026-10-18

### Added
- `layout` setting, for the order elements are drawn in and how they're grouped. `between` spaces groups apart and keeps each together, so `[['logoLabel'], ['title', 'description']]` puts the logo and label at the top and the title and description at the bottom. `logo` draws the logo on a row of its own, and `logoLabel` beside the label, as before. Unset, cards draw as they did.
- `meta.gap`, now that meta can be moved from last position.
- `align` for each element and the logo, over the card's `align`. A logo beside the label follows the label's.

## 1.0.0-beta.7 - 2026-10-16

### Added
- `styles` setting, for named overrides editors choose between per entry. The Social Image field shows a Style dropdown when there are any, and its settings choose which it offers. Styles apply after section and entry type overrides, and the settings page previews each one.
- `craft.placard.previewImageUrl(entry)`, as `shareImageUrl()` but with a card that also renders drafts for logged-in users, for CP templates like a field layout's Template element.

### Changed
- `background.useShareImage` uses the custom image or image field picked in the entry's Social Image field. With the card picked, or no field, it still uses the first image in `imageFieldHandles`.

## 1.0.0-beta.6 - 2026-10-15

### Added
- `label`, `title`, `description` and `meta` each take an array of their settings: `text`, `show`, `size`, `lineHeight`, `maxLines`, `uppercase`, `color`, `colorField`, `fontPath` and `gap`. A string still sets the text, and `false` hides the element. In `sections` and `entryTypes`, an element's array changes only the keys it names.
- Line height for each element, as a multiple of the size. It defaults to 1.02, as before, with the extra space now shared above and below each line, as in CSS.
- `maxLines` and `uppercase` for the label, description and meta, which were fixed at 1, 2 and 1 lines and never uppercase.
- `logo`, `background` and `border` take arrays of their settings in the same way, e.g. `'border' => ['width' => [8, 0, 0, 0], 'color' => '#f5b400']`. A single value sets the logo's path, the background's colour or the border's widths, and `'logo' => false` hides the logo.

### Deprecated
- The flat element, logo, background and border settings in config (`titleSize`, `showMeta`, `logoHeight`, `backgroundBlur`, `borderColor` and the like). They still work, but use the arrays. `useImageAsBackground` is `background.useShareImage`. The properties stay on the settings model, for `DefineCardEvent`.

## 1.0.0-beta.5 - 2026-10-14

### Added
- `imageFieldHandles`, `backgroundImageField` and the `*ColorField` settings take nested paths like `seo.seoImage`, to read a field inside a Matrix or Content Block field. The first nested entry with a value is used.
- The Social Image field lists asset fields inside the entry's Matrix and Content Block fields.
- `border` setting, for border widths as one value or CSS-style per side, with `borderColor` and `borderColorField`. The text sits inside the border.

### Fixed
- The logo is centred on the label's capitals rather than its line box, which sat the label low beside it.

## 1.0.0-beta.4 - 2026-10-14

### Added
- `labelSize`, `descriptionSize` and `metaSize` settings, alongside `titleSize`.
- `logoHeight` setting. The logo's width follows its proportions.
- `logoPath` can be an SVG. It's drawn at the logo's size, so it stays sharp, on a transparent background.

## 1.0.0-beta.3 - 2026-10-14

### Added
- `padding` setting, for the inset around the text, as one value or CSS-style per side.
- `gap` setting, for the space between elements.
- `labelGap`, `titleGap` and `descriptionGap` settings, for the space below one element.

## 1.0.0-beta.2 - 2026-10-14

### Added
- `fontPath` setting, to set the font for every element at once. The per-element font settings override it.
- `titleFontPath` setting.
- `backgroundBlur` setting.
- `backgroundGrayscale` setting, replacing `backgroundGreyscale`.
- `backgroundBlendMode` setting, to blend the background image with the canvas colour (`multiply`, `screen`, `overlay` and others).

### Changed
- `craft.placard.shareImageUrl()` accepts any value, returning `default()` for anything that isn't an entry.
- `craft.placard.default()` returns null when `defaultImagePath` isn't set.

### Deprecated
- `displayFontPath`. Use `titleFontPath`.
- `backgroundGreyscale`. Use `backgroundGrayscale`.

## 1.0.0-beta.1 - 2026-10-13

### Added
- Initial release.
- Social cards generated with Imagick, served from `/og/<entryId>.png`.
- Configured in `config/placard.php`, with overrides per section and entry type.
- Card text from object templates, and colours and background image from entry fields.
- `DefineCard` event to customise cards in PHP.
- Social Image field, to choose per entry which image is shared, with a live preview.
- `craft.placard` template helpers.
- Settings page preview and `php craft placard/preview` console command.
