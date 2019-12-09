<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Svea\Checkout\Controller\Checkout;
use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\PushRepository;

class Push extends Checkout implements CsrfAwareActionInterface
{

    /**
     * @var $pushRepo PushRepository
     */
    protected $pushRepo;
    
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('sid');
        $sveaHash = $this->getRequest()->getParam('hash');

        $result = $this->jsonResultFactory->create();
        $this->pushRepo = $this->pushRepositoryFactory->create();

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        try {
            $orderId = $this->tryToCreateOrder($orderId, $sveaHash);
            if ($orderId) {
                $result->setHttpResponseCode(200);
                return $result;
            } else {
                $result->setHttpResponseCode(404);
                return $result;
            }
        } catch (\Exception $e) {
            $result->setHttpResponseCode(404);
            return $result;
        }
    }

    public function tryToCreateOrder($orderId, $sveaHash)
    {
        try {
            $sveaOrder = $this->loadSveaOrder($orderId);
        } catch (\Exception $e) {
            throw $e;
        }

        // the push is created request in PushOrder
        try  {
            $push = $this->pushRepo->get($sveaOrder->getOrderId());
            $orderIdExists = $push->getOrderId() ? true : false;
        }catch (\Exception $e) {
            // ignore
            $this->getSveaCheckout()->getLogger()->error("Svea Push: Found no push.");

            return true;
        }

        // we are already done, stop it!
        if ($orderIdExists) {
            return $push->getOrderId();
        }

        // try to create order here, when status is final, and we haven't created an order!
        if ($sveaOrder->getStatus() === "Final" && !$orderIdExists) {
            try {
                $order = $this->createMagentoOrder($sveaOrder, $sveaHash);
            } catch (\Exception $e) {
                throw $e;
            }

            // we are almost done!
            // save order id to push that we are done!
            try {
                $push = $this->pushRepo->get($sveaOrder->getOrderId());
                $push->setOrderId($order->getId());
                $this->pushRepo->save($push);
            } catch (\Exception $e) {

            }

            return $order->getId();
        }

        return false;
    }


    protected function loadSveaOrder($sveaOrderId)
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
     * @param GetOrderResponse $sveaOrder
     * @param $sveaHash
     * @return Order
     * @throws \Exception
     */
    protected function createMagentoOrder(GetOrderResponse $sveaOrder, $sveaHash)
    {
        try {
            // load quote if it exists
            $quote = $this->loadQuote($sveaOrder->getMerchantData()->getQuoteId());

            // compare the hashes, so no one tries to create an order without our permission
            if ($quote->getSveaHash() !== $sveaHash) {
                throw new \Exception("invalid svea hash, order might have been updated");
            }

        } catch (\Exception $e) {
            throw $e;
        }

        
        // here we create the magento order
        try {
            // we try to create the order now ;)
            $order = $this->placeOrder($sveaOrder, $quote);
        } catch (\Exception $e) {
            throw $e;
        }

        // save svea payment info
        try {
            $order->getPayment()->setAdditionalInformation("svea_payment_method", $sveaOrder->getPaymentType());
            $this->sveaCheckoutContext->getOrderRepository()->save($order);
        } catch (\Exception $e) {
            // ignore
            $this->getSveaCheckout()->getLogger()->error("Svea Push: Could not set svea payment method to order");
        }
        
        return $order;
    }


    /**
     * @param GetOrderResponse $sveaOrder
     * @param Quote $quote
     * @return Order
     * @throws CheckoutException
     */
    protected function placeOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {
        try {
            /** @var $order Order */
            $order = $this->getSveaCheckout()->placeOrder($sveaOrder, $quote);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Push Order: Could not place order. Svea Order ID: " . $sveaOrder->getOrderId() . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Could not place the order."));
        }

        return $order;
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
     * @param $quoteId int
     * @return Quote|void
     * @throws CheckoutException
     */
    protected function loadQuote($quoteId)
    {
        try {
            $quote = $this->loadQuoteById($quoteId);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Push Order: We found no quote for this Svea order.");
            throw new CheckoutException(__("Found no quote object for this Svea order ID."));
        }

        return $quote;
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
