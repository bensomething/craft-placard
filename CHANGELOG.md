# Release Notes for Placard

## Unreleased

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
