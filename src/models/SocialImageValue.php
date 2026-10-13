<?php

namespace bensomething\placard\models;

use craft\base\Model;

/**
 * A source that gives no image (an empty field, a deleted asset) shares the
 * generated card, so an entry always shares what the field's preview shows.
 */
class SocialImageValue extends Model
{
    public const CARD = 'card';
    public const CUSTOM = 'custom';
    public const FIELD_PREFIX = 'field:';

    /** `card`, `custom` or `field:<handle>`. */
    public string $source = self::CARD;

    /** Only used by the `custom` source. */
    public ?int $assetId = null;

    public function getFieldHandle(): ?string
    {
        return str_starts_with($this->source, self::FIELD_PREFIX)
            ? substr($this->source, strlen(self::FIELD_PREFIX)) ?: null
            : null;
    }
}
