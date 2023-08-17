<?php

namespace born05\assetusage\console\controllers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use yii\console\Controller;
use yii\console\ExitCode;

use born05\assetusage\services\Asset as AssetService;

/**
 * Controls
 */
class DefaultController extends Controller
{

    /**
     * Handle assetusage/default console commands
     *
     * @return mixed
     */
    public function actionIndex() {

        $assetService = new AssetService();

        $assetService->storeRedactorAssets();
        
        //$this->stdout('Hello World!' . PHP_EOL);
        return true;
    }

    /**
     * Lists all unused assets.
     * @param string|null $volume The handle of the asset's volume.
     */
    public function actionListUnused(?string $volume = null)
    {
        $this->stdout('Listing all unused asset ids!' . PHP_EOL);

        $results = $this->getUnusedAssets($volume);
        foreach ($results as $result) {
            $this->stdout($result['id'] . ' : ' . $result['filename'] . PHP_EOL);
        }

        return ExitCode::OK;
    }

    /**
     * Deletes all unused assets.
     * @param string|null $volume The handle of the asset's volume.
     */
    public function actionDeleteUnused(?string $volume = null)
    {
        $this->stdout('Deleting all unused asset ids:' . PHP_EOL);

        $results = $this->getUnusedAssets($volume);
        $assetCount = count($results);

        if ($this->confirm("Delete $assetCount assets?")) {
            $assets = Craft::$app->getAssets();

            foreach ($results as $result) {
                $this->stdout('Deleting ' . $result['id'] . ' : ' . $result['filename'] . PHP_EOL);

                $asset = $assets->getAssetById($result['id']);
                if ($asset) {
                    Craft::$app->getElements()->deleteElement($asset);
                }
            }

            $this->stdout('Deleted all unused asset ids.' . PHP_EOL);
        }

        return ExitCode::OK;
    }

    private function getUnusedAssets(?string $volume = null)
    {
        if ($volume) {
            /** @var craft\models\Volume */
            $volumeModel = Craft::$app->getVolumes()->getVolumeByHandle($volume);
        }

        $subQuery = (new Query())
          ->select('id')
          ->from(Table::RELATIONS . ' relations')
          ->where('relations.targetId=assets.id')
          ->orWhere('relations.sourceId=assets.id');
    
        $query = (new Query())
            ->select(['id', 'filename'])
            ->from(Table::ASSETS . ' assets')
            ->where(['not exists', $subQuery]);

        if (isset($volumeModel)) {
            $query->where(['volumeId' => $volumeModel->id]);
        }

        return $query->all();
    }
}
