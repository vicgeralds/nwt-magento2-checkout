<?php


namespace Svea\Checkout\Service;

use Magento\Quote\Model\Quote;

class GetCurrentQuote
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    protected $currentQuote;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return Quote
     */
    public function getQuote(): Quote
    {
        if ($this->currentQuote === null) {
            $this->currentQuote = $this->checkoutSession->getQuote();
        }

        return $this->currentQuote;
    }
}