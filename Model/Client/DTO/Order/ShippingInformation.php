<?php

namespace Svea\Checkout\Model\Client\DTO\Order;

use Magento\Quote\Model\Quote;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Svea\Checkout\Model\Client\DTO\AbstractRequest;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\FallbackOptionFactory;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\FallbackOption;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\Tags;

class ShippingInformation extends AbstractRequest
{
    /**
     * @var ShippingMethodManagementInterface
     */
    private $shipMethodManagement;

    /**
     * @var FallbackOptionFactory
     */
    private $fallbackOptFactory;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var bool
     */
    private $enableShipping;

    /**
     * @var bool
     */
    private $enforceFallback = false;

    /**
     * @var int
     */
    private $weight;

    /**
     * @var Tags
     */
    private $tags;

    /**
     * @var FallbackOption[]
     */
    private $fallbackOptions;

    public function __construct(
        ShippingMethodManagementInterface $shipMethodManagement,
        FallbackOptionFactory $fallbackOptFactory,
        Data $helper
    ) {
        $this->shipMethodManagement = $shipMethodManagement;
        $this->fallbackOptFactory = $fallbackOptFactory;
        $this->helper = $helper;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = [
            'EnableShipping' => $this->getEnableShipping(),
            'EnforceFallback' => $this->getEnforceFallback(),
            'Weight' => $this->getWeight(),
            'Tags' => $this->getTags()->toArray(),
            'FallbackOptions' => [],
        ];

        foreach ($this->getFallbackOptions() as $fallbackOption) {
            $array['FallbackOptions'][] = $fallbackOption->toArray();
        }

        return array_filter($array);
    }

    /**
     * Get the value of enableShipping
     */
    public function getEnableShipping()
    {
        return $this->enableShipping;
    }

    /**
     * Set the value of enableShipping
     *
     * @return  self
     */
    public function setEnableShipping($enableShipping)
    {
        $this->enableShipping = $enableShipping;

        return $this;
    }

    /**
     * Get the value of enforceFallback
     */
    public function getEnforceFallback()
    {
        return $this->enforceFallback;
    }

    /**
     * Set the value of enforceFallback
     *
     * @return  self
     */
    public function setEnforceFallback($enforceFallback)
    {
        $this->enforceFallback = $enforceFallback;

        return $this;
    }

    /**
     * Get the value of weight
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Set the value of weight
     *
     * @return  self
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * Get the value of tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set the value of tags
     *
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get the value of fallbackOptions
     *
     * @return FallbackOption[]
     */
    public function getFallbackOptions()
    {
        return $this->fallbackOptions;
    }

    /**
     * Set the value of fallbackOptions
     *
     * @param FallbackOption[] $fallbackOptions
     *
     * @return  self
     */
    public function setFallbackOptions($fallbackOptions)
    {
        $this->fallbackOptions = $fallbackOptions;

        return $this;
    }

    public function generateFromQuote(Quote $quote): ShippingInformation
    {
        $enableShipping = !$quote->getIsVirtual();
        $this->setEnableShipping($enableShipping);
        $this->setEnforceFallback($this->helper->getSveaShippingEnforceFallback());
        $this->setWeight($this->addZeroes($quote->getShippingAddress()->getWeight()));

        try {
            $fallbackOptions = [];
            $methods = $this->shipMethodManagement->getList(
                $quote->getId(),
                $quote->getShippingAddress()->getId()
            );
    
            foreach ($methods as $method) {
                if (Carrier::CODE === $method->getCarrierCode()) {
                    continue;
                }

                $fallbackOptions[] = $this->fallbackOptFactory->create()
                    ->setId($method->getMethodCode())
                    ->setCarrier($method->getCarrierCode())
                    ->setName(__($method->getCarrierTitle())->getText())
                    ->setPrice($method->getAmount())
                ;
            }
            $this->setFallbackOptions($fallbackOptions);
        } finally {
            return $this;
        }
    }
}
