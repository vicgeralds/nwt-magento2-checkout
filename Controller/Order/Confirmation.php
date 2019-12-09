<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

class Confirmation extends Push implements CsrfAwareActionInterface
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
        $push = null;
        for ($i = 0; $i<4;$i++) {
            try {
                $push = $pushRepo->get($sveaOrderId);
                if ($push->getOrderId()) {
                   break;
                }
            } catch (NoSuchEntityException $e) {
                // do nothing
                $push = null;
            }

            // sleep for 5 seconds, we wait for the push!
            sleep(5);
        }

        // the push should have been created in validateOrder, should be long before we are here...
        if (!$push ) {
            $checkout->getLogger()->error(sprintf("Confirmation Error: Could not create order. No push found.Svea order id: %s.", $sveaOrderId));
            $this->messageManager->addErrorMessage(sprintf("Could not create order. Please contact the website admin. Svea Order ID: %s.", $sveaOrderId));
            return $this->_redirect('*');
        }

        // if the push hasn't created an order yet, (we have waited 20 seconds), we will try to create the order here!
        if (!$push->getOrderId()) {
            try {
                $lastOrderId = $this->tryToCreateOrder($sveaOrderId, $sveaHash);

                if (is_bool($lastOrderId) || !$lastOrderId) {
                    throw new \Exception("Could not create order.");
                }
            } catch (\Exception $e) {
                $checkout->getLogger()->error(sprintf("Confirmation Error: Could not create order, push id: %s svea order id: %s. Error: %s", $push->getId(), $sveaOrderId, $e->getMessage()));
                $this->messageManager->addErrorMessage(sprintf("Could not create order. Please contact the website admin, with this push id: %s and this order id: %s.", $push->getId(), $sveaOrderId));
                return $this->_redirect('*');
            }
        } else {
            $lastOrderId = $push->getOrderId();
        }



        // try load order by id
        try {
            $order = $this->loadOrder($lastOrderId);
        } catch (\Exception $e) {
            // If there is an order, but we couldn't load it due to technical problems, this could in worst cases lead to the user places an new order...

            $checkout->getLogger()->error(sprintf("Confirmation Error: Could not load Order, push id: %s last order id: %s. Error: %s", $push->getId(), $lastOrderId, $e->getMessage()));
            $this->messageManager->addErrorMessage(sprintf("Could not continue. Please contact the website admin, with this push id: %s and this order id: %s.", $push->getId(), $lastOrderId));
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
     * This function is only used in testmode and when the module is installed in localhost
     * The purpose is to send mocked requests with the ValidationnURI, where the order is placed.
     *
     * Would be better to use ngrok or other tunnel to setup callback urls locally.
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

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
