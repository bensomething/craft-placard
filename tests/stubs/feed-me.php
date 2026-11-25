<?php

/**
 * Feed Me classes Placard refers to, for PHPStan. Feed Me isn't a dev dependency.
 * Scanned, never loaded.
 */

namespace craft\feedme\events {
    class FeedProcessEvent extends \yii\base\Event
    {
        public mixed $element = null;
    }
}

namespace craft\feedme\services {
    class Process extends \craft\base\Component
    {
        public const EVENT_STEP_BEFORE_ELEMENT_SAVE = 'onStepBeforeElementSave';
    }
}
