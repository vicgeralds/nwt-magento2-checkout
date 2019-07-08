<?php
namespace Svea\Checkout\Model\Client\DTO;

use Svea\Checkout\Model\Client\DTO\Order\Address;
use Svea\Checkout\Model\Client\DTO\Order\Customer;
use Svea\Checkout\Model\Client\DTO\Order\GetOrder;
use Svea\Checkout\Model\Client\DTO\Order\Gui;
use Svea\Checkout\Model\Client\DTO\Order\IdentityFlags;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class GetDeliveryResponse
{

    private $_data;

    /** @var OrderRow[] $cartItems */
    protected $cartItems;

    /** @var $canCreditOrderRows bool */
    protected $canCreditOrderRows;

    /** @var $id int */
    protected $id;

    /**
     * CreatePaymentResponse constructor.
     * @param $response string
     */
    public function __construct($response = null)
    {
        if ($response == null) {
            return;
        }

        if (is_string($response)) {
            $data = json_decode($response, true);
        } else {
            // we think its an array!
            $data = $response;
        }

        $this->_data = $data;


        $actions = $this->get('Actions');
        $actions = is_array($actions) ? $actions : [];

        $this->setId($this->get('Id'));
        $this->setCanCreditOrderRows(in_array('CanCreditOrderRows', $actions));


        if (isset($data['OrderRows'])) {
            $items = $data['OrderRows'];
            $orderRows = [];
            foreach ($items as $item) {

                // we only take rows that we can credit
                if (!in_array("CanCreditRow", $item['Actions'])) {
                    continue;
                }

                $orderRow = new OrderRow();

                // fill
                $orderRow
                    ->setArticleNumber($item['ArticleNumber'])
                    ->setName($item['Name'])
                    ->setQuantity($item['Quantity'])
                    ->setUnitPrice($item['UnitPrice'])
                    ->setUnit($item['Unit'])
                    ->setDiscountPercent($item['DiscountPercent'])
                    ->setVatPercent($item['VatPercent'])
                    ->setRowNumber($item['OrderRowId']);

                // add to array
                $orderRows[] = $orderRow;
            }

            $this->setCartItems($orderRows);
        }
    }

    /**
     * @return OrderRow[]
     */
    public function getCartItems()
    {
        return is_array($this->cartItems) ? $this->cartItems : [];
    }

    /**
     * @param OrderRow[] $cartItems
     * @return GetDeliveryResponse
     */
    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCanCreditOrderRows()
    {
        return $this->canCreditOrderRows;
    }

    /**
     * @param bool $canCreditOrderRows
     * @return GetDeliveryResponse
     */
    public function setCanCreditOrderRows($canCreditOrderRows)
    {
        $this->canCreditOrderRows = $canCreditOrderRows;
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
     * @return GetDeliveryResponse
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    // Helpers!


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

    private function get($key)
    {
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }

        return null;
    }

}