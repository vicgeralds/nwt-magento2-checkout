<?php

namespace Svea\Checkout\Model\Client\DTO\Order\ShippingInformation;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;

class FallbackOption extends AbstractRequest
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $carrier;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $shippingFee;

    /**
     * Get the value of id
     *
     * @return  string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @param  string  $id
     *
     * @return  self
     */
    public function setId(string $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of carrier
     *
     * @return  string
     */
    public function getCarrier()
    {
        return $this->carrier;
    }

    /**
     * Set the value of carrier
     *
     * @param  string  $carrier
     *
     * @return  self
     */
    public function setCarrier(string $carrier)
    {
        $this->carrier = $carrier;

        return $this;
    }

    /**
     * Get the value of name
     *
     * @return  string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @param  string  $name
     *
     * @return  self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of price
     *
     * @return  int
     */
    public function getShippingFee()
    {
        return $this->shippingFee;
    }

    /**
     * Set the value of price
     *
     * @param  int|float  $price
     *
     * @return  self
     */
    public function setShippingFee($price)
    {
        $this->shippingFee = $price;

        return $this;
    }

    public function toArray()
    {
        return [
            'Id' => $this->getId(),
            'Carrier' => $this->getCarrier(),
            'Name' => $this->getName(),
            'ShippingFee' => $this->getShippingFee()
        ];
    }
}