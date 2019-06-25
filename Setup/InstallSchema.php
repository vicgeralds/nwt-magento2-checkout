<?php


namespace Svea\Checkout\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;


/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{


    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();
        $connection = $installer->getConnection();


        $quoteData = array(
            'svea_order_id' => ['type' => Table::TYPE_TEXT, 'length' => '255', 'comment' => 'svea_order_id', 'nullable' => true, 'default' => ''],
        );
        $orderData = array(
            'svea_order_id' => ['type' => Table::TYPE_TEXT, 'length' => '255', 'comment' => 'svea_order_id', 'nullable' => true, 'default' => ''],
        );


        $alterTables = array(
            'quote' => $quoteData,
            'sales_order' => $orderData,
        );


        foreach ($alterTables as $_table => $columns) {

            $table = $installer->getTable($_table);
            $tableInfo = $connection->describeTable($table);
            foreach ($columns as $column => $definition) {
                if (isset($tableInfo[$column])) {
                    continue;
                }

                $connection->addColumn($table, $column, $definition);
            }
        }


        $_table = 'quote';
        $table = $installer->getTable($_table);
        $idxName = $installer->getIdxName($_table, ['svea_order_id']);
        $connection->addIndex($table, $idxName, ['svea_order_id']);

        $_table = 'sales_order';
        $table = $installer->getTable($_table);
        $idxName = $installer->getIdxName($_table, ['svea_order_id']);
        $connection->addIndex($table, $idxName, ['svea_order_id']);


        $installer->endSetup();
    }


}
