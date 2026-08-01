# Release Notes for Placard

## Unreleased

### Added
- Initial scaffold: generates configurable social / OG cards with Imagick for Craft entries that have no share image.
- Social share preview: a **Social Preview** field-layout UI element (drag into any entry type) that previews how the entry's share image sits on Facebook / X / Bluesky, plus a live sample preview alongside the plugin settings. Skeleton text — it frames the image only, so it needs no SEO/brand data.
- `craft.placard.shareImageUrl(entry)` — the image that will actually be shared (first real image field, cropped, else the generated card).
- `cardElements` — an editable table (Label / Title / Description / Date) for each text element's size, max lines, colour, display (normal / uppercase / hidden) and position (9 anchors), replacing the separate title/meta/placement/colour settings. The logo has its own `logoSize` / `logoPosition` / `logoPath`. The settings-page preview updates live from the (unsaved) fields as you change them.
- **Description** text element — an optional standfirst, wrapped and clamped to its own max lines. It has no default source: populate it per project from `Generator::EVENT_DEFINE_CARD`, and give it a font with `descriptionFontPath`.
- Each text element gets its own font path, set as a row in the settings screen's **Fonts** table (`labelFontPath` / `displayFontPath` / `descriptionFontPath` / `dateFontPath`), each falling back to a bundled Inter weight.
- The settings screen groups related settings into tables rather than stacks of fields, with per-setting instructions in info icons. The tables scroll horizontally on narrow panes.
- Generated entry cards now include a settings fingerprint in their cache key, so design changes (placement, fonts, palette, dimensions) refresh existing cards instead of serving stale ones.
