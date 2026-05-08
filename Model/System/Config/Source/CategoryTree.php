<?php

namespace Bread\BreadCheckout\Model\System\Config\Source;

/**
 * Class CategoryTree
 *
 * @package Bread\BreadCheckout\Model\System\Config\Source
 */
class CategoryTree extends \Magento\Framework\App\Config\Value
{

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * CategoryTree constructor.
     *
     * @param \Magento\Framework\Model\Context                                $context
     * @param \Magento\Framework\Registry                                     $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface              $config
     * @param \Magento\Framework\App\Cache\TypeListInterface                  $cacheTypeList
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface                      $storeManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null    $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null              $resourceCollection
     * @param array                                                           $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager              = $storeManager;
    }

    /**
     * Get categories for current config scope
     *
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoriesTreeView()
    {
        $categories = $this->categoryCollectionFactory->create();

        $store = $this->storeManager->getStore();

        if ($store) {
            $rootCategoryId = $store->getRootCategoryId();
            $categories->addFieldToFilter('path', ['like' => "1/$rootCategoryId/%"]);
        }

        $categories->addAttributeToSelect('name')
            ->addAttributeToSort('path', 'asc')
            ->addFieldToFilter('is_active', ['eq' => '1'])
            ->load();

        return $categories;
    }

    /**
     * Get categories as option array
     *
     * @return array
     */
    public function toOptionArray()
    {

        $options = [
            [
                'label' => __('-- None --'),
                'value' => ''
            ]
        ];

        $categoriesTreeView = $this->getCategoriesTreeView();

        $lowest = null;
        foreach ($categoriesTreeView as $category) {
            if ($category->getLevel() < $lowest || $lowest == null) {
                $lowest = $category->getLevel();
            }
        }

        /**
         * @var \Magento\Catalog\Model\Category $category
         */
        foreach ($categoriesTreeView as $category) {
            $catName  = $category->getName();
            $catId    = $category->getId();
            $catLevel = $category->getLevel();

            $catName = str_repeat('----', $catLevel - $lowest) . $catName;

            $options[] = [
                'label' => $catName,
                'value' => $catId
            ];
        }

        return $options;
    }
}
