<?php

namespace bensomething\placard\services;

use Craft;
use craft\helpers\FileHelper;
use GuzzleHttp\Exception\BadResponseException;
use yii\base\Component;

/**
 * Downloads Google Fonts families for cards, once per family and weight, into
 * storage/runtime/placard/fonts/. Google gives a client it doesn't recognise one
 * whole TTF per weight, rather than browser WOFF2 subsets, and serves a variable
 * family as a static instance at the weight asked for.
 */
class GoogleFonts extends Component
{
    /** `Family` or `Family:weight`, as in `Fraunces:700`. */
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 ]*(:\d{1,4})?$/';

    /** How long a failed download is remembered before it's tried again, in seconds. */
    private const RETRY_AFTER = 300;

    /** Families that couldn't be downloaded for a reason that may pass, keyed by family and weight. */
    private array $pending = [];

    /**
     * The font's file, downloading it on first use, or null when Google can't supply
     * it. Without a weight in the font, `$weight` is tried, then the regular weight.
     */
    public function path(string $font, int $weight): ?string
    {
        $parsed = self::parse($font);
        if ($parsed === null) {
            Craft::warning("Placard couldn't use the Google font \"$font\". Give a family, optionally with a weight, as in \"Fraunces:700\"", __METHOD__);

            return null;
        }

        [$family, $explicit] = $parsed;
        foreach ($explicit !== null ? [$explicit] : array_unique([$weight, 400]) as $candidate) {
            $path = $this->download($family, $candidate);
            if ($path !== null || isset($this->pending["$family:$candidate"])) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Whether path() came back empty for a reason that may pass, like a network error,
     * so a card drawn without the font shouldn't be kept.
     */
    public function isPending(string $font, int $weight): bool
    {
        $parsed = self::parse($font);
        if ($parsed === null) {
            return false;
        }

        [$family, $explicit] = $parsed;
        foreach ($explicit !== null ? [$explicit] : array_unique([$weight, 400]) as $candidate) {
            if (isset($this->pending["$family:$candidate"])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The family and weight in a font, or null when it isn't one.
     *
     * @return array{0: string, 1: int|null}|null
     */
    public static function parse(string $font): ?array
    {
        $font = trim(preg_replace('/\s+/', ' ', $font));
        if (!preg_match(self::PATTERN, $font)) {
            return null;
        }

        $parts = explode(':', $font, 2);

        return [trim($parts[0]), isset($parts[1]) ? (int)$parts[1] : null];
    }

    /** The first TTF in a css2 stylesheet, from Google's own font host. */
    public static function fontUrl(string $css): ?string
    {
        return preg_match('#url\((https://fonts\.gstatic\.com/[^)\s]+\.ttf)\)#', $css, $match) ? $match[1] : null;
    }

    public function directory(): string
    {
        return Craft::$app->getPath()->getRuntimePath() . '/placard/fonts';
    }

    // -- Downloads

    private function download(string $family, int $weight): ?string
    {
        $key = "$family:$weight";
        $file = $this->directory() . '/' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $family)) . "-$weight.ttf";
        if (is_file($file)) {
            return $file;
        }

        // Remembered, so a family Google lacks, or an outage, doesn't hold up every render.
        $cache = Craft::$app->getCache();
        $failed = $cache->get("placard:google-font:$key");
        if ($failed !== false) {
            if ($failed === 'pending') {
                $this->pending[$key] = true;
            }

            return null;
        }

        try {
            $client = Craft::createGuzzleClient(['timeout' => 10, 'connect_timeout' => 5]);
            $css = (string)$client->get('https://fonts.googleapis.com/css2', [
                'query' => ['family' => "$family:wght@$weight"],
                'headers' => ['User-Agent' => 'Placard'],
            ])->getBody();

            $url = self::fontUrl($css);
            if ($url === null) {
                // Families with axes besides weight aren't offered as one TTF.
                return $this->fail($key, "Google Fonts has no single font file for $family at weight $weight. Download it and set fontPath instead", false);
            }

            $blob = (string)$client->get($url)->getBody();
            if (!$this->isFont($blob)) {
                return $this->fail($key, "Google Fonts sent something other than a font for $family at weight $weight", true);
            }

            FileHelper::createDirectory(dirname($file));
            // Written aside and renamed in, so a concurrent render never reads a partial file.
            $tmp = $file . '.' . uniqid('', true) . '.tmp';
            if (file_put_contents($tmp, $blob) === false || !rename($tmp, $file)) {
                @unlink($tmp);

                return $this->fail($key, "Placard couldn't save the Google font $family to $file", true);
            }

            return $file;
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();

            // A 400 means the family, or that weight of it, doesn't exist.
            return $this->fail($key, "Google Fonts couldn't supply $family at weight $weight (HTTP $status)", $status >= 500 || $status === 429);
        } catch (\Throwable $e) {
            return $this->fail($key, "Placard couldn't download the Google font $family: {$e->getMessage()}", true);
        }
    }

    private function fail(string $key, string $message, bool $pending): null
    {
        Craft::warning($message, __METHOD__);
        Craft::$app->getCache()->set("placard:google-font:$key", $pending ? 'pending' : 'missing', self::RETRY_AFTER);
        if ($pending) {
            $this->pending[$key] = true;
        }

        return null;
    }

    /** Whether the contents start like a TrueType or OpenType font. */
    private function isFont(string $blob): bool
    {
        return str_starts_with($blob, "\x00\x01\x00\x00") || str_starts_with($blob, 'OTTO') || str_starts_with($blob, 'true');
    }
}
