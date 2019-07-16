<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Svea\Checkout\Controller\Checkout;

class Push extends Checkout
{
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('sid');
        $result = $this->jsonResultFactory->create();
        $pushRepo = $this->pushRepositoryFactory->create();

        for ($i =0; $i<5; $i++) {
            try {
                $push = $pushRepo->get($orderId);
                if (!$push->getOrderId()) {
                    sleep(5);
                    continue;
                }
            } catch (NoSuchEntityException $e) {
                sleep(5);
                continue;
            }

            // if we get here it means the push is saved to the database and an order id exists!
            $result->setHttpResponseCode(200);
            return $result;
        }

        $result->setHttpResponseCode(404);
        return $result;
    }
}
