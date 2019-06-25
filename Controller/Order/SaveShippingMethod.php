<?php

namespace Svea\Checkout\Controller\Order;


class SaveShippingMethod extends \Svea\Checkout\Controller\Order\Update
{

    /**
     * Save shipping method action
     */

    public function execute()
    {
        if ($this->ajaxRequestAllowed()) {
            return;
        }

        $shippingMethod = $this->getRequest()->getPost('shipping_method', '');
        if (!$shippingMethod) {
            $this->getResponse()->setBody(json_encode(array('messages' => 'Please choose a valid shipping method.')));
            return;
        }


        if ($shippingMethod) {
            try {
                $checkout = $this->getSveaCheckout();
                $checkout->updateShippingMethod($shippingMethod);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    $e->getMessage()
                );
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('We can\'t update shipping method.')
                );
            }
        }
        $this->_sendResponse(['cart', 'coupon', 'svea_shipping_total', 'messages', 'svea', 'newsletter', 'grand_total']);
    }

}

