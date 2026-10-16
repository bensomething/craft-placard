# Release Notes for Placard

## Unreleased

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
