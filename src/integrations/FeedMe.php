<?php

namespace bensomething\placard\integrations;

use craft\base\ElementInterface;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\services\Process;
use WeakMap;
use yii\base\Event;

/**
 * Tells which saves come from a Feed Me import, so they skip rendering on save as
 * bulk resaves do. An import can save thousands of entries, each queueing a render.
 *
 * Feed Me marks no save as its own, so this notes each element it's about to save.
 * The note goes when the element does, so nothing outlives the import, even in a
 * queue worker that runs other jobs after it.
 */
class FeedMe
{
    /** @var WeakMap<ElementInterface, true>|null */
    private static ?WeakMap $importing = null;

    public static function register(): void
    {
        self::$importing = new WeakMap();

        // A class-level handler, so it needs nothing from Feed Me until Feed Me fires it.
        Event::on(
            Process::class,
            Process::EVENT_STEP_BEFORE_ELEMENT_SAVE,
            static function(FeedProcessEvent $event): void {
                self::markImporting($event->element);
            }
        );
    }

    /** Notes that `$element` is being saved by an import. */
    public static function markImporting(mixed $element): void
    {
        if ($element instanceof ElementInterface) {
            self::$importing ??= new WeakMap();
            self::$importing[$element] = true;
        }
    }

    /**
     * Whether `$element` is being saved by an import. Its saves in other sites are
     * copies, which point back at the element the import saved.
     */
    public static function isImporting(ElementInterface $element): bool
    {
        if (self::$importing === null) {
            return false;
        }

        for ($e = $element; $e !== null; $e = $e->propagatingFrom) {
            if (isset(self::$importing[$e])) {
                return true;
            }
        }

        return false;
    }
}
