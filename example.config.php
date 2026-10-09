<?php

/**
 * Example config — copy to your project's `config/placard.php`.
 *
 * This file is where Placard is configured; its settings page only previews the
 * card it produces. Supports Craft's multi-environment format.
 */

return [
    // Layout. Label, title, description and meta flow in one stack, in that order.
    // position is top | middle | bottom | between (which pushes the first and last
    // to the edges and shares out the rest); align is left | center | right.
    'position' => 'bottom',
    'align' => 'left',
    // What the stack is placed by. caps: cap height to last baseline, so tall
    // letters and descenders hang outside and text sits at the same height on
    // every card. ink: exactly what each card's letters reach.
    'textEdges' => 'caps',
    // Most lines the title wraps to before it's clamped with an ellipsis.
    'titleMaxLines' => 3,
    // Set the title in capitals, or as written.
    'titleUppercase' => true,
    // Title font size, in pixels on the 1200×630 card.
    'titleSize' => 116,

    // Turn elements off. The stack closes up around anything hidden. Hiding the
    // label keeps the logo, on its own; showLogo hides that.
    'showLabel' => true,
    'showTitle' => true,
    'showDescription' => true,
    'showMeta' => true,
    'showLogo' => true,

    // What each element says: an object template rendered against the entry, as
    // in an entry type's title format. Fields, fixed text, formatting, fallbacks:
    // '{summary}', 'News', '{postDate|date("j F Y")}', '{summary ?: seoDescription}'.
    // HTML is stripped. Description and meta are empty, so hidden, until set.
    'title' => '{title}',
    'label' => '{type.name}',
    'description' => '',
    'meta' => '',

    // Palette
    'backgroundColor' => '#000000',
    // Take the canvas colour from a Color field on the entry instead, falling back
    // to backgroundColor when it's empty.
    'backgroundColorField' => '',
    'labelColor' => '#a3a3a3',
    'titleColor' => '#ffffff',
    'descriptionColor' => '#a3a3a3',
    'metaColor' => '#a3a3a3',

    // Fonts — point the display font at YOUR project's (licensed) font. Leave a
    // path empty to fall back to its bundled Inter weight.
    'displayFontPath' => '@root/src/fonts/MyDisplay.otf',
    'labelFontPath' => '',
    'descriptionFontPath' => '',
    'metaFontPath' => '',

    // Logo (PNG), drawn beside the label.
    'logoPath' => '@webroot/img/logo.png',

    // Image fields checked, in order, for the Automatic share image: what an entry
    // shares when its Social Image field is left on Automatic, or it has none.
    'imageFieldHandles' => ['seoImage', 'hero', 'featuredImage'],

    // Background image, from the first of these that gives one: an asset field on
    // the entry, the entry's share image (if useImageAsBackground is on), then one
    // fixed image.
    'backgroundImageField' => '',
    'useImageAsBackground' => false,
    'backgroundImagePath' => '',
    // cover crops the image to fill the card; contain fits it whole, centred on
    // the canvas colour.
    'backgroundFit' => 'cover',
    // Inset around the image (both fits work inside it, with the canvas colour
    // in the margin), and its corner radius, in pixels.
    'backgroundPadding' => 0,
    'backgroundRadius' => 0,
    'backgroundGreyscale' => false,
    'backgroundOpacity' => 0.25,

    // craft.placard.default()
    'defaultImagePath' => 'img/og-default.png',

    // Per-section and per-entry-type changes, applied over everything above. List
    // only what differs. Entry types win over sections. Any setting can be
    // overridden except defaultImagePath. The settings page can preview each one.
    'sections' => [
        // 'artists' => [
        //     'position' => 'middle',
        //     'align' => 'center',
        //     'titleUppercase' => false,
        //     'backgroundColorField' => 'colour',
        //     'useImageAsBackground' => true,
        //     'backgroundFit' => 'contain',
        //     'backgroundOpacity' => 1,
        // ],
    ],
    'entryTypes' => [
        // 'featuredArtist' => ['showMeta' => false],
    ],
];
