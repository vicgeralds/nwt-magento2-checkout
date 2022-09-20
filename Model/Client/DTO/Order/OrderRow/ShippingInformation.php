<?php

namespace Svea\Checkout\Model\Client\DTO\Order\OrderRow;

use Magento\Quote\Model\Quote\Item;
use Svea\Checkout\Model\Client\DTO\AbstractRequest;

class ShippingInformation extends AbstractRequest
{
    /**
     * @var int
     */
    private $weight;

    /**
     * Get the value of weight
     *
     * @return  int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Set the value of weight
     *
     * @param  int  $weight
     *
     * @return  self
     */
    public function setWeight(int $weight)
    {
        $this->weight = $weight;

        return $this;
    }

    public function toArray()
    {
        return [
            'Weight' => $this->getWeight()
        ];
    }
}
