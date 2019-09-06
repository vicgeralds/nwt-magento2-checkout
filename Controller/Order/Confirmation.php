<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

class Confirmation extends Update
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);
        $sveaHash = $this->getRequest()->getParam('hash'); // for security!

        // it seems like we dont have quote information here, so we try to load everything with the svea_order_id instead!
        if (!$sveaOrderId = $checkout->getRefHelper()->getSveaOrderId()) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Svea Order Not found. Svea Order ID %s.", $sveaOrderId));
            $this->messageManager->addErrorMessage(sprintf("Missing Svea Order ID. Please try again."));
            return $this->_redirect('*');
        }

        // a helper for all people testing in localhost!
        if ($this->getSveaCheckout()->getHelper()->isTestMode() && $this->getSveaCheckout()->getHelper()->useLocalhost()) {
            $this->createMockCallbacks($sveaHash, $sveaOrderId);
        }

        $pushRepo = $this->pushRepositoryFactory->create();
        try {
            $push = $pushRepo->get($sveaOrderId);
            if (!$push->getOrderId()) {
                throw new NoSuchEntityException(__("Order id missing"));
            }
        } catch (NoSuchEntityException $e) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Push missing, i.e order has not been placed, svea id: %s.", $sveaOrderId));
            $this->messageManager->addErrorMessage(sprintf("An error occured. Could not fetch the new order. Please contact the website admin with this svea id: %s.", $sveaOrderId));
            return $this->_redirect('*');
        }

        // try load order by id
        $lastOrderId = $push->getOrderId();
        try {
            $order = $this->loadOrder($lastOrderId);
        } catch (\Exception $e) {
            // If there is an order, but we couldn't load it due to technical problems, this could in worst cases lead to the user places an new order...

            $checkout->getLogger()->error(sprintf("Confirmation Error: Could not load Order, push id: %s last order id: %s. Error: %s", $push->getId(), $lastOrderId, $e->getMessage()));
            $this->messageManager->addErrorMessage(sprintf("Could not continue. Please contact the website admin, with this push id: %s and this order id: %s.", $push->getId(), $lastOrderId));
            return $this->_redirect('*');
        }

        try {
            $quote = $this->loadQuoteById($order->getQuoteId());
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Confirmation Order: We found no quote for this Svea order.");
            $this->messageManager->addErrorMessage((__("Found no quote object for this Svea order ID.")));
            return $this->_redirect('*');
        }

        // compare the hashes, no one should access this without permissions
        if ($quote->getSveaHash() !== $sveaHash) {
            $checkout->getLogger()->error(sprintf("Confirmation Order: The quote hash (%s) does not match the request hash (%s) for order id (%s).", $quote->getSveaHash(), $sveaHash, $push->getOrderId()));
            $this->messageManager->addErrorMessage(__("An error occurred..."));
            return $this->_redirect('*');
        }

        // unset our checkout sessions
        $this->getSveaCheckout()->getRefHelper()->unsetSessions(true, true);
        // clear old sessions
        $session = $this->getCheckoutSession();
        $session->clearHelperData();
        $session->clearQuote()->clearStorage();

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

    /**
     * @param $quoteId
     * @return Quote
     */
    protected function loadQuoteById($quoteId)
    {
        return $this->quoteFactory->create()->loadByIdWithoutStore($quoteId);
    }


    /**
     * This function is only used in testmode and when the module is installed in localhost
     * The purpose is to send mocked requests with the ValidationnURI, where the order is placed.
     *
     * @param $hash
     * @param $sveaOrderId
     */
    protected function createMockCallbacks($hash, $sveaOrderId)
    {
        $helper = $this->getSveaCheckout()->getHelper();
        $url =  $helper->getCheckoutUrl('validateOrder', ['sid'=> $sveaOrderId, 'hash' => $hash, '_escape_params' => false]);
        $this->getSveaCheckout()->getLogger()->info(sprintf("Trying to manually call the validation URL, for svea order ID: %s.", $sveaOrderId));
        $this->getSveaCheckout()->getLogger()->info("URL: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        $errors = curl_error($ch);

        if ($errors) {
            $this->getSveaCheckout()->getLogger()->error($errors);
        }

        curl_close($ch);

        $this->getSveaCheckout()->getLogger()->info("RESULT From callback: (should not be empty): " . $result);

    }

}
