<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Svea\Checkout\Model\CheckoutException;

class Confirmation extends Update
{
    public function execute()
    {
        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);
        $sveaHash = $this->getRequest()->getParam('hash'); // for security!

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
        if (!$quoteId) {
            $checkout->getLogger()->critical(sprintf("Confirmation Error: Quote ID missing %s.", $quote->getId()));
        }

        // compare the hashes, no one should access this without permissions
        if ($quote->getSveaHash() !== $sveaHash) {
            $checkout->getLogger()->error(sprintf("Validate Order: The quote hash (%s) does not match the request hash (%s)." . $quote->getSveaHash(), $sveaHash));
            $this->messageManager->addErrorMessage("An error occurred...");
            return $this->_redirect('*');
        }

        if (!$sveaOrderId = $checkout->getRefHelper()->getSveaOrderId()) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Svea Order Not found. Quote ID %s.", $quote->getId()));
            $this->messageManager->addErrorMessage(sprintf("Missing Svea Order ID. Please contact the website admin with this quote id: %s.", $quote->getId()));
            return $this->_redirect('*');
        }


        $pushRepo = $this->pushRepositoryFactory->create();
        try {
            $push = $pushRepo->get($sveaOrderId);
            if (!$push->getOrderId()) {
                throw new NoSuchEntityException(__("Order id missing"));
            }

        } catch (NoSuchEntityException $e) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Push missing, i.e order has not been placed, quote id: %s.", $quote->getId()));
            $this->messageManager->addErrorMessage(sprintf("Missing Order ID. Order seems not to be placed, please contact the website admin with this quote id: %s.", $quote->getId()));
            return $this->_redirect('*');
        }


        $lastOrderId = $push->getOrderId();

        // clear old sessions
        $session = $this->getCheckoutSession();
        $session->clearHelperData();
        $session->clearQuote()->clearStorage();

        // try load order by id
        try {
            $order = $this->loadOrder($lastOrderId);
        } catch (\Exception $e) {
            // If there is an order, but we couldn't load it due to technical problems, this could in worst cases lead to the user places an new order...

            $checkout->getLogger()->error(sprintf("Confirmation Error: Could not load Order, quote id: %s last order id: %s. Error: %s", $quote->getId(), $lastOrderId, $e->getMessage()));
            $this->messageManager->addErrorMessage(sprintf("Could not continue. Please contact the website admin, with this quote id: %s and this order id: %s.", $quoteId, $lastOrderId));
            return $this->_redirect('*');
        }

        // unset our checkout sessions
        $this->getSveaCheckout()->getRefHelper()->unsetSessions(true, true);



        // add order information to the session
        $session
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());


        // we set new sessions
        $session
            ->setSveaOrderId($sveaOrderId) // we need this in the success page
            ->setLastQuoteId($order->getQuoteId()) // we need this in the success page
            ->setLastSuccessQuoteId($order->getQuoteId());


        return $this->_redirect('*/*/success');
    }


    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function loadOrder($orderId)
    {
        return $this->sveaCheckoutContext->getOrderRepository()->get($orderId);
    }
}
