<?php
/**
 * Helps Integration With Catalog
 *
 * @author Bread   copyright   2016
 * @author Joel    @Mediotype
 * @author Miranda @Mediotype
 */
namespace Bread\BreadCheckout\Helper;

class Catalog extends Data
{
    /**
     * @var \Magento\Catalog\Block\Product\View
     */
    public $productViewBlock;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterfaceFactory
     */
    public $productRepositoryFactory;

    public function __construct(
        \Magento\Framework\App\Helper\Context $helperContext,
        \Magento\Framework\Model\Context $context,
        \Magento\Catalog\Block\Product\View $productViewBlock,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\UrlInterfaceFactory $urlInterfaceFactory,
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->productViewBlock = $productViewBlock;
        $this->productRepositoryFactory = $productRepositoryFactory;
        $this->storeManager = $storeManager;
        parent::__construct($helperContext, $context, $request, $encryptor, $urlInterfaceFactory, $storeManager);
    }

    /**
     * Get html param string for default button size based on configuration
     *
     * @return string
     */
    public function getDefaultButtonSizeHtml()
    {
        if ($this->useDefaultButtonSize()) {
            return 'data-bread-default-size="true"';
        }

        return '';
    }

    /**
     * Get Formatted Product Data Array
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @param  \Magento\Catalog\Model\Product|null $baseProduct
     * @param  int                            $qty
     * @param  float|null                     $lineItemPrice
     * @return array
     */
    public function getProductDataArray(
        \Magento\Catalog\Model\Product $product,
        ?\Magento\Catalog\Model\Product $baseProduct = null,
        int $qty = 1,
        ?float $lineItemPrice = null
    ): array {
    
        $theProduct     = ($baseProduct == null) ? $product : $baseProduct;
        $skuString      = $this->getSkuString($product, $theProduct);
        $price          = $this->getPrice($lineItemPrice, $theProduct);

        $productData = [
            'name'      => $theProduct->getName(),
            'price'     => $price,
            'sku'       => ( $baseProduct == null ) ? $skuString : ($baseProduct['sku'].'///'.$skuString),
            'detailUrl' => $theProduct->getProductUrl(),
            'quantity'  => $qty,
            'id' => $theProduct->getId()
        ];

        $imgSrc = $this->getImgSrc($product);
        if ($imgSrc != null) {
            $productData['imageUrl'] = $imgSrc;
        }

        return $productData;
    }

    /**
     * Get formatted product data for grouped product
     * based on lowest price associated item
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getGroupedProductDataArray(\Magento\Catalog\Model\Product $product)
    {
        $grouped = $product->getTypeInstance();
        $associatedProductsCollection = $grouped->getAssociatedProductCollection($product)
            ->addAttributeToSelect('name')
            ->addAttributeToSort('price', 'ASC');
        $item = $associatedProductsCollection->getFirstItem();

        $productData = [
            'name'      => $item->getName(),
            'price'     => round($item->getFinalPrice() * 100),
            'sku'       => $item->getSku(),
            'detailUrl' => $item->getProductUrl(),
            'quantity'  => 1,
        ];

        $imgSrc = $this->getImgSrc($item);
        if ($imgSrc != null) {
            $productData['imageUrl'] = $imgSrc;
        }

        return $productData;
    }

    /**
     * Return Product SKU Or Formatted SKUs for Products With Options
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @param  \Magento\Catalog\Model\Product $theProduct
     * @return string
     */
    protected function getSkuString(
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\Product $theProduct
    ) {
    
        $selectedOptions    = $theProduct->getTypeInstance(true)->getOrderOptions($theProduct);

        if (!array_key_exists('options', $selectedOptions)) {
            return (string) $product->getSku();
        }
        // @codingStandardsIgnoreStart
        $skuString  = $product->getData('sku');
        foreach ($selectedOptions['options'] as $key => $value) {
            if ($value['option_type'] == 'multiple') {
                $selectedOptionValues = explode(',', $value['option_value']);
            } else {
                $selectedOptionValues = [$value['option_value']];
            }
            foreach ($selectedOptionValues as $selectedOptionValue) {
                $found = false;
                foreach ($theProduct->getOptions() as $option) {
                    if ($found) {
                        break;
                    }
                    if (!empty($option->getValues())) {
                        if ($option->getTitle() == $value['label']) {
                            foreach ($option->getValues() as $optionValue) {
                                if ($selectedOptionValue == $optionValue->getOptionTypeId()) {
                                    $skuString = $skuString . '***' . $optionValue->getSku();
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    } elseif ($value['label'] == $option['title']) {
                        $skuString = $skuString . '***' . $option->getSku() . '===' . $value['option_value'];
                        break;
                    }
                }
            }
        }
        // @codingStandardsIgnoreEnd

        return $skuString;
    }

    /**
     * Get Price
     *
     * @param  null                           $lineItemPrice
     * @param  \Magento\Catalog\Model\Product $theProduct
     * @return float
     */
    protected function getPrice($lineItemPrice, \Magento\Catalog\Model\Product $theProduct)
    {

        if ($lineItemPrice !== null) {
            $price = $lineItemPrice;
        } else {
            $price = $theProduct->getFinalPrice();

            // For Bundled and Grouped products, final price comes through as 0, so need to use minimum price instead
            if (floatval($price) === 0.0) {
                $price = $theProduct->getMinimalPrice();
            }
        }

        return round($price * 100);
    }

    /**
     * Get Img Src Value
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @return null|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getImgSrc(\Magento\Catalog\Model\Product $product)
    {
        if ($this->isInAdmin()) {
            $product = $this->productRepositoryFactory->create()->getById($product->getId());
            $imageUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
                . 'catalog/product' . $product->getImage();
            return $imageUrl;
        }

        try {
            return (string) $this->productViewBlock->getImage($product, 'product_small_image')->getImageUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
