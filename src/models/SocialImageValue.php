<?php

namespace bensomething\placard\models;

use craft\base\Model;

/**
 * The value of a {@see \bensomething\placard\fields\SocialImage} field: which image an
 * entry shares.
 *
 * `source` is one of:
 * - `auto` — the first image in the configured `imageFieldHandles`, else the
 *   generated card (what Placard does with no field at all)
 * - `card` — always the generated card
 * - `field:<handle>` — the first image in that asset field
 * - `custom` — {@see $assetId}, picked in the field itself
 *
 * A source that gives no image (an empty field, a deleted asset) falls back to
 * `auto`, so an entry always shares something.
 */
class SocialImageValue extends Model
{
    public const AUTO = 'auto';
    public const CARD = 'card';
    public const CUSTOM = 'custom';
    public const FIELD_PREFIX = 'field:';

    public string $source = self::AUTO;

    /** The picked asset, for the `custom` source. */
    public ?int $assetId = null;

    /** The asset field handle, for a `field:<handle>` source; else null. */
    public function getFieldHandle(): ?string
    {
        return str_starts_with($this->source, self::FIELD_PREFIX)
            ? substr($this->source, strlen(self::FIELD_PREFIX)) ?: null
            : null;
    }
}
