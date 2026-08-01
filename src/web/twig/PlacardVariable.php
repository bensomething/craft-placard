<?php

namespace bensomething\placard\web\twig;

use bensomething\placard\Plugin;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

/**
 * `craft.placard` template helpers.
 *
 * Typical usage in your <head>:
 *
 *   {% set og = craft.placard.entryHasImage(entry)
 *       ? entry.seoImage.one().url          {# your own share image #}
 *       : craft.placard.url(entry) %}        {# generated card #}
 *   <meta property="og:image" content="{{ og ?? craft.placard.default() }}">
 */
class PlacardVariable
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
     * The image that will actually be shared for an entry: the first real asset in
     * the configured image fields (cropped to card dimensions), or — when there is
     * none — the generated card. This is what the social preview frames display.
     */
    public function shareImageUrl(Entry $entry): string
    {
        $settings = Plugin::getInstance()->getSettings();

        foreach ($settings->imageFieldHandles as $handle) {
            try {
                $value = $entry->getFieldValue($handle);
            } catch (\Throwable) {
                continue;
            }

            $asset = $value instanceof AssetQuery ? $value->one() : $value;
            if ($asset instanceof Asset) {
                $url = $asset->getUrl(['width' => $settings->width, 'height' => $settings->height, 'mode' => 'crop']);
                if ($url) {
                    return $url;
                }
            }
        }

        return $this->url($entry);
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
