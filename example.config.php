<?php

/**
 * Copy to your project's `config/placard.php`. Supports Craft's multi-environment format.
 */

return [
    // Layout. position: top, middle, bottom or between. align: left, center or right.
    'position' => 'bottom',
    'align' => 'left',
    'titleMaxLines' => 3,
    'titleUppercase' => true,
    'titleSize' => 116, // px on the 1200×630 card
    // caps: placed by cap height to last baseline. ink: by what the letters reach.
    'textEdges' => 'caps',
    // Space below each element in px. labelGap, titleGap and descriptionGap override
    // gap for one element; null uses gap.
    'gap' => 24,
    'labelGap' => null,
    'titleGap' => null,
    'descriptionGap' => null,

    // Elements
    'showLabel' => true,
    'showTitle' => true,
    'showDescription' => true,
    'showMeta' => true,
    'showLogo' => true,

    // Content, as object templates rendered against the entry, e.g. '{summary ?: seoDescription}'.
    'title' => '{title}',
    'label' => '{type.name}',
    'description' => '',
    'meta' => '',

    // Palette. Each *Field takes the colour from a field on the entry, falling back to the colour.
    'backgroundColor' => '#000000',
    'backgroundColorField' => '',
    'labelColor' => '#a3a3a3',
    'labelColorField' => '',
    'titleColor' => '#ffffff',
    'titleColorField' => '',
    'descriptionColor' => '#a3a3a3',
    'descriptionColorField' => '',
    'metaColor' => '#a3a3a3',
    'metaColorField' => '',

    // Fonts. fontPath sets every element, and each *FontPath overrides it for one.
    // Empty falls back to the bundled Inter.
    'fontPath' => '',
    'titleFontPath' => '@root/src/fonts/MyDisplay.otf',
    'labelFontPath' => '',
    'descriptionFontPath' => '',
    'metaFontPath' => '',

    'logoPath' => '@webroot/img/logo.png',

    // Checked in order for the share image of entries with no Social Image field.
    'imageFieldHandles' => ['seoImage', 'hero', 'featuredImage'],

    // Background image: the field, then the share image (if useImageAsBackground), then the path.
    'backgroundImageField' => '',
    'useImageAsBackground' => false,
    'backgroundImagePath' => '',
    'backgroundFit' => 'cover', // or contain
    'backgroundPadding' => 0,
    'backgroundRadius' => 0,
    'backgroundGrayscale' => false,
    'backgroundBlur' => 0,
    'backgroundOpacity' => 0.25, 
    'backgroundBlendMode' => 'normal', // normal, multiply, screen, overlay, darken, lighten, color-dodge, color-burn,hard-light, soft-light, difference or exclusion.

    // craft.placard.default()
    'defaultImagePath' => 'img/og-default.png',

    // Overrides by handle. Entry types win over sections.
    'sections' => [
        // 'artists' => [
        //     'position' => 'middle',
        //     'backgroundColorField' => 'colour',
        //     'useImageAsBackground' => true,
        // ],
    ],
    'entryTypes' => [
        // 'featuredArtist' => ['showMeta' => false],
    ],
];
