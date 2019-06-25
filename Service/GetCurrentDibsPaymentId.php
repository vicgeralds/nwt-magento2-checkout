<?php


namespace Svea\Checkout\Service;


class GetCurrentSveaPaymentId extends GetCurrentQuote
{

    public function getSveaPaymentId()
    {
        return $this->checkoutSession->getSveaPaymentId();
    }
}