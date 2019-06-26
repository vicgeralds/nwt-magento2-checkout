<?php
namespace Svea\Checkout\Controller\Order;

class Success extends \Svea\Checkout\Controller\Checkout
{

    public function execute()
    {
        
        $session = $this->getCheckoutSession();
        if (!$this->_objectManager->get('Magento\Checkout\Model\Session\SuccessValidator')->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $lastOrderId = $session->getLastOrderId();

        // TODO load $order and show iframe (use: $session->getSveaOrderId())
        
        $session->clearQuote(); //destroy quote, unset QuoteId && LastSuccessQuoteId
        $session->unsSveaOrderId(); // destroy session

        // need to be BEFORE event dispach (GA need to have layout loaded, to set the orderIds on the block)
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__("Svea Checkout - Success"));

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order_ids' => [$lastOrderId]]
        );

        return $resultPage;
    }

}