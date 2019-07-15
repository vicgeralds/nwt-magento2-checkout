<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Sales\Model\Order;

class Confirmation extends Update
{
    public function execute()
    {
        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        $quote = $checkout->getQuote();
        if (!$quote) {
            // What if the quote session expires after the order is placed in magento? It should never happen
            // but if it does, the customer will never be redirected to the success page
            // however the user should receive an e-mail confirmation with all info...
            $checkout->getLogger()->error("Confirmation Error: Quote not found.");
            $this->messageManager->addErrorMessage("Your session has expired...");
            return $this->_redirect('*');
        }

        $quoteId = $quote->getId();
        if (!$sveaOrderId = $quote->getSveaOrderId()) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Svea Order Not found in Quote, i.e order has not been placed, quote id: %s.", $quote->getId()));
            $this->messageManager->addErrorMessage(sprintf("Missing Svea Order ID. Order seems not to be placed, please contact the website admin with this quote id: %s.", $quote->getId()));
            return $this->_redirect('*');
        }

        $lastOrderId = $quote->getReservedOrderId();
        if (!$lastOrderId) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Missing Last Order ID, i.e order has not been placed, quote id: %s.", $quote->getId()));
            $this->messageManager->addErrorMessage(sprintf("Missing Order ID. Order seems not to be placed, please contact the website admin with this quote id: %s.", $quote->getId()));
            return $this->_redirect('*');
        }

        // clear old sessions
        $session = $this->getCheckoutSession();
        $session->clearHelperData();
        $session->clearQuote()->clearStorage();

        // try load order by id
        try {
            $order = $this->loadOrder($lastOrderId);
        } catch (\Exception $e) {
            // If there is an order, but we couldn't load it due to technical problems, this could in worst cases lead to the user places an new order...

            $checkout->getLogger()->error(sprintf("Confirmation Error: Could not load Order, quote id: %s last order id: %s.", $quote->getId(), $lastOrderId));
            $this->messageManager->addErrorMessage(sprintf("Could not continue. Please contact the website admin, with this quote id: %s and this order id: %s.", $quoteId, $lastOrderId));
            return $this->_redirect('*');
        }

        // unset our checkout sessions
        $this->getSveaCheckout()->getRefHelper()->unsetSessions(true);

        // we set new sessions
        $session
            ->setSveaOrderId($sveaOrderId) // we need this in the success page
            ->setLastQuoteId($quoteId) // we need this in the success page
            ->setLastSuccessQuoteId($order->getQuoteId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        return $this->_redirect('*/*/success');
    }

    /**
     * @param $orderId
     * @return Order
     * @throws \Exception
     */
    protected function loadOrder($orderId)
    {
        // TODO
    }
}
