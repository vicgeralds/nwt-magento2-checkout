<?php

namespace Svea\Checkout\ViewModel\Adminhtml\Order\View;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\DataObjectFactory;

class Shipping implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    public function __construct(DataObjectFactory $dataObjectFactory) {
        $this->dataObjectFactory = $dataObjectFactory;
    }

    public function getShippingLocationInfo(OrderInterface $order)
    {
        $shipInfo = $order->getPayment()->getAdditionalInformation()['svea_shipping_info'] ?? [];
        $dataObj = $this->dataObjectFactory->create()->setData($shipInfo);
        $locationData = $dataObj->getLocation();
        $locationObj = $this->dataObjectFactory->create()->setData($locationData);
        return $locationObj;
    }
}
