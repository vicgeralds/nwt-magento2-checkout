<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Model\CheckoutException;

class Confirmation extends Update
{
    public function execute()
    {

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);
        $cancelOrder = false;
        $errorMessage = "";

        try {
            $checkout->tryToSaveSveaPayment(null, true);
        } catch (CheckoutException $e) {
            $cancelOrder = true;
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $cancelOrder = true;
            $errorMessage = __("Something went wrong. Try again.");
        }

        // success
        if (!$cancelOrder) {
            return $this->_redirect('*/*/success');
        }

        // an error occured todo cancel order
        $this->messageManager->addErrorMessage($errorMessage);
        return $this->_redirect('*');

    }

}