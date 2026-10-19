<?php

namespace bensomething\placard\integrations;

use bensomething\placard\models\Settings;
use bensomething\placard\Plugin;
use craft\elements\Entry;
use nystudio107\seomatic\helpers\DynamicMeta;
use nystudio107\seomatic\Seomatic as SeomaticPlugin;
use yii\base\Event;

/**
 * Gives SEOmatic each entry's share image as its SEO, Open Graph and X image, so
 * templates need nothing. SEOmatic's image settings only list Assets fields, so
 * they can't read the Social Image field or the generated card.
 *
 * Runs as SEOmatic builds a page's meta, which it caches per page and clears when
 * the entry's saved. The share image's URL changes on save too, so they agree.
 */
class Seomatic
{
    public static function register(): void
    {
        // class_exists() rather than ::class, which would fatally autoload the
        // constant's class on sites without SEOmatic.
        if (!class_exists(DynamicMeta::class)) {
            return;
        }

        Event::on(DynamicMeta::class, DynamicMeta::EVENT_ADD_DYNAMIC_META, static function(): void {
            $entry = SeomaticPlugin::$matchedElement;
            $vars = SeomaticPlugin::$plugin?->metaContainers->metaGlobalVars;
            if (!$entry instanceof Entry || $vars === null) {
                return;
            }

            $url = Plugin::getInstance()->getGenerator()->shareImageUrl($entry);

            // All three, as a section can point Open Graph and X at fields of their own.
            foreach (['seo', 'og', 'twitter'] as $prefix) {
                $vars->{$prefix . 'Image'} = $url;
                $vars->{$prefix . 'ImageWidth'} = (string)Settings::WIDTH;
                $vars->{$prefix . 'ImageHeight'} = (string)Settings::HEIGHT;
            }
        });
    }
}
