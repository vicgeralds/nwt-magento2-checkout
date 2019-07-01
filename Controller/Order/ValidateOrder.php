<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;

class ValidateOrder extends Update
{

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('sid');

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        $result = $this->jsonResultFactory->create();

        try {
            $this->validateOrder($orderId, true);
        } catch (CheckoutException $e) {

            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => $e->getMessage(), 'Valid' => false]);
            return $result;
        }

        return $result->setData(['Valid' => true]);
    }


    /**
     * @param null $sveaOrderId
     * @param bool $compareSveaIdWithSession
     * @return bool|void
     * @throws CheckoutException
     */
    public function validateOrder($sveaOrderId = null, $compareSveaIdWithSession = false)
    {

        $checkout = $this->getSveaCheckout();

        if ($sveaOrderId === null) {
            $checkoutOrderId = $this->getCheckoutSession()->getSveaOrderId();
        } else {
            $checkoutOrderId = $sveaOrderId;
        }

        if (!$checkoutOrderId) {
            $checkout->getLogger()->error("Validate Order: Found no svea order ID.");
            return $this->throwCheckoutException("Your session has expired, found no svea order id.");
        }

        if ($compareSveaIdWithSession && $this->getCheckoutSession()->getSveaOrderId() != $sveaOrderId) {
            $checkout->getLogger()->error("Validate Order: Svea order ID not matching");
            return $this->throwCheckoutException("Your session has expired, found no svea order id.");
        }


        $quote = $this->getSveaCheckout()->getQuote();
        if (!$quote) {
            $checkout->getLogger()->error("Validate Order: No quote found for this customer.");
            return $this->throwCheckoutException("Your session has expired, found no quote.");
        }


        try {
            $payment = $checkout->getSveaPaymentHandler()->loadSveaOrderById($checkoutOrderId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Validate Order: The svea order with ID: " . $checkoutOrderId . " was not found in svea.");
                return $this->throwCheckoutException("Found no Svea Order for this session. Please refresh the site or clear your cookies.");
            } else {
                $checkout->getLogger()->error("Validate Order: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Validate Order: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                // todo show error to customer in magento! order could not be placed
                return $this->throwCheckoutException("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin.");

            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong.')
            );

            $checkout->getLogger()->error("Validate Order: Something went wrong. Might have been the request parser. Order ID: ". $checkoutOrderId. "... Error message:" . $e->getMessage());
            return $this->throwCheckoutException("Something went wrong... Contact site admin.");
        }

        if ($payment->getShippingAddress() === null) {
            $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
            return $this->throwCheckoutException("Please add shipping information.");
        }

        $currentPostalCode = $payment->getShippingAddress()->getPostalCode();
        $currentCountryId = $payment->getCountryCode();
        // check other quote stuff

        try {

            $oldPostCode = $quote->getShippingAddress()->getPostcode();
            $oldCountryId = $quote->getShippingAddress()->getCountryId();

            // we do nothing
            if (!($oldCountryId == $currentCountryId && $oldPostCode == $currentPostalCode)) {
                $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
                return $this->throwCheckoutException("The country or postal code doesn't match with the one you entered earlier. Please re-enter the new postal code for the shipping above.");
            }

            if (!$quote->getShippingAddress()->getShippingMethod()) {
                $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
                return $this->throwCheckoutException("Please choose a shipping method.");
            }

        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong.')
            );

            $checkout->getLogger()->error("Validate Order: Something went wrong... Order ID: ". $checkoutOrderId. "... Error message:" . $e->getMessage());
            return $this->throwCheckoutException("Something went wrong... Contact site admin.");
        }


        return true;
    }


    /**
     * @param $message
     * @throws CheckoutException
     */
    protected function throwCheckoutException($message)
    {
        throw new CheckoutException($message);
    }
}