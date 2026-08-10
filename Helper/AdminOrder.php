<?php

namespace Bread\BreadCheckout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class AdminOrder extends AbstractHelper
{
    protected $_session;
    protected $_appState;

    public function __construct(
        Context $context,
        \Magento\Backend\Model\Session\Quote $session,
        \Magento\Framework\App\State $appState
    ) {
        $this->_session = $session;
        $this->_appState = $appState;
        parent::__construct($context);
    }

    public function isAdminOrder()
    {
        try {
            if ($this->_appState->getAreaCode() !== \Magento\Framework\App\Area::AREA_ADMINHTML) {
                return false;
            }
            return $this->_session->getQuote()->getIsAdminOrder();
        } catch (\Exception $e) {
            return false;
        }
    }
}
