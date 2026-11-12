<?php

/**
 * Craft 6 classes Placard refers to, for PHPStan, which analyses against Craft 5.
 * Scanned, never loaded.
 *
 * On Craft 6 the Yii2 adapter's `craft\elements\Asset` extends the native class.
 * Here it's declared the other way round, so the native class has Craft 5's methods.
 */

namespace CraftCms\Cms\Asset\Data {
    readonly class AssetTransformResult
    {
        public function __construct(public string $url)
        {
        }
    }
}

namespace CraftCms\Cms\Asset\Elements {
    use CraftCms\Cms\Asset\Data\AssetTransformResult;

    class Asset extends \craft\elements\Asset
    {
        public function transform(mixed $definition): AssetTransformResult
        {
            return new AssetTransformResult('');
        }
    }
}
