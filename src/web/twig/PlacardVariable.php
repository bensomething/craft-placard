<?php

namespace bensomething\placard\web\twig;

use bensomething\placard\Plugin;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

/**
 * `craft.placard` template helpers.
 *
 * Typical usage in your <head>:
 *
 *   <meta property="og:image" content="{{ entry is defined
 *       ? craft.placard.shareImageUrl(entry)
 *       : craft.placard.default() }}">
 */
class PlacardVariable
{
    /**
     * Absolute URL to the generated card for an entry, cache-busted when the entry
     * or its card settings change.
     */
    public function url(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->cardUrl($entry);
    }

    /**
     * Absolute URL to the configured static brand fallback card.
     */
    public function default(): string
    {
        return UrlHelper::siteUrl(Plugin::getInstance()->getSettings()->defaultImagePath);
    }

    /**
     * The image that will actually be shared for an entry, for its `og:image`:
     * whatever its Social Image field chooses, else the first image in the
     * configured image fields (cropped to the card's size), else the generated card.
     */
    public function shareImageUrl(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->shareImageUrl($entry);
    }

    /**
     * Whether the entry shares an image of its own rather than the generated card
     * — see {@see shareImageUrl()}. Safe to call on any value.
     */
    public function entryHasImage(mixed $entry): bool
    {
        return $entry instanceof Entry && Plugin::getInstance()->getGenerator()->shareImage($entry) !== null;
    }
}
