<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Model\Client\ClientException;

class ValidateOrder extends Update
{



    public function execute()
    {

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        $checkoutOrderId = $this->getCheckoutSession()->getSveaOrderId();
        $quote = $this->getSveaCheckout()->getQuote();

        if (!$checkoutOrderId) {
            $checkout->getLogger()->error("Validate Order: Found no svea order ID.");
            return $this->respondWithError("Your session has expired, found no svea order id.");
        }


        if (!$quote) {
            $checkout->getLogger()->error("Validate Order: No quote found for this customer.");
            return $this->respondWithError("Your session has expired, found no quote.");
        }


        try {
            $payment = $checkout->getSveaPaymentHandler()->loadSveaOrderById($checkoutOrderId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Validate Order: The svea order with ID: " . $checkoutOrderId . " was not found in svea.");
                return $this->respondWithError("Found no Svea Order for this session. Please refresh the site or clear your cookies.");
            } else {
                $checkout->getLogger()->error("Validate Order: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Validate Order: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                // todo show error to customer in magento! order could not be placed
                return $this->respondWithError("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin.");

            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong.')
            );

            $checkout->getLogger()->error("Validate Order: Something went wrong. Might have been the request parser. Order ID: ". $checkoutOrderId. "... Error message:" . $e->getMessage());
            return $this->respondWithError("Something went wrong... Contact site admin.");
        }

        if ($payment->getShippingAddress() === null) {
            $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
            return $this->respondWithError("Please add shipping information.");
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
                return $this->respondWithError("The country or postal code doesn't match with the one you entered earlier. Please re-enter the new postal code for the shipping above.", true, [
                    'postalCode' => $currentPostalCode, 'countryId' => $currentCountryId
                ]);
            }

            if (!$quote->getShippingAddress()->getShippingMethod()) {
                $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
                return $this->respondWithError("Please choose a shipping method.", true, [
                    'postalCode' => $currentPostalCode, 'countryId' => $currentCountryId
                ]);
            }

        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong.')
            );

            $checkout->getLogger()->error("Validate Order: Something went wrong... Order ID: ". $checkoutOrderId. "... Error message:" . $e->getMessage());
            return $this->respondWithError("Something went wrong... Contact site admin.");
        }


        $this->getResponse()->setBody(json_encode(array('chooseShippingMethod' => false, 'error' => false)));
        return false;
    }

    protected function respondWithError($message,$chooseShippingMethod = false, $extraData = [])
    {
        $data = array('messages' => $message, "chooseShippingMethod" => $chooseShippingMethod, 'error' => true);
        $data = array_merge($data, $extraData);
        $this->getResponse()->setBody(json_encode($data));
        return false;
    }
}