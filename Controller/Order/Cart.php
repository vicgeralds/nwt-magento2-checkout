<?php
namespace Svea\Checkout\Controller\Order;

class Cart extends \Svea\Checkout\Controller\Order\Update
{
    public function execute(){
        return $this->_sendResponse(['cart','coupon','shipping','messages','svea'],$updateCheckout = true);
    }
}