<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;

class ValidateOrder extends Update
{

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('sid');

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        $result = $this->jsonResultFactory->create();

        // one step at the time, all exceptions will be caught
        try {

            // check if svea order id is correct
            $this->validateSveaOrderId($orderId);

            // load svea order if it exists
            $sveaOrder = $this->loadSveaOrder($orderId);

            // load quote if it exists
            $quote = $this->loadQuote($sveaOrder->getMerchantData()->getQuoteId());

            // check if everything is valid
            $this->validateOrder($sveaOrder,$quote);

            // we try to create the order now ;)
            $order = $this->placeOrder($sveaOrder,$quote);

        } catch (CheckoutException $e) {

            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => $e->getMessage(), 'Valid' => false]);
            return $result;
        } catch (\Exception $e) {

            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => "Could not place order", 'Valid' => false]);
            return $result;
        }

        return $result->setData(['Valid' => true, 'ClientOrderNumber' => $order->getIncrementId()]);
    }


    /**
     * @param $sveaOrderId
     * @throws CheckoutException
     */
    protected function validateSveaOrderId($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        if (!$sveaOrderId) {
            $checkout->getLogger()->error("Validate Order: Found no svea order ID.");
            return $this->throwCheckoutException("Found no svea order id.");
        }

        if (!is_integer($sveaOrderId)) {
            $checkout->getLogger()->error("Validate Order: The Svea Order ID is invalid!");
            return $this->throwCheckoutException("The Svea Order ID is invalid.");
        }

    }

    /**
     * @param $sveaOrderId
     * @return \Svea\Checkout\Model\Client\DTO\GetOrderResponse|void
     * @throws CheckoutException
     */
    public function loadSveaOrder($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        try {
            $sveaOrder = $checkout->getSveaPaymentHandler()->loadSveaOrderById($sveaOrderId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Validate Order: The svea order with ID: " . $sveaOrderId . " was not found in svea.");
                return $this->throwCheckoutException("Found no Svea Order for this session. Please refresh the site or clear your cookies.");
            } else {
                $checkout->getLogger()->error("Validate Order: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Validate Order: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                // todo show error to customer in magento! order could not be placed
                return $this->throwCheckoutException("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin.");

            }
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order: Something went wrong. Might have been the request parser. Order ID: ". $sveaOrderId. "... Error message:" . $e->getMessage());
            return $this->throwCheckoutException("Something went wrong... Contact site admin.");
        }

        return $sveaOrder;
    }

    /**
     * @param $quoteId int
     * @return Quote|void
     * @throws CheckoutException
     */
    public function loadQuote($quoteId)
    {
        try {
            $quote = $this->loadQuoteById($quoteId);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Validate Order: We found no quote for this Svea order.");
            return $this->throwCheckoutException("Found no quote object for this Svea order ID.");
        }

        return $quote;
    }

    /**
     * @param $sveaOrder GetOrderResponse
     * @param $quote Quote
     * @return void
     * @throws CheckoutException
     */
    public function validateOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {

        $checkout = $this->getSveaCheckout();

        if ($sveaOrder->getShippingAddress() === null) {
            $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
            return $this->throwCheckoutException("Please add shipping information.");
        }

        $currentPostalCode = $sveaOrder->getShippingAddress()->getPostalCode();
        $currentCountryId = $sveaOrder->getCountryCode();
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
                $checkout->getLogger()->error("Validate Order: Consumer has not chosen a shipping method.");
                return $this->throwCheckoutException("Please choose a shipping method.");
            }

        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order: Something went wrong... Order ID: ". $sveaOrder->getOrderId(). "... Error message:" . $e->getMessage());
            return $this->throwCheckoutException("Something went wrong... Contact site admin.");
        }

    }

    /**
     * @param GetOrderResponse $sveaOrder
     * @param Quote $quote
     * @return Order
     */
    public function placeOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {
        // TODO
        return null;
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