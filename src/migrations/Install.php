<?php

namespace born05\assetusage\migrations;



use Craft;
use craft\config\DbConfig;
use craft\db\Migration;


class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }


    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    protected function createTables()
    {
        $tablesCreated = false;

    // matrixinventory_matrixlist table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%assetusage_assetrelations}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%assetusage_assetrelations}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'sourceId' => $this->integer()->notNull(),
                    'sourceSiteId' => $this->integer()->notNull(),
                    'targetId' => $this->integer()->notNull()
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {

        $this->createIndex(
            $this->db->getIndexName(
                '{{%assetusage_assetrelations}}',
                ['sourceId', 'targetId', 'sourceSiteId'],
                true
            ),
            '{{%assetusage_assetrelations}}',
            ['sourceId', 'targetId', 'sourceSiteId', 'id'],
            true
        );
        
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%assetusage_assetrelations}}', 'sourceSiteId'),
            '{{%assetusage_assetrelations}}',
            'sourceSiteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%assetusage_assetrelations}}', 'sourceId'),
            '{{%assetusage_assetrelations}}',
            'sourceId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%assetusage_assetrelations}}', 'targetId'),
            '{{%assetusage_assetrelations}}',
            'targetId',
            '{{%assets}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%assetusage_assetrelations}}');
    }
}
