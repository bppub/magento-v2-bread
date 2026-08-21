<?php
namespace Bread\BreadCheckout\Helper;

use Braintree\Exception;

class Url extends Data
{
    /**
     * @var \Magento\Framework\Url
     */
    public $urlHelper;

    public function __construct(
        \Magento\Framework\App\Helper\Context $helperContext,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\UrlInterfaceFactory $urlInterfaceFactory,
        \Magento\Framework\Url $urlHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->urlHelper = $urlHelper;
        parent::__construct($helperContext, $context, $request, $encryptor, $urlInterfaceFactory, $storeManager, $configWriter);
    }

    /**
     * Get frontend url
     *
     * @param  string $routePath
     * @param  array  $routeParams
     * @return string
     */
    public function getFrontendUrl($routePath, $routeParams)
    {
        return $this->urlHelper->getUrl($routePath, $routeParams);
    }

    /**
     * Get The Validate Order URL
     *
     * @return string
     */
    public function getLandingPageURL($error = false)
    {
        $url = $this->getFrontendUrl(parent::URL_LANDING_PAGE, $error ? ['error' => '1'] : []);

        return preg_replace('/' . preg_quote('?') . '.*' . '/', '', $url);
    }
}
