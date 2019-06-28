<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Controller\Checkout;

class Push extends Checkout
{
    public function execute()
    {
        $orderId  = $this->getRequest()->getParam('sid');
        // Todo!

        exit("ok");
    }


}