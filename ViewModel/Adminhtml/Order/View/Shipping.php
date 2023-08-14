<?php

namespace Svea\Checkout\ViewModel\Adminhtml\Order\View;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Svea\Checkout\Service\SveaShippingInfo;

class Shipping implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var SveaShippingInfo
     */
    private SveaShippingInfo $sveaShippingInfo;

    /**
     * @var DataObject|null
     */
    private ?DataObject $locationObj = null;

    private ?Address $sourcedAddress = null;

    public function __construct(
        DataObjectFactory $dataObjectFactory,
        SveaShippingInfo $sveaShippingInfo
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->sveaShippingInfo = $sveaShippingInfo;
    }

    public function getShippingLocationInfo(Order $order): DataObject
    {
        if (!$this->locationObj) {
            $shipInfo = $this->sveaShippingInfo->getFromOrder($order);
            $locationData = [];
            if (null !== $shipInfo) {
                $locationData = $shipInfo->getLocation();
            }
            $this->locationObj = $this->dataObjectFactory->create()->setData($locationData);
        }
        return $this->locationObj;
    }

    /**
     * Get postal address line as string
     *
     * @param Order $order
     * @return string
     */
    public function getPostalAddressLine(Order $order): string
    {
        $address = $this->sourceShippingAddress($order);
        $postalLineFormat = '%s %s';
        if ($address->getCountryId()) {
            $postalLineFormat .= ', %s';
        }

        $postalLine = sprintf(
            $postalLineFormat,
            $address->getPostcode(),
            $address->getCity(),
            $address->getCountryId()
        );

        return $postalLine;
    }

    /**
     * Get street address lines as array
     *
     * @param Order $order
     * @return array
     */
    public function getStreetAddressLines(Order $order): array
    {
        $address = $this->sourceShippingAddress($order);
        return $address->getStreet();
    }

    /**
     * Get shipping address either from order or from shipping location info
     *
     * @param Order $order
     * @return Address
     */
    private function sourceShippingAddress(Order $order): Address
    {
        $address = $order->getShippingAddress();
        $locationInfo = $this->getShippingLocationInfo($order);
        if (null === $locationInfo->getAddress()) {
            $this->sourcedAddress = $address;
            return $this->sourcedAddress;
        }
        $address = $this->sveaShippingInfo->createOrderAddressFromLocation($locationInfo);
        $this->sourcedAddress = $address;
        return $this->sourcedAddress;
    }
}
