<?php

namespace Svea\Checkout\Model\Svea\Data\PresetValues;

use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class TestValuesProvider implements PresetValuesProviderInterface
{
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
        ];
    }
}
