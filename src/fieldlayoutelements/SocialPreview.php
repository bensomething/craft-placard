<?php

namespace bensomething\placard\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\fieldlayoutelements\BaseUiElement;
use craft\helpers\Html;
use craft\web\View;

/**
 * A field-layout UI element that renders the social share preview for the entry
 * being edited. Drag "Social Preview" into any entry type's field layout to show,
 * inline in the editor, how that entry's share image will sit on Facebook / X /
 * Bluesky.
 *
 * The preview is a skeleton (title / description / brand text are placeholders),
 * so it needs no SEO or brand data — only the resolved share image. Registered in
 * {@see \bensomething\placard\Plugin::init()}.
 */
class SocialPreview extends BaseUiElement
{
    protected function selectorLabel(): string
    {
        return Craft::t('placard', 'Social Preview');
    }

    protected function selectorIcon(): ?string
    {
        return 'image';
    }

    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        // No image can be resolved until the entry exists (the generated card route
        // is keyed by entry id), so prompt to save on brand-new entries.
        if (!$element instanceof Entry || !$element->id) {
            return Html::tag(
                'p',
                Craft::t('placard', 'Save the entry to preview how it will appear when shared.'),
                ['class' => 'light'],
            );
        }

        return Craft::$app->getView()->renderTemplate(
            'placard/_preview/cards',
            ['entry' => $element],
            View::TEMPLATE_MODE_CP,
        );
    }
}
