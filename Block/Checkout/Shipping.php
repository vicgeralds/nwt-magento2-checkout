<?php

namespace Svea\Checkout\Block\Checkout;

use Svea\Checkout\Block\Checkout;

class Shipping extends Checkout
{
    public function getShippingMethodUrl()
    {
        return $this->getUrl("{$this->_controllerPath}/GetShippingMethod");
    }
}