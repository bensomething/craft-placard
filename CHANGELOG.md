# Release Notes for Herald

## Unreleased

### Added
- Initial scaffold: generates configurable social / OG cards with Imagick for Craft entries that have no share image.
- Social share preview: a **Social Preview** field-layout UI element (drag into any entry type) that previews how the entry's share image sits on Facebook / X / Bluesky, plus a live sample preview alongside the plugin settings. Skeleton text — it frames the image only, so it needs no SEO/brand data.
- `craft.herald.shareImageUrl(entry)` — the image that will actually be shared (first real image field, cropped, else the generated card).
- `cardElements` — an editable table (Label / Date / Title) for each text element's size, position (9 anchors), colour, uppercase and hide, replacing the separate title/meta/placement/colour settings. The logo has its own `logoSize` / `logoPosition` / `logoPath`. The settings-page preview updates live from the (unsaved) fields as you change them.
- Generated entry cards now include a settings fingerprint in their cache key, so design changes (placement, fonts, palette, dimensions) refresh existing cards instead of serving stale ones.
