<?php

namespace bensomething\placard\jobs;

use bensomething\placard\Plugin;
use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;

/**
 * Renders an entry's card into the cache, so the first crawler to ask for it
 * doesn't wait on the render.
 */
class RenderCard extends BaseJob
{
    public int $entryId;
    public int $siteId;

    public function execute($queue): void
    {
        $entry = Entry::find()->id($this->entryId)->siteId($this->siteId)->one();
        $generator = Plugin::getInstance()->getGenerator();

        // It may have changed since it was queued.
        if (!$entry || !$generator->servesCard($entry)) {
            return;
        }

        $generator->cachedCard($generator->defineCard($entry));
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('placard', 'Rendering social card');
    }
}
