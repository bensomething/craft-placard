<?php

namespace bensomething\placard;

use bensomething\placard\fields\SocialImage;
use bensomething\placard\models\Settings;
use bensomething\placard\services\Generator;
use bensomething\placard\web\twig\PlacardVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\DefineRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\Fields;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * @property-read Generator $generator
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;
    public bool $hasReadOnlyCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'generator' => Generator::class,
        ]);

        // The .png suffix keeps the URL recognisable as an image to crawlers.
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function(RegisterUrlRulesEvent $event): void {
                $event->rules['og/<entryId:\d+>.png'] = 'placard/image/generate';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('placard', PlacardVariable::class);
            }
        );

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = SocialImage::class;
            }
        );

        // isMultiInstance() only stops the same field going in twice. This stops two
        // different ones, as only the first would be used (see Generator::socialImageValue()).
        Event::on(
            FieldLayout::class,
            Model::EVENT_DEFINE_RULES,
            static function(DefineRulesEvent $event): void {
                /** @var FieldLayout $layout */
                $layout = $event->sender;
                $event->rules[] = [['customFields'], static function() use ($layout): void {
                    $count = count(array_filter(
                        $layout->getCustomFields(),
                        static fn($field) => $field instanceof SocialImage,
                    ));
                    if ($count > 1) {
                        $layout->addError('customFields', Craft::t('placard', 'Only one Social Image field can be added to a field layout.'));
                    }
                }, 'skipOnEmpty' => false];
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
     * A preview-only CP screen, not Craft's settings form. A Save button would copy
     * config/placard.php's values into project config.
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
            // Re-renders the preview in place (see _settings.twig).
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
            ->metaSidebarTemplate('placard/_sidebar')
            ->contentTemplate('placard/_settings', [
                'settings' => $this->getSettings(),
                'hasConfigFile' => Craft::$app->getConfig()->getConfigFromFile('placard') !== [],
                'previewOptions' => $this->previewOptions(),
                'unknownHandles' => $this->unknownHandles(),
            ]);
    }

    /**
     * Section/entry type pairs with overrides, plus overridden entry types no section
     * uses (nested entries).
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
     * Override handles that match no section or entry type, so typos don't fail silently.
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

    public function getReadOnlySettingsResponse(): mixed
    {
        return $this->getSettingsResponse();
    }
}
