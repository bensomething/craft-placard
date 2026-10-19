<?php

namespace bensomething\placard\integrations;

use bensomething\placard\Plugin;
use Craft;
use craft\elements\Entry;
use vaersaagod\seomate\SEOMate as SeomatePlugin;

/**
 * Gives SEOmate each entry's share image, so templates need nothing. SEOmate's field
 * profiles only read Assets fields (closures included), so they can't read the Social
 * Image field or the generated card.
 *
 * SEOmate has no events, so this adds to the `seomate` template variable from its own
 * `seomateMeta` hook, as a template would. Craft loads plugins in handle order, so
 * `placard` registers its hook before `seomate` and runs first.
 */
class Seomate
{
    /** The meta SEOmate fills from an image field, all set so a profile's own fields can't win. */
    public const IMAGE_KEYS = ['image', 'og:image', 'twitter:image'];

    public static function register(): void
    {
        Craft::$app->getView()->hook('seomateMeta', static function(array &$context): string {
            if (!class_exists(SeomatePlugin::class) || ($seomate = SeomatePlugin::getInstance()) === null) {
                return '';
            }

            $override = $context['seomate'] ?? [];
            $entry = $override['element']
                ?? $context['seomatePreviewElement']
                ?? Craft::$app->getUrlManager()->getMatchedElement();

            // A template that sets its own image keeps it.
            if (!$entry instanceof Entry || isset($override['meta']['image'])) {
                return '';
            }

            $context['seomate'] = self::withImage(
                $override,
                Plugin::getInstance()->getGenerator()->shareImageUrl($entry),
                $seomate->getSettings()->imageTransformMap,
            );

            return '';
        });
    }

    /**
     * Adds the image to a `seomate` variable, keeping what a template already set.
     * SEOmate transforms images as assets and drops a URL it's given, so the image
     * keys come out of `imageTransformMap`. Cards are already the right size.
     *
     * @param array<string, mixed> $override
     * @param array<string, mixed> $transformMap SEOmate's, used when the template sets none
     * @return array<string, mixed>
     */
    public static function withImage(array $override, string $url, array $transformMap): array
    {
        $transformMap = $override['config']['imageTransformMap'] ?? $transformMap;

        foreach (self::IMAGE_KEYS as $key) {
            $override['meta'][$key] ??= $url;
            unset($transformMap[$key]);
        }

        $override['config']['imageTransformMap'] = $transformMap;

        return $override;
    }
}
