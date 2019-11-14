<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Svea\Checkout\Controller\Checkout;
use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;

class Push extends Checkout implements CsrfAwareActionInterface
{
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('sid');
        $result = $this->jsonResultFactory->create();
        $pushRepo = $this->pushRepositoryFactory->create();

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        try {
            $sveaOrder = $this->loadSveaOrder($orderId);
        } catch (\Exception $e) {
            $result->setHttpResponseCode(404);
            return $result;
        }

        for ($i =0; $i<5; $i++) {
            try {
                $push = $pushRepo->get($sveaOrder->getOrderId());
                if (!$push->getOrderId()) {
                    sleep(5);
                    continue;
                }
            } catch (NoSuchEntityException $e) {
                sleep(5);
                continue;
            }

            try {
                $order = $this->loadOrder($push->getOrderId());
                $this->getSveaCheckout()->getLogger()->info("Svea Push: Loaded Magento order");

                // setAdditionalInformation() might be deprecated!
                $order->getPayment()->setAdditionalInformation("svea_payment_method", $sveaOrder->getPaymentType());
                $this->sveaCheckoutContext->getOrderRepository()->save($order);
            } catch (\Exception $e) {
                // ignore
                $this->getSveaCheckout()->getLogger()->error("Svea Push: Could not set svea payment method to order");

            }

            // if we get here it means the push is saved to the database and an order id exists!
            $result->setHttpResponseCode(200);
            return $result;
        }

        $result->setHttpResponseCode(404);
        return $result;
    }


    public function loadSveaOrder($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        try {
            $sveaOrder = $checkout->getSveaPaymentHandler()->loadSveaOrderById($sveaOrderId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Push: The svea order with ID: " . $sveaOrderId . " was not found in svea.");
                throw new CheckoutException(__("Found no Svea Order for this session. Please refresh the site or clear your cookies."));
            } else {
                $checkout->getLogger()->error("Push: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Push: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                throw new CheckoutException(__("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin."));
            }
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Push: Something went wrong. Might have been the request parser. Order ID: " . $sveaOrderId . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Something went wrong... Contact site admin."));
        }

        return $sveaOrder;
    }


    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function loadOrder($orderId)
    {
        return $this->sveaCheckoutContext->getOrderRepository()->get($orderId);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
