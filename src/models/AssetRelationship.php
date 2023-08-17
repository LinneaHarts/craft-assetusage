<?php

namespace born05\assetusage\models;

use craft\base\Model;

class AssetRelationship extends Model
{
    public $id;
    public $sourceId;
    public $targetId;
    public $sourceSiteId;
    public $dateCreated;
    public $dateUpdated;

    public function rules(): array
    {
        return [
            [['id', 'sourceId', 'targetId', 'sourceSiteId'], 'required'],
            [['dateCreated', 'dateUpdated'], 'datetime', 'format' => 'php:Y-m-d H:i:s']
        ];
    }
}