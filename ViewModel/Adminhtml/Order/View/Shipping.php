<?php

namespace Svea\Checkout\ViewModel\Adminhtml\Order\View;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\AddressFactory;
use Magento\Sales\Model\Order\Address;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Svea\Checkout\Helper\SveaShippingConfig;

class Shipping implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var AddressFactory
     */
    private AddressFactory $addressFactory;

    /**
     * @var DataObject|null
     */
    private ?DataObject $locationObj = null;

    private ?Address $sourcedAddress = null;

    public function __construct(
        DataObjectFactory $dataObjectFactory,
        AddressFactory $addressFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->addressFactory = $addressFactory;
    }

    public function getShippingLocationInfo(Order $order): DataObject
    {
        if (!$this->locationObj) {
            $shipInfo = $order->getPayment()->getAdditionalInformation()['svea_shipping_info'] ?? [];
            $dataObj = $this->dataObjectFactory->create()->setData($shipInfo);
            $locationData = $dataObj->getLocation();
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
        $address = $this->addressFactory->create();
        $locationAddress = $locationInfo->getAddress();
        $address->setCountryId($locationAddress['countryCode']);
        $address->setPostcode($locationAddress['postalCode']);
        $address->setCity($locationAddress['city']);
        $street = [$locationAddress['streetAddress']];
        if (isset($locationAddress['streetAddress2']) && !empty($locationAddress['streetAddress2'])) {
            $street[] = $locationAddress['streetAddress2'];
        }
        $address->setStreet($street);
        $this->sourcedAddress = $address;
        return $this->sourcedAddress;
    }
}
