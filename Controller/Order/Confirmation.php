<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Controller\Checkout;
use Svea\Checkout\Model\CheckoutException;

class Confirmation extends Checkout
{
    public function execute()
    {
        $orderId  = $this->getRequest()->getParam('sid');

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        try {
            $orderPlaced = $checkout->tryToSaveSveaPayment($orderId);
        } catch (CheckoutException $e) {

            if ($e->isReload()) {
                $this->messageManager->addNoticeMessage($e->getMessage());
            } else {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            $this->_redirect("sveacheckout");
            return false;

        } catch (\Exception $e) {
            $checkout->getLogger()->error($e->getMessage());
            return $this->respondWithError("Something went wrong.");
        }

        if ($orderPlaced) {
            return $this->_redirect('*/*/success');
        } else {
            return $this->respondWithError("Unknown error.");

        }
    }


    protected function respondWithError($message)
    {
        $this->messageManager->addErrorMessage($message);
        return $this->_redirect('sveacheckout');
    }

}