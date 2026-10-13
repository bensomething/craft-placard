<?php

namespace bensomething\placard\web\twig;

use bensomething\placard\Plugin;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

/**
 * `craft.placard`. Typical usage:
 *
 *   <meta property="og:image" content="{{ entry is defined
 *       ? craft.placard.shareImageUrl(entry)
 *       : craft.placard.default() }}">
 */
class PlacardVariable
{
    /** The generated card, cache-busted when the entry or its settings change. */
    public function url(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->cardUrl($entry);
    }

    /** The static fallback card, for pages with no entry. */
    public function default(): string
    {
        return UrlHelper::siteUrl(Plugin::getInstance()->getSettings()->defaultImagePath);
    }

    /** The Social Image field's choice, else the first configured image field's, else the card. */
    public function shareImageUrl(Entry $entry): string
    {
        return Plugin::getInstance()->getGenerator()->shareImageUrl($entry);
    }

    /** Whether the entry shares its own image rather than the card. Safe to call on any value. */
    public function entryHasImage(mixed $entry): bool
    {
        return $entry instanceof Entry && Plugin::getInstance()->getGenerator()->shareImage($entry) !== null;
    }
}
