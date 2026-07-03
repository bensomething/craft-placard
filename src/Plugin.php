<?php

namespace bensmith\ogimage;

use bensmith\ogimage\models\Settings;
use bensmith\ogimage\services\Generator;
use bensmith\ogimage\web\twig\OgImageVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * OG Image plugin.
 *
 * Generates social / OG cards with Imagick for entries that have no share image,
 * and exposes a `craft.ogImage` variable plus an `og/<entryId>.png` route that
 * streams the generated PNG (cached under storage/runtime).
 *
 * Everything project-specific — dimensions, fonts, logo, palette, and how a card's
 * title / label / date / background are derived from an entry — is configurable via
 * plugin settings, a `config/og-image.php` file, or the {@see Generator::EVENT_DEFINE_CARD}
 * event. Nothing here is tied to a particular site.
 *
 * @property-read Generator $generator
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'generator' => Generator::class,
        ]);

        // Route: /og/<entryId>.png -> streams the generated card. The .png suffix
        // keeps the URL recognisable as an image to crawlers / scrapers.
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event): void {
                $event->rules['og/<entryId:\d+>.png'] = 'og-image/image/generate';
            }
        );

        // craft.ogImage.* helpers for templates.
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('ogImage', OgImageVariable::class);
            }
        );
    }

    public function getGenerator(): Generator
    {
        return $this->get('generator');
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('og-image/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
