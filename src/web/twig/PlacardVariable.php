<?php

namespace bensomething\placard\web\twig;

use bensomething\placard\Plugin;
use Craft;
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
    public function cardUrl(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->cardUrl($entry);
    }

    /** The static fallback image, for pages with no entry. Null when `defaultImagePath` isn't set. */
    public function defaultImageUrl(): ?string
    {
        $path = Plugin::getInstance()->getSettings()->defaultImage();

        return $path !== '' ? UrlHelper::siteUrl($path) : null;
    }

    /** @deprecated in 1.0.0-beta.16. Use {@see cardUrl()}. */
    public function url(Entry $entry): string
    {
        Craft::$app->getDeprecator()->log('placard.url', '`craft.placard.url()` is deprecated. Use `craft.placard.cardUrl()` instead.');

        return $this->cardUrl($entry);
    }

    /** @deprecated in 1.0.0-beta.16. Use {@see defaultImageUrl()}. */
    public function default(): ?string
    {
        Craft::$app->getDeprecator()->log('placard.default', '`craft.placard.default()` is deprecated. Use `craft.placard.defaultImageUrl()` instead.');

        return $this->defaultImageUrl();
    }

    /**
     * The Social Image field's choice, else the first configured image field's, else the card.
     * Anything that isn't an entry gets defaultImageUrl(). Safe to call on any value.
     */
    public function shareImageUrl(mixed $entry = null): ?string
    {
        if (!$entry instanceof Entry) {
            return $this->defaultImageUrl();
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
            return $this->defaultImageUrl();
        }

        return Plugin::getInstance()->getGenerator()->previewImageUrl($entry);
    }

    /** Whether the entry shares its own image rather than the card. Safe to call on any value. */
    public function entryHasImage(mixed $entry): bool
    {
        return $entry instanceof Entry && Plugin::getInstance()->getGenerator()->shareImage($entry) !== null;
    }
}
