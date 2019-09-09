<?php
namespace Svea\Checkout\Model\Client\DTO\Order;

class GetDelivery
{

    const ACTION_CAN_REFUND_ORDER = "CanCreditAmount";
    const ACTION_CAN_REFUND_ORDER_ROWS = "CanCreditOrderRows";

    /** @var OrderRow[] $cartItems */
    protected $cartItems;

    protected $cartActions = [];

    /** @var int|null */
    protected $invoiceId;

    protected $actions = [];
    
    /** @var $id int */
    protected $id;

    /**
     * @var $deliveryAmount int
     */
    protected $deliveryAmount;

    /**
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     * @return GetDelivery
     */
    public function setActions($actions)
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @return OrderRow[]
     */
    public function getCartItems()
    {
        return is_array($this->cartItems) ? $this->cartItems : [];
    }

    /**
     * @return OrderRow[]
     */
    public function getCreditableItems()
    {
        $items = [];
        foreach ($this->getCartItems() as $item)
        {
            $actions = $this->getCartActionsByRow($item->getRowNumber());
            if (!in_array("CanCreditRow", $actions)) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    public function getCreditableRowsIds()
    {
        $items = $this->getCreditableItems();
        $rowsIds = [];
        foreach ($items as $item)
        {
            $rowsIds[] = $item->getRowNumber();
        }

        return $rowsIds;
    }

    /**
     * @param OrderRow[] $cartItems
     * @return GetDelivery
     */
    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
        return $this;
    }


    /**
     * @return null|OrderRow
     */
    public function getInvoiceFeeRow()
    {
        foreach ($this->getCartItems() as $item) {
            if ($item->getName() === "InvoiceFee") {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array $cartActions
     * @return GetDelivery
     */
    public function setCartActions($cartActions)
    {
        $this->cartActions = $cartActions;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * @param int|null $invoiceId
     * @return GetDelivery
     */
    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GetDelivery
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getDeliveryAmount()
    {
        return $this->deliveryAmount;
    }

    /**
     * @param int $deliveryAmount
     * @return GetDelivery
     */
    public function setDeliveryAmount($deliveryAmount)
    {
        $this->deliveryAmount = $deliveryAmount;
        return $this;
    }



    /**
     * @return bool
     */
    public function canRefund()
    {
        return in_array(self::ACTION_CAN_REFUND_ORDER, $this->getActions()) || in_array(self::ACTION_CAN_REFUND_ORDER_ROWS, $this->getActions());
    }

    /**
     * @return string|null
     */
    public function getRefundType()
    {
        if (in_array(self::ACTION_CAN_REFUND_ORDER_ROWS, $this->getActions())) {
            return "rows";
        }

        if (in_array(self::ACTION_CAN_REFUND_ORDER, $this->getActions())) {
            return "amount";
        }

        return null;
    }

    /**
     * @return array
     */
    protected function getCartActions()
    {
        return $this->cartActions;
    }

    /**
     * @param $rowNr
     * @return array
     */
    protected function getCartActionsByRow($rowNr)
    {
        foreach ($this->getCartActions() as $nr => $actions)
        {
            if ($nr == $rowNr) {
                return $actions;
            }
        }

        return [];
    }

}