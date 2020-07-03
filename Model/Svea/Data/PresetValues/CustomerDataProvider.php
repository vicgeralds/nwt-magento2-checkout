<?php

namespace Svea\Checkout\Model\Svea\Data\PresetValues;

use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class CustomerDataProvider implements PresetValuesProviderInterface
{
    /**
     * @var Session
     */
    private $customer;

    /**
     * @var DataObject
     */
    private $dataContainer;

    /**
     * @var PresetValueFactory
     */
    private $presetValueFactory;

    /**
     * CustomerDataProvider constructor.
     *
     * @param Session $customer
     * @param DataObject $dataContainer
     * @param PresetValueFactory $presetValueFactory
     */
    public function __construct(
        Session $customer,
        DataObject $dataContainer,
        PresetValueFactory $presetValueFactory
    ) {
        $this->customer = $customer;
        $this->dataContainer = $dataContainer;
        $this->presetValueFactory = $presetValueFactory;
    }

    /**
     * @return string|null
     */
    public function getEmailAddress() : PresetValue
    {
        $cacheKey = 'EmailAddress';
        if (! $this->dataContainer->getData($cacheKey)) {
            $customeEmail = $this->customer->isLoggedIn()
                ? $this->customer->getCustomer()->getEmail()
                : null;

            $presetValue = $this->presetValueFactory->create();
            $presetValue->setEmailAddress($customeEmail);
            $this->setCache($cacheKey, $presetValue);
        }

        return $this->getCache($cacheKey);
    }

    /**
     * @return mixed|string|null
     */
    public function getPhoneNumber() : PresetValue
    {
        $cacheKey = 'PhoneNumber';
        if (! $this->dataContainer->getData($cacheKey)) {

            $shippingAddress = $this->customer->getCustomer()->getDefaultShippingAddress();
            $phone = $shippingAddress ? $shippingAddress->getTelephone() : null;

            $presetValue = $this->presetValueFactory->create();
            $presetValue->setPhoneNumber($phone);
            $this->setCache($cacheKey, $presetValue);
        }

        return $this->getCache($cacheKey);


    }

    /**
     * @return mixed|string|null
     */
    public function getPostalCode() : PresetValue
    {
        $cacheKey = 'PostalCode';
        if (! $this->dataContainer->getData($cacheKey)) {

            $shippingAddress = $this->customer->getCustomer()->getDefaultShippingAddress();
            $postalCode = $shippingAddress ? $shippingAddress->getPostcode() : null;

            $presetValue = $this->presetValueFactory->create();
            $presetValue->setPostalCode($postalCode);
            $this->setCache($cacheKey, $presetValue);
        }

        return $this->getCache($cacheKey);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'EmailAddress'  => $this->getEmailAddress(),
            'PhoneNumber'   => $this->getPhoneNumber(),
            'PostalCode'    => $this->getPostalCode(),
        ];
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    private function getCache($key) : ?PresetValue
    {
        return $this->dataContainer->getData($key);
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    private function setCache($key, $value) : void
    {
        $this->dataContainer->setData($key, $value);
    }
}
