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
use born05\assetusage\Plugin;
use born05\assetusage\records\AssetRelationship as AssetRelRecord;
use born05\assetusage\models\AssetRelationship as AssetRelModel;

use verbb\supertable\elements\SuperTableBlockElement;

class Asset extends Component
{

    public function storeRedactorAssets(): void
    {

        echo 'Hi World!' . PHP_EOL;

        $fields = Craft::$app->fields->getAllFields();

        $matrixFields = [];

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
                    
                    $this->_addAssetRelation($element, $redactorElement);
                    

                }
            } else if (get_class($field) == 'craft\\fields\\Matrix') {
                $this->_extractAssetsFromMatrix($field->handle);
            } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                $this->_extractAssetsFromSupertable($field->id);
            }
        }
    }

    private function _addAssetRelation($element, $redactorElement) {
        if (preg_match('/src=/', $redactorElement)) {
            //echo 'Found asset: ' . $redactorElement . PHP_EOL;
            $imgArray = $this->_extractFilenames($redactorElement);
            foreach($imgArray as $filename) {
                //echo 'Filename: ' . $filename . PHP_EOL;
                $img = \craft\elements\Asset::find()->filename($filename)->one();
                if ($img) {
                    $model = new AssetRelModel();
                    $record = new AssetRelRecord();
                    $now = new DateTime();
                    $model->dateCreated = $now->format('Y-m-d H:i:s');
                    $model->dateUpdated = $now->format('Y-m-d H:i:s');

                    $model->sourceId = $element->id;
                    $model->targetId = $img->id;
                    $model->sourceSiteId = $element->siteId;
                    $record->setAttributes($model->getAttributes(), false);
                    $record->save();

                }
            }
        }
    }

    private function _extractAssetsFromMatrix(string $handle, bool $nested = false, $element = null) {
        echo 'Extracting assets from matrix field ' . $handle . PHP_EOL;
        if ($nested && $element) {
            $elements = [$element];
        } else {
            $elements = Entry::find()->search($handle . ':*')->all();
        }
        foreach ($elements as $element) {
            $matrixBlocks = $element->getFieldValue($handle);
            foreach ($matrixBlocks as $block) {
                $fieldLayout = $block->type->getFieldLayout();
                $tabs = $fieldLayout->getTabs();
                if (empty($tabs)) {
                    continue;
                }
                $tab = $fieldLayout->getTabs()[0];

                foreach ($tab->getElements() as $layoutElement) {
                    if ($layoutElement instanceof CustomField) {
                        $field = $layoutElement->getField();
                        //echo 'Element title: ' . $element->title . PHP_EOL;
                        if ($element->title == "How using AI for design can optimize your workflow and provide results") {
                            echo 'Matrix field handle: ' . $field->handle . ' ' . get_class($field) . PHP_EOL;
                        }
                        
                        if (get_class($field) == 'craft\\redactor\\Field') {
                            $this->_addAssetRelation($element, $block->getFieldValue($field->handle));
                        } else if (get_class($field) == 'craft\\fields\\Matrix') {
                            echo 'Found nested matrix: ' . $field->handle . PHP_EOL;
                            $this->_extractAssetsFromMatrix($field->handle, true, $element);
                        } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                            echo 'Found nested supertable: ' . $field->handle . PHP_EOL;
                            $this->_extractAssetsFromSupertable($field->id);
                        }
                    }
                }
            } 
            
        }
    }

    private function _extractAssetsFromSupertable(int $fieldId) {
        echo 'Extracting assets from supertable field ' . $fieldId . PHP_EOL;
        $query = SuperTableBlockElement::find()->fieldId($fieldId);
        $superTableBlocks = $query->all();
        echo 'Found ' . count($superTableBlocks) . ' blocks' . PHP_EOL;
        foreach ($superTableBlocks as $block) {
            $fieldLayout = $block->getFieldLayout();
            foreach ($fieldLayout->getCustomFields() as $field) {
                echo $field->handle . ' ' . get_class($field) . PHP_EOL;    
                if (get_class($field) == 'craft\\redactor\\Field') {
                    $element = $block->getOwner();
                    $this->_addAssetRelation($element, $block->getFieldValue($field->handle));
                } else if (get_class($field) == 'craft\\fields\\Matrix') {
                    $element = $block->getOwner();
                    echo 'Found nested matrix: ' . $field->handle . PHP_EOL;
                    $this->_extractAssetsFromMatrix($field->handle, true, $element);
                } else if (get_class($field) == 'verbb\\supertable\\fields\\SuperTableField') {
                    echo 'Found nested supertable: ' . $field->handle . PHP_EOL;
                    $this->_extractAssetsFromSupertable($field->id);
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
