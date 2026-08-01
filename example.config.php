<?php

/**
 * Example config — copy to your project's `config/placard.php`.
 *
 * Values here override the plugin's CP settings and are the recommended place to
 * configure the plugin per project (they're version-controlled and env-aware).
 * Supports Craft's multi-environment format.
 */

return [
    // Canvas
    'width' => 1200,
    'height' => 630,
    'padding' => 80,
    'lineHeight' => 1.02,

    // One row per text element, in this fixed order: Label, Title, Description,
    // Date. `display` is normal | uppercase | hidden. `maxLines` clamps with a
    // trailing ellipsis, and only applies to the elements that wrap — Title and
    // Description. Each element is anchored independently, so pick positions that
    // don't collide.
    'cardElements' => [
        ['size' => 28,  'maxLines' => 1, 'color' => '#a3a3a3', 'display' => 'normal',    'position' => 'top-left'],
        ['size' => 116, 'maxLines' => 3, 'color' => '#ffffff', 'display' => 'uppercase', 'position' => 'bottom-left'],
        ['size' => 32,  'maxLines' => 2, 'color' => '#a3a3a3', 'display' => 'hidden',    'position' => 'middle-left'],
        ['size' => 28,  'maxLines' => 1, 'color' => '#a3a3a3', 'display' => 'normal',    'position' => 'top-right'],
    ],

    // Logo
    'logoSize' => 48,
    'logoPosition' => 'top-left',

    // Palette
    'backgroundColor' => '#000000',

    // Assets — point the display font + logo at YOUR project's (licensed) assets.
    // Leave a font path empty to fall back to its bundled Inter weight.
    'displayFontPath' => '@root/src/fonts/MyDisplay.otf',
    'labelFontPath' => '',
    'descriptionFontPath' => '',
    'dateFontPath' => '',
    'logoPath' => '@webroot/img/logo.png',

    // Which asset fields mean "this entry already has a share image".
    'imageFieldHandles' => ['seoImage', 'hero', 'featuredImage'],
    'useImageAsBackground' => false,
    'backgroundOpacity' => 0.25,

    // craft.placard.default()
    'defaultImagePath' => 'img/og-default.png',
];
