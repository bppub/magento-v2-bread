<?php

namespace Bread\BreadCheckout\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Cache\TypeListInterface;

class CopyApiCredentials implements DataPatchInterface
{
    const PATH_TRANSLATION_MAP = [
        'classic_api_public_key' => 'api_public_key',
        'classic_api_secret_key' => 'api_secret_key',
        'classic_api_sandbox_public_key' => 'api_sandbox_public_key',
        'classic_api_sandbox_secret_key' => 'api_sandbox_secret_key',

    ];

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @param ConfigInterface $config
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        ConfigInterface $config,
        ModuleDataSetupInterface $moduleDataSetup,
        TypeListInterface $cacheTypeList
    ) {
        $this->config = $config;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->cacheTypeList = $cacheTypeList;
    }


    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        foreach (self::PATH_TRANSLATION_MAP as $classic => $platform) {
            $row = $this->moduleDataSetup->getTableRow(
                'core_config_data',
                'path',
                'payment/breadcheckout/' . $classic
            );
            if ($row && !empty($row['value'])) {
                $this->config->saveConfig(
                    'payment/breadcheckout/' . $platform, $row['value'],
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
        }
        
        //Clear config cache
        $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);

        $this->moduleDataSetup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
