# Release Notes for Placard

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
