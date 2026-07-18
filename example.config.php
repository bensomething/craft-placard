<?php

/**
 * Example config — copy to your project's `config/herald.php`.
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

    // Title
    'titleFontSize' => 116,
    'maxLines' => 3,
    'uppercaseTitle' => true,

    // Palette
    'backgroundColor' => '#000000',
    'titleColor' => '#ffffff',
    'metaColor' => '#a3a3a3',

    // Assets — point the display font + logo at YOUR project's (licensed) assets.
    // Leave displayFontPath empty to fall back to the bundled Inter Bold.
    'displayFontPath' => '@root/src/fonts/MyDisplay.otf',
    'logoPath' => '@webroot/img/logo.png',

    // Which asset fields mean "this entry already has a share image".
    'imageFieldHandles' => ['seoImage', 'hero', 'featuredImage'],
    'useImageAsBackground' => false,

    // craft.herald.default()
    'defaultImagePath' => 'img/og-default.png',
];
