<?php

namespace bensomething\placard;

use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\Settings;
use bensomething\placard\services\Generator;
use bensomething\placard\web\twig\PlacardVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Placard plugin.
 *
 * Generates social / OG cards with Imagick for entries that have no share image,
 * and exposes a `craft.placard` variable plus an `og/<entryId>.png` route that
 * streams the generated PNG (cached under storage/runtime).
 *
 * The layout is fixed. The brand (palette, fonts, logo, background) is set in
 * `config/placard.php`, and how a card's title / label / description / meta /
 * background are derived from an entry via the {@see Generator::EVENT_DEFINE_CARD}
 * event. Nothing here is tied to a particular site.
 *
 * @property-read Generator $generator
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    // The settings page is a preview of the card, not a form — see getSettingsResponse().
    public bool $hasCpSettings = true;
    public bool $hasReadOnlyCpSettings = true;

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
                $event->rules['og/<entryId:\d+>.png'] = 'placard/image/generate';
            }
        );

        // craft.placard.* helpers for templates.
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('placard', PlacardVariable::class);
            }
        );

        // "Social Image" field: choose, per entry, which image is shared, with the
        // card preview alongside.
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types[] = SocialImage::class;
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

    /**
     * Placard is configured in config/placard.php, so its settings page only shows
     * the card that config produces. It's a CP screen rather than Craft's settings
     * form: there's nothing to save, and a Save button would copy the config file's
     * values into project config.
     */
    public function getSettingsResponse(): mixed
    {
        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        return $controller->asCpScreen()
            ->title($this->name)
            ->crumbs([
                ['label' => Craft::t('app', 'Settings'), 'url' => UrlHelper::cpUrl('settings')],
                ['label' => Craft::t('app', 'Plugins'), 'url' => UrlHelper::cpUrl('settings/plugins')],
            ])
            // Re-renders the preview in place (see _settings.twig), so a config change
            // can be checked without reloading the page.
            ->additionalButtonsHtml(Html::button(
                Html::tag('div', Craft::t('placard', 'Refresh'), ['class' => 'label'])
                . Html::tag('div', '', ['class' => ['spinner', 'spinner-absolute']]),
                [
                    'type' => 'button',
                    'id' => 'placard-refresh',
                    'class' => 'btn',
                    'data' => ['icon' => 'refresh'],
                ],
            ))
            // The card sits in the details sidebar, as entry pages show theirs.
            ->metaSidebarTemplate('placard/_sidebar')
            ->contentTemplate('placard/_settings', [
                'settings' => $this->getSettings(),
                'hasConfigFile' => Craft::$app->getConfig()->getConfigFromFile('placard') !== [],
                'previewOptions' => $this->previewOptions(),
                'unknownHandles' => $this->unknownHandles(),
            ]);
    }

    /**
     * The settings preview's dropdown: one option per section / entry type pair that
     * has overrides, plus entry types with overrides that no section uses (nested
     * entries). The entries to preview are fetched when one's picked.
     *
     * @return array<int, array{label:string, section:string, type:string}>
     */
    private function previewOptions(): array
    {
        $settings = $this->getSettings();
        $entries = Craft::$app->getEntries();
        $options = [];
        $typesInSections = [];

        foreach ($entries->getAllSections() as $section) {
            foreach ($section->getEntryTypes() as $type) {
                $typesInSections[$type->handle] = true;
                if (!isset($settings->sections[$section->handle]) && !isset($settings->entryTypes[$type->handle])) {
                    continue;
                }

                $options[] = [
                    'label' => count($section->getEntryTypes()) > 1 ? "$section->name › $type->name" : $section->name,
                    'section' => $section->handle,
                    'type' => $type->handle,
                ];
            }
        }

        foreach (array_keys($settings->entryTypes) as $handle) {
            $type = $entries->getEntryTypeByHandle((string)$handle);
            if ($type && !isset($typesInSections[$type->handle])) {
                $options[] = [
                    'label' => $type->name,
                    'section' => '',
                    'type' => $type->handle,
                ];
            }
        }

        return $options;
    }

    /**
     * Override handles in the config that don't match a section or entry type. They
     * do nothing, so a typo would otherwise fail silently.
     *
     * @return string[]
     */
    private function unknownHandles(): array
    {
        $settings = $this->getSettings();
        $entries = Craft::$app->getEntries();
        $unknown = [];

        foreach (array_keys($settings->sections) as $handle) {
            if (!$entries->getSectionByHandle((string)$handle)) {
                $unknown[] = "sections.$handle";
            }
        }
        foreach (array_keys($settings->entryTypes) as $handle) {
            if (!$entries->getEntryTypeByHandle((string)$handle)) {
                $unknown[] = "entryTypes.$handle";
            }
        }

        return $unknown;
    }

    /** Nothing on the page is editable, so it reads the same with admin changes off. */
    public function getReadOnlySettingsResponse(): mixed
    {
        return $this->getSettingsResponse();
    }
}
