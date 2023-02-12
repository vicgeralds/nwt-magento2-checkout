<?php

namespace Svea\Checkout\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Svea\Checkout\Helper\Data;

class Layout
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Data
     */
    private $data;

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $data
    ) {
        $this->storeManager = $storeManager;
        $this->data = $data;
    }

    /**
     * Returns shipping template path if Svea Shipping is disabled, so that standard shipping will be shown
     * Otherwise returns null since then shipping block should not be displayed
     *
     * @param string $template
     * @return string|null
     */
    public function getShippingTemplate($template = 'Svea_Checkout::checkout/shipping.phtml')
    {
        $store = $this->storeManager->getStore();
        if ($this->data->getSveaShippingActive($store->getCode())) {
            return null;
        }

        return $template;
    }
}
