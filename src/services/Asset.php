<?php

namespace born05\assetusage\services;

use DOMDocument;
use DateTime;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset as AssetElement;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ElementHelper;
use craft\helpers\Db;
use born05\assetusage\Plugin;
use born05\assetusage\records\AssetRelationship as AssetRelRecord;
use born05\assetusage\models\AssetRelationship as AssetRelModel;

use verbb\supertable\elements\SuperTableBlockElement;

use lenz\linkfield\models\Link;

class Asset extends Component
{

    public function storeAllRedactorAssets(): void
    {

        //echo 'Hi World!' . PHP_EOL;

        $fields = Craft::$app->fields->getAllFields();

        $assetRecords = (new AssetRelRecord())->find()->all();
        foreach ($assetRecords as $record) {
            $record->delete();
        }

        foreach($fields as $field) {
            //echo $field->handle . " " . get_class($field) . PHP_EOL;
            if (get_class($field) == 'craft\\redactor\\Field') {
                $elements = Entry::find()->search($field->handle . ':*')->all();
                foreach ($elements as $element) {
                    $redactorElement = $element->getFieldValue($field->handle);
                    $this->_addRedactorRelation($element, $redactorElement);
                }
            /*} else if (get_class($field) == 'typedlinkfield\\fields\\LinkField' || get_class($field) == 'lenz\\linkfield\\fields\\LinkField') {
                //echo $field->handle . " " . get_class($field) . PHP_EOL;
                //echo json_encode($field) . PHP_EOL;
                $elements = Entry::find()->search($field->handle . ':*')->all();
                //echo "Found " . count($elements) . " elements" . PHP_EOL;
                foreach ($elements as $element) {
                    $linkElement = $element->getFieldValue($field->handle);
                    //echo $linkElement->getType() . PHP_EOL;
                    //echo "linkElement " . json_encode($linkElement) . PHP_EOL;
                    if ($linkElement) {
                        if ($linkElement->getType() == 'asset' && $linkElement->hasElement()) {
                            //echo $linkElement->getElement()->title . PHP_EOL;
                            $this->_addAssetRelation($element, $linkElement->getElement());
                        }
                    }
                }*/
            } else if (get_class($field) == 'craft\\fields\\Matrix') {
                $this->_extractAssetsFromMatrix($field->handle);
            } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                $this->_extractAssetsFromSupertable($field->id, false, $field->handle);
            }
        }
    }

    public function storeRedactorAssets($element): void
    {
        $assetRecords = (new AssetRelRecord())->find()->where(["sourceId" => $element->id])->all();
        Craft::info('Element ID ' . $element->id . ' asset records', __METHOD__);
        foreach ($assetRecords as $record) {
            $record->delete();
        }
        $fieldLayoutFields = $element->getFieldLayout()->getCustomFields();
        foreach ($fieldLayoutFields as $fieldLayoutField) {
            $field = Craft::$app->fields->getFieldById($fieldLayoutField->id);
            if (get_class($field) == 'craft\\redactor\\Field') {
                $redactorElement = $element->getFieldValue($field->handle);
                $this->_addRedactorRelation($element, $redactorElement);
            } else if (get_class($field) == 'craft\\fields\\Matrix') {
                $this->_extractAssetsFromMatrix($field->handle, false, $element);
            } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                $this->_extractAssetsFromSupertable($field->id, false, $field->handle, $element);
            }

        } 

    }

    private function _addRedactorRelation($element, $redactorElement) {
        if (preg_match('/src=/', $redactorElement)) {
            //echo 'Found asset: ' . $redactorElement . PHP_EOL;
            $imgArray = $this->_extractFilenames($redactorElement);
            foreach($imgArray as $filename) {  
                $img = \craft\elements\Asset::find()->filename($filename)->one();
                if ($img) {
                    $this->_addAssetRelation($element, $img);
                }
            }
        }
    }

    private function _addAssetRelation($element, $asset) {
        $model = new AssetRelModel();
        $record = new AssetRelRecord();
        $now = new DateTime();
        $model->dateCreated = $now->format('Y-m-d H:i:s');
        $model->dateUpdated = $now->format('Y-m-d H:i:s');

        $model->sourceId = $element->id;
        $model->targetId = $asset->id;
        $model->sourceSiteId = $element->siteId;
        $record->setAttributes($model->getAttributes(), false);
        $record->save();
    }

    private function _extractAssetsFromMatrix(string $handle, bool $nested = false, $element = null, $blocks = null) {
        //echo 'Extracting assets from matrix field ' . $handle . PHP_EOL;
        if ($element) {
            $elements = [$element];
        } else if (!$nested) {
            $fieldId = Craft::$app->getFields()->getFieldByHandle($handle)->id;

            $fieldLayouts = (new Query())
                ->select(['layoutId'])
                ->from(['{{%fieldlayoutfields}}'])
                ->where(Db::parseParam('fieldId', $fieldId))
                ->column();
            if (empty($fieldLayouts)) {
                return;
            }
            //echo 'Found ' . count($fieldLayouts) . ' field layouts' . PHP_EOL;
            $entryTypes = (new Query())
                ->select(['id'])
                ->from([Table::ENTRYTYPES])
                ->where(Db::parseParam('fieldLayoutId', $fieldLayouts))
                ->column();
            if (empty($entryTypes)) {
                return;
            }        
            //echo 'Found ' . count($entryTypes) . ' entry types' . PHP_EOL;  
            $elements = Entry::find()->typeId($entryTypes)->all();
        }

       

        foreach ($elements as $element) {
            if (!$blocks || count($blocks) == 0) {
                $matrixBlocks = $element->getFieldValue($handle)->all();
            } else {
                $matrixBlocks = $blocks;
            }
            foreach ($matrixBlocks as $block) {
                $fieldLayout = $block->type->getFieldLayout();
                //echo "Block handle " . $block->type->handle . PHP_EOL;
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if (get_class($field) == 'craft\\redactor\\Field') {
                        //$element = $block->getOwner();
                        $this->_addRedactorRelation($element, $block->getFieldValue($field->handle));
                    } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                        //echo 'Found nested supertable: ' . $field->handle . PHP_EOL;
                        $this->_extractAssetsFromSupertable($field->id, true, $field->handle, $element);
                    } 
                }
            } 
            
        }
    }

    private function _extractAssetsFromSupertable(int $fieldId, bool $nested = false, $handle, $element = null) {
        //echo 'Extracting assets from supertable field ' . $fieldId . PHP_EOL;
        if (!$nested && $element) {
            $superTableBlocks = $element->getFieldValue($handle)->all();
        } else {
            $query = SuperTableBlockElement::find()->fieldId($fieldId);
            $superTableBlocks = $query->all();
        }
        //echo 'Found ' . count($superTableBlocks) . ' blocks' . PHP_EOL;
        foreach ($superTableBlocks as $block) {
            $fieldLayout = $block->getFieldLayout();
            foreach ($fieldLayout->getCustomFields() as $field) {
                //echo $field->handle . ' ' . get_class($field) . PHP_EOL;
                if (!$nested && !$element) {
                    $element = $block->getOwner();
                }    
                if (get_class($field) == 'craft\\redactor\\Field') {
                    $this->_addRedactorRelation($element, $block->getFieldValue($field->handle));
                } else if (get_class($field) == 'craft\\fields\\Matrix') {
                    $blocks = $block->getFieldValue($field->handle)->all();
                    if (count($blocks)) {
                        //echo 'Found nested matrix: ' . $field->handle . PHP_EOL;
                        $this->_extractAssetsFromMatrix($field->handle, true, $element, $blocks);
                    }
                } 
            }
        }
    }

    private function _extractFilenames(string $html): array {

        $imgArray = [];
        $doc = new DOMDocument();
        @$doc->loadHTML($html);

        $tags = $doc->getElementsByTagName('img');

        foreach ($tags as $tag) {
            $src = $tag->getAttribute('src');
            $lastSlashPos = strrpos($src, "/");
            $endOfFilenamePos = strrpos($src, "?") > strrpos($src, "#") ? 
                                    strrpos($src, "?") : strrpos($src, "#");
            if ($endOfFilenamePos) {
                $filename = substr($src, $lastSlashPos + 1, $endOfFilenamePos - $lastSlashPos - 1);
            } else {
                $filename = substr($src, $lastSlashPos + 1);
            }
            
            $imgArray[] = $filename;
        }
        return $imgArray;
    }
    /**
     * Count the number of times an asset is used and return a formatted string.
     * e.g. Used {count} times
     *
     * @param  AssetElement $asset
     * @return string
     */
    public function getUsageCount(AssetElement $asset): string
    {
        $relations = $this->queryRelations($asset);

        if (Plugin::getInstance()->settings->includeRevisions) {
            $count = $this->formatResults(count($relations));
        } else {
            $count = count(array_filter($relations, function ($relation) {
                try {
                    /** @var craft\base\Element */
                    $element = Craft::$app->elements->getElementById($relation['id'], null, $relation['siteId']);

                    return !!$element && !ElementHelper::isDraftOrRevision($element);
                } catch (\Throwable $e) {
                    return false;
                }
            }));
        }

        $relations = $this->queryAssetRelations($asset);

        if (Plugin::getInstance()->settings->includeRevisions) {
            $count += $this->formatResults(count($relations));
        } else {
            $count += $count + count(array_filter($relations, function ($relation) {
                try {
                    /** @var craft\base\Element */
                    $element = Craft::$app->elements->getElementById($relation['id'], null, $relation['siteId']);

                    return !!$element && !ElementHelper::isDraftOrRevision($element);
                } catch (\Throwable $e) {
                    return false;
                }
            }));

        }

        return $this->formatResults($count);
    }

    /**
     * Get all elements related to the asset.
     *
     * @param  AssetElement $asset
     * @return array
     */
    public function getUsedIn(AssetElement $asset): array
    {
        $relations = $this->queryRelations($asset);

        $elements = [];

        foreach ($relations as $relation) {
            try {
                /** @var craft\services\Elements */
                $elementsService = Craft::$app->elements;

                /** @var craft\base\Element */
                $element = $elementsService->getElementById($relation['id'], null, $relation['siteId']);

                $root = ElementHelper::rootElement($element);
                $isRevision = $root->getIsDraft() || $root->getIsRevision();

                if ($root && !$isRevision) {
                    $elements[$root->id] = $root;
                }
            } catch (\Throwable $e) {
                // let it slide...
            }
        }

        return array_values($elements);
    }

    private function queryRelations(AssetElement $asset): array
    {
        return (new Query())
            ->select(['sourceId as id', 'sourceSiteId as siteId'])
            ->from(Table::RELATIONS)
            ->where(['targetId' => $asset->id])
            ->all();
    }

    private function queryAssetRelations(AssetElement $asset): array
    {
        return (new Query())
            ->select(['targetId as id', 'sourceSiteId as siteId'])
            ->from('assetusage_assetrelations')
            ->where(['targetId' => $asset->id])
            ->all();
    }

    /**
     * Format the count into a string.
     * e.g. Used {count} times
     *
     * @param  int $count
     * @return string
     */
    private function formatResults($count): string
    {
        if ($count === 1) {
            return Craft::t('assetusage', 'Used {count} time', ['count' => $count]);
        } elseif ($count > 1) {
            return Craft::t('assetusage', 'Used {count} times', ['count' => $count]);
        }

        return '<span style="color: #da5a47;">' . Craft::t('assetusage', 'Unused') . '</span>';
    }
}
