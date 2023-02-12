<?php

namespace Svea\Checkout\Service;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;

class SveaShippingInfo
{
    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    public function __construct(
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Get data object with Svea Shipping Info from quote
     *
     * @param Quote $quote
     * @return DataObject|null
     */
    public function getFromQuote(Quote $quote): ?DataObject
    {
        $extShippingInfo = $quote->getExtShippingInfo();
        if (!$extShippingInfo) {
            return null;
        }

        try {
            $extShipInfoArray = $this->jsonSerializer->unserialize($extShippingInfo);
        } catch (\Exception $e) {
            return null;
        }

        $sveaArray = $extShipInfoArray['svea_shipping_info'] ?? [];
        if (count($sveaArray) < 1) {
            return null;
        }
        return $this->dataObjectFactory->create()->setData($sveaArray);
    }

    /**
     * Set Svea Shipping Info on quote
     *
     * @param Quote $quote
     * @param array $content
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setInQuote(Quote $quote, array $content): void
    {
        $contentObj = $this->dataObjectFactory->create()->setData($content);
        try {
            $extShippingInfo = $this->jsonSerializer->unserialize($quote->getExtShippingInfo());
        } catch (\Exception $e) {
            $extShippingInfo = [];
        }

        $extShippingInfo['svea_shipping_info'] = $contentObj->toArray();

        $quote->setExtShippingInfo($this->jsonSerializer->serialize($extShippingInfo));
        $quote->getPayment()->setAdditionalInformation('svea_shipping_info', $contentObj->toArray());
    }
}
