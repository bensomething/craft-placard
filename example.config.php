<?php

/**
 * Copy to your project's `config/placard.php`. Supports Craft's multi-environment format.
 */

return [
    // Layout. position: top, middle, bottom or between. align: left, center or right.
    'position' => 'bottom',
    // What's drawn, from the top: label, title, description, meta, logo (a row of its
    // own) or logoLabel (the logo beside the label). An array groups items, and between
    // spaces groups apart, keeping each together: [['logoLabel'], ['title', 'meta']].
    // Items left out aren't drawn.
    'layout' => ['logoLabel', 'title', 'description', 'meta'],
    'align' => 'left',
    // caps: placed by cap height to last baseline. ink: by what the letters reach.
    'textEdges' => 'caps',
    // Inset for the text in px, as CSS shorthand: 80, [60, 100] or [60, 100, 80, 100].
    'padding' => 80,
    // Space below each element in px. An element's own gap overrides it.
    'gap' => 24,

    // Elements, in stack order. text is an object template rendered against the entry,
    // e.g. '{summary ?: seoDescription}'. A string sets just the text, false hides it.
    // Sizes are px on the 1200×630 card. lineHeight is a multiple of the size.
    // colorField takes the colour from a field on the entry, falling back to color.
    // fontPath and googleFont override the font below. gap and align override gap and align.
    'label' => [
        'text' => '{type.name}',
        'show' => true,
        'size' => 28,
        'lineHeight' => 1.02,
        'maxLines' => 1,
        'uppercase' => false,
        'color' => '#a3a3a3',
        'colorField' => '',
        'fontPath' => '',
        'googleFont' => '',
        'gap' => null,
        'align' => null,
    ],
    'title' => [
        'text' => '{title}',
        'size' => 116,
        'lineHeight' => 1.02,
        'maxLines' => 3,
        'uppercase' => true,
        'color' => '#ffffff',
        'fontPath' => '@root/src/fonts/MyDisplay.otf',
    ],
    'description' => [
        'text' => '',
        'size' => 32,
        'lineHeight' => 1.02,
        'maxLines' => 2,
        'color' => '#a3a3a3',
    ],
    'meta' => [
        'text' => '',
        'size' => 28,
        'color' => '#a3a3a3',
    ],

    // Every element's font, unless it sets its own: a TTF, OTF, WOFF or WOFF2 file, or a
    // Google Fonts family ('Fraunces', or 'Fraunces:700' for one weight throughout),
    // downloaded once. fontPath wins over googleFont. Both empty use the bundled Inter.
    'fontPath' => '',
    'googleFont' => '',

    // Checked in order for the share image of entries with no Social Image field.
    'shareImageFields' => ['seoImage', 'hero', 'featuredImage'],

    // Drawn beside the label, or on its own row with 'logo' in layout. Its width follows
    // its proportions. align applies on its own row; beside the label, it follows the
    // label's. A string sets just the path, false hides it.
    'logo' => [
        'path' => '@webroot/img/logo.png',
        'height' => 48,
        'show' => true,
        'align' => null,
    ],

    // colorField takes the colour from a field on the entry, falling back to color.
    // The image is imageField's, then the share image (if useShareImage), then imagePath.
    // The share image is the Social Image field's pick, else the first in shareImageFields.
    'background' => [
        'color' => '#000000',
        'colorField' => '',
        'imageField' => '',
        'useShareImage' => false,
        'imagePath' => '',
        'fit' => 'cover', // or contain
        'padding' => 0,
        'radius' => 0,
        'grayscale' => false,
        'blur' => 0,
        'opacity' => 0.25,
        // normal, multiply, screen, overlay, darken, lighten, color-dodge, color-burn,
        // hard-light, soft-light, difference or exclusion.
        'blendMode' => 'normal',
    ],

    // Widths in px, as CSS shorthand like padding: [8, 0, 0, 0] is a bar along the top.
    // The text sits inside the border.
    'border' => [
        'width' => 0,
        'color' => '#ffffff',
        'colorField' => '',
    ],

    // craft.placard.defaultImageUrl(), for pages with no entry, such as 'img/og-default.png'.
    'defaultImagePath' => '',

    // 'png', 'jpeg', or 'auto' for JPEG with a background image and PNG otherwise.
    'format' => 'auto',
    'jpegQuality' => 85,

    // Gives SEOmatic and SEOmate each entry's share image. false to pass it yourself.
    'seoPlugins' => true,

    // Overrides by handle. Entry types win over sections.
    'sections' => [
        // 'artists' => [
        //     'position' => 'middle',
        //     'background' => ['colorField' => 'colour', 'useShareImage' => true],
        //     'border' => ['color' => '#33bbdd'],
        // ],
    ],
    'entryTypes' => [
        // 'featuredArtist' => ['meta' => false, 'title' => ['color' => '#ffcc00']],
    ],

    // Overrides editors choose per entry in the Social Image field, applied after
    // sections and entry types. name labels the style, else the handle is used.
    'styles' => [
        // 'light' => [
        //     'name' => 'Light',
        //     'background' => '#ffffff',
        //     'title' => ['color' => '#111111'],
        // ],
    ],
];
