<?php

namespace bensomething\herald\web\twig;

use bensomething\herald\Plugin;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

/**
 * `craft.herald` template helpers.
 *
 * Typical usage in your <head>:
 *
 *   {% set og = craft.herald.entryHasImage(entry)
 *       ? entry.seoImage.one().url          {# your own share image #}
 *       : craft.herald.url(entry) %}        {# generated card #}
 *   <meta property="og:image" content="{{ og ?? craft.herald.default() }}">
 */
class HeraldVariable
{
    /**
     * Absolute URL to the generated card for an entry, cache-busted on update.
     */
    public function url(Entry $entry): string
    {
        $token = $entry->dateUpdated?->getTimestamp() ?? 0;

        return UrlHelper::siteUrl('og/' . $entry->id . '.png', ['v' => $token]);
    }

    /**
     * Absolute URL to the configured static brand fallback card.
     */
    public function default(): string
    {
        return UrlHelper::siteUrl(Plugin::getInstance()->getSettings()->defaultImagePath);
    }

    /**
     * Whether the element already has a usable share image (in any of the
     * configured image field handles), so the generated / default fallback can be
     * skipped. Safe to call on any value — missing fields are ignored.
     */
    public function entryHasImage(mixed $entry): bool
    {
        if (!$entry instanceof Element) {
            return false;
        }

        foreach (Plugin::getInstance()->getSettings()->imageFieldHandles as $handle) {
            try {
                $value = $entry->getFieldValue($handle);
            } catch (\Throwable) {
                continue;
            }

            if ($value instanceof AssetQuery) {
                if ($value->exists()) {
                    return true;
                }
            } elseif ($value instanceof Asset || $value) {
                return true;
            }
        }

        return false;
    }
}
