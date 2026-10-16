<?php

namespace bensomething\placard\web\twig;

use bensomething\placard\Plugin;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

/**
 * `craft.placard`. Typical usage:
 *
 *   {% set ogImage = craft.placard.shareImageUrl(entry ?? null) %}
 *   {% if ogImage %}<meta property="og:image" content="{{ ogImage }}">{% endif %}
 */
class PlacardVariable
{
    /** The generated card, cache-busted when the entry or its settings change. */
    public function url(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->cardUrl($entry);
    }

    /** The static fallback image, for pages with no entry. Null when `defaultImagePath` isn't set. */
    public function default(): ?string
    {
        $path = trim(Plugin::getInstance()->getSettings()->defaultImagePath);

        return $path !== '' ? UrlHelper::siteUrl($path) : null;
    }

    /**
     * The Social Image field's choice, else the first configured image field's, else the card.
     * Anything that isn't an entry gets default(). Safe to call on any value.
     */
    public function shareImageUrl(mixed $entry = null): ?string
    {
        if (!$entry instanceof Entry) {
            return $this->default();
        }

        return Plugin::getInstance()->getGenerator()->shareImageUrl($entry);
    }

    /**
     * As shareImageUrl(), but it also previews drafts and disabled entries to logged-in
     * users who can view them. For CP templates, like a field layout's Template element.
     */
    public function previewImageUrl(mixed $entry = null): ?string
    {
        if (!$entry instanceof Entry || !$entry->id) {
            return $this->default();
        }

        return Plugin::getInstance()->getGenerator()->previewImageUrl($entry);
    }

    /** Whether the entry shares its own image rather than the card. Safe to call on any value. */
    public function entryHasImage(mixed $entry): bool
    {
        return $entry instanceof Entry && Plugin::getInstance()->getGenerator()->shareImage($entry) !== null;
    }
}
