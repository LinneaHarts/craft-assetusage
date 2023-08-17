<?php

namespace born05\assetusage\records;

use craft\db\ActiveRecord;

class AssetRelationship extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%assetusage_assetrelations}}';
    }
}