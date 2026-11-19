<?php

namespace Bread\BreadCheckout\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\App\ResourceConnection;

class Uninstall implements UninstallInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $connection = $this->resource->getConnection();
        $configTable = $this->resource->getTableName('core_config_data');

        $connection->delete(
            $configTable,
            [
                $connection->quoteInto('path LIKE ?', 'payment/breadcheckout/%'),
                $connection->quoteInto('path LIKE ?', 'payment/rbccheckout/%')
            ]
        );
    }
}
