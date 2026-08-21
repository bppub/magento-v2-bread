<?php

namespace Bread\BreadCheckout\Setup\Patch\Data;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class EncryptAuthTokens implements DataPatchInterface
{
    private const AUTH_TOKEN_PATH = 'payment/breadcheckout/bread_auth_token';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EncryptorInterface $encryptor,
        TypeListInterface $cacheTypeList
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', self::AUTH_TOKEN_PATH)
        );

        foreach ($rows as $row) {
            $value = $row['value'];
            if ($value === null || $value === '' || preg_match('/^\d+:\d+:/', $value)) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int) $row['config_id']]
            );
        }

        $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);
        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}