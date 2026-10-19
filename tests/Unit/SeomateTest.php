<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\integrations\Seomate;
use PHPUnit\Framework\TestCase;

/**
 * The share image goes into the `seomate` variable as a template would put it,
 * keeping whatever the template already set there.
 */
class SeomateTest extends TestCase
{
    private const URL = 'https://example.com/og/1.png?v=abc';

    private const TRANSFORM_MAP = [
        'image' => ['width' => 1200, 'height' => 630, 'format' => 'jpg'],
        'og:image' => ['width' => 1200, 'height' => 630, 'format' => 'jpg'],
        'twitter:image' => ['width' => 1200, 'height' => 600, 'format' => 'jpg'],
    ];

    public function testSetsEveryImageKey(): void
    {
        $seomate = Seomate::withImage([], self::URL, self::TRANSFORM_MAP);

        foreach (Seomate::IMAGE_KEYS as $key) {
            $this->assertSame(self::URL, $seomate['meta'][$key]);
        }
    }

    public function testTakesTheImageKeysOutOfTheTransformMap(): void
    {
        $map = self::TRANSFORM_MAP + ['custom:image' => ['width' => 400]];

        $seomate = Seomate::withImage([], self::URL, $map);

        $this->assertSame(['custom:image' => ['width' => 400]], $seomate['config']['imageTransformMap']);
    }

    public function testKeepsWhatTheTemplateSet(): void
    {
        $override = [
            'element' => 'kept',
            'meta' => ['description' => 'Hello', 'og:image' => 'https://example.com/own.jpg'],
            'config' => ['includeSitenameInTitle' => false],
        ];

        $seomate = Seomate::withImage($override, self::URL, self::TRANSFORM_MAP);

        $this->assertSame('kept', $seomate['element']);
        $this->assertSame('Hello', $seomate['meta']['description']);
        $this->assertSame('https://example.com/own.jpg', $seomate['meta']['og:image']);
        $this->assertSame(self::URL, $seomate['meta']['image']);
        $this->assertFalse($seomate['config']['includeSitenameInTitle']);
    }

    public function testStartsFromTheTemplatesTransformMap(): void
    {
        $override = ['config' => ['imageTransformMap' => ['image' => ['width' => 800], 'other' => ['width' => 10]]]];

        $seomate = Seomate::withImage($override, self::URL, self::TRANSFORM_MAP);

        $this->assertSame(['other' => ['width' => 10]], $seomate['config']['imageTransformMap']);
    }
}
