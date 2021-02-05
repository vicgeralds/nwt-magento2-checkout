<?php

namespace Svea\Checkout\Model\Svea\Data\PresetValues;

use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class TestValuesProvider implements PresetValuesProviderInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * TestValuesProvider constructor.
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string|null
     */
    public function getEmailAddress() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setEmailAddress('test@example.com');

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getPhoneNumber() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setPhoneNumber('0811111111');

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getIsCompany() : PresetValue
    {
        $customerType = $this->scopeConfig->getValue('svea_checkout/settings/default_customer_type');
        $customerTypes = $this->scopeConfig->getValue('svea_checkout/settings/customer_types');
        $customerTypes = explode(',', $customerTypes);

        $isB2B = $customerType == 'B2B';
        $isB2C = in_array('B2C', $customerTypes);

        $presetValue = new PresetValue();
        $presetValue->setIsCompany($isB2B);
        $presetValue->setValue($isB2B);

        $isReadOnly = true;
        if (count($customerTypes) > 1) {
            $isReadOnly = false;
        }

        $presetValue->setIsReadOnly($isReadOnly);

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getPostalCode() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setPostalCode('99999');

        return $presetValue;
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
            'IsCompany'     => $this->getIsCompany(),
        ];
    }
}
