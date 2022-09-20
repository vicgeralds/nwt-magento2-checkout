<?php

namespace Svea\Checkout\Model\Client\DTO\Order\ShippingInformation;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;

class Tags extends AbstractRequest
{
    /**
     * @var array
     */
    private $tagArray = [];

    public function addTag($key, $value)
    {
        $this->tagArray[$key] = $value;
    }

    public function getTags(): array
    {
        return $this->tagArray;
    }

    public function toArray()
    {
        return $this->tagArray;
    }
}
