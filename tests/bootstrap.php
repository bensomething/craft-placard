<?php

// Unit tests run without a Craft app, so only Craft's static helpers are loaded.
$vendor = dirname(__DIR__) . '/vendor';
require $vendor . '/autoload.php';
require $vendor . '/yiisoft/yii2/Yii.php';

// Craft 6's `craft\` names are aliases of its own classes, which the Yii2 adapter
// registers as the app boots.
if (class_exists(CraftCms\Yii2Adapter\ClassAliases::class)) {
    (new CraftCms\Yii2Adapter\ClassAliases())->register();
}

// Craft 6's Craft::t() goes through its I18N facade, which needs an app. Translate
// as Yii does without one, as on Craft 5: the message as written, placeholders filled.
if (class_exists(CraftCms\Cms\Support\Facades\I18N::class)) {
    CraftCms\Cms\Support\Facades\I18N::swap(new class() {
        /** @param array<string, mixed> $params */
        public function translate(string $message, array $params = [], ?string $category = null, ?string $language = null): string
        {
            $placeholders = [];
            foreach ($params as $name => $value) {
                $placeholders['{' . $name . '}'] = (string)$value;
            }

            return strtr($message, $placeholders);
        }
    });
}

// Craft 6 moved the Craft class into the Yii2 adapter.
foreach (['/craftcms/cms/src/Craft.php', '/craftcms/yii2-adapter/legacy/Craft.php'] as $craft) {
    if (is_file($vendor . $craft)) {
        require $vendor . $craft;
        break;
    }
}
