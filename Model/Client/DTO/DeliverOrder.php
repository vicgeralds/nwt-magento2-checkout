<?php
namespace Svea\Checkout\Model\Client\DTO;


use Svea\Checkout\Model\Client\DTO\Order\OrderRow;

class DeliverOrder extends AbstractRequest
{

    /**
     * Required
     * @var $items OrderRow[]
     */
    protected $items;

    /**
     * @return OrderRow[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param OrderRow[] $items
     * @return DeliverOrder
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }




    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {
        $items = [];
        if (!empty($this->getItems())) {
            foreach ($this->getItems() as $item) {
                $items[] = $item->toArray();
            }
        }

        return [
            'orderItems' => $items,
        ];
    }


}