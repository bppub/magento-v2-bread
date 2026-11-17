<?php
/**
 * @author Maritim, Kip
 * @copyright (c) 2023, Bread Financial
 *
 * Manage Catalog page product collection listing
 * 
 */

namespace Bread\BreadCheckout\Block\Catalog;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\categoryFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class CatProductCollection
 */
class ProductCollection extends Template
{
    /** 
     * @var Registry  
     */
    private $_registry;

    /** 
     * @var categoryFactory  
     */
    private $_categoryFactory;

    /** 
     * @var CollectionFactory  
     */
    protected $_productCollectionFactory;

    /** 
     * @var Category  
     */
    protected $_category;

    /** 
     * @var 
     */
    protected $categoryRepository;

    /**
     * @var \Bread\BreadCheckout\Helper\Quote
     */
    private $quoteHelper;

    /**
     * 
     * @var \Bread\BreadCheckout\Helper\Data
     */
    private $dataHelper;
    
    /**
     * @var \Bread\BreadCheckout\Helper\Category
     */
    
    private $categoryHelper;
    
    /**
     * @var \Bread\BreadCheckout\Helper\Customer
     */
    private $customerHelper;
    
    /**
     * @var \Bread\BreadCheckout\Helper\Catalog
     */
    public $catalogHelper;
    
    protected $_objectManager = null;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category
     */
    private $categoryResource;

    /**
     * CatProductCollection constructor.
     * @param Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param Registry $registry
     * @param Category $category
     * @param categoryFactory $categoryFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Registry $registry,
        Category $category,
        categoryFactory $categoryFactory,
        CategoryRepository $categoryRepository,
        ObjectManagerInterface $objectManager,
        \Bread\BreadCheckout\Helper\Category $categoryHelper,
        \Bread\BreadCheckout\Helper\Customer $customerHelper,
        \Bread\BreadCheckout\Helper\Catalog $catalogHelper,
        \Bread\BreadCheckout\Helper\Quote $quoteHelper,
        \Bread\BreadCheckout\Helper\Data $dataHelper,    
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->categoryResource = $categoryResource;
        $this->_registry = $registry;
        $this->_categoryFactory = $categoryFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_category = $category;
        $this->categoryRepository = $categoryRepository;
        $this->_objectManager = $objectManager;
        $this->categoryHelper = $categoryHelper;
        $this->customerHelper = $customerHelper;
        $this->catalogHelper  = $catalogHelper;
        $this->quoteHelper    = $quoteHelper;
        $this->dataHelper = $dataHelper;
        
    }

    /**
     * @return array (product)
     */
    public function getCategoryProductCollection()
    {
        //get current category ID
        $category_load = $this->_registry->registry('current_category');
        $categoryId = $category_load->getId();
        if ($this->categoryHelper->isEnabledForCategory($category_load)) {
            //load product collection of category id wise
            $category_product_collection = $this->_categoryFactory->create();
            $this->categoryResource->load($category_product_collection, $categoryId);
            $collection = $this->_productCollectionFactory->create();
            
            //Paginate collection
            $page = ($this->getRequest()->getParam('p')) ? $this->getRequest()->getParam('p') : 1;
            $pageSize = ($this->getRequest()->getParam('limit')) ? $this->getRequest
                    ()->getParam('limit') : 12;
            $collection->addAttributeToSelect('*');
            $collection->addCategoryFilter($category_product_collection);
            $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);

            return $collection;
        }
        return array();
    }


    /**
     * @since 2.2.0
     * @return type
     */
    public function getApiVersion() {
        return (string) $this->dataHelper->getApiVersion();
    }
    
    /**
     * @since 2.2.0
     * @return string
     */
    public function getIntegrationKey() {
        return $this->dataHelper->getIntegrationKey();
    }

    /**
     * @since 2.2.0
     * @return string
     */
    public function getConfigClient() {
        return $this->dataHelper->getConfigClient();
    }

    /**
     * @since 2.2.0
     * @return string
     */
    public function getCurrentCurrencyCode() {
        return $this->catalogHelper->getCurrentCurrencyCode();
    }
    
    public function isEnabledOnCAT() {
        return $this->categoryHelper->isEnabledOnCAT();
    }
}
