<?php

namespace Svea\Checkout\Model;

/**
 * We use this to generate a unique client_order_number for Svea.
 * The quote and checkout session should be valid for the same amount of time
 *
 * If a checkout order fails somehow at svea, we want to create an new one using the same quote id
 * Therefore we add an extra sequence to the client_order_id, to make it unique!
 *
 * Class CheckoutOrderNumberReference
 * @package Svea\Checkout\Model
 */
class CheckoutOrderNumberReference
{
    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = null;

    const CLIENT_ID_PREFIX = "quote_id_";

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @param \Magento\Checkout\Model\Session $_checkoutSession
     */
    public function __construct(\Magento\Checkout\Model\Session $_checkoutSession)
    {
        $this->_checkoutSession = $_checkoutSession;
    }


    /**
     * @return int|null
     */
    public function getSveaOrderId()
    {
        return $this->getCheckoutSession()->getSveaOrderId();
    }

    /**
     * @param $sveaOrderId
     * @return void
     */
    public function setSveaOrderId($sveaOrderId)
    {
        $this->getCheckoutSession()->setSveaOrderId($sveaOrderId);
    }

    /**
     * @param $signature
     * @return void
     */
    public function setQuoteSignature($signature)
    {
        $this->getCheckoutSession()->setSveaQuoteSignature($signature);
    }

    /**
     * @return string
     */
    public function getQuoteSignature()
    {
        return $this->getCheckoutSession()->getSveaQuoteSignature();
    }

    public function getQuoteId()
    {
        return $this->getQuote()->getId();
    }

    /**
     * @return string
     */
    public function generateClientOrderNumber()
    {
        $sequence = $this->getSequence();
        $cn = self::CLIENT_ID_PREFIX . $this->getQuoteId();
        if ($sequence > 1) {
            $cn = $cn . "_" . $sequence;
        }

        return $cn;
    }

    /**
     * @param $clientId
     * @return bool
     */
    public function clientIdIsMatching($clientId)
    {
        $cn = self::CLIENT_ID_PREFIX . $this->getQuoteId();
        return strpos($clientId, $cn) !== false;
    }

    /**
     * @return int
     */
    protected function getSequence()
    {
        $sequence = $this->getCheckoutSession()->getSveaCheckoutSequence();
        if ($sequence) {
            return (int) $sequence;
        }

        $this->getCheckoutSession()->setSveaCheckoutSequence(1);
        return 1;
    }

    /**
     * @return void
     */
    public function addToSequence()
    {
        $this->getCheckoutSession()->setSveaCheckoutSequence($this->getSequence() + 1);
    }


    /**
     * Quote object getter
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if ($this->_quote === null) {
            $this->_quote = $this->_checkoutSession->getQuote();
        }

        return $this->_quote;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @return $this
     */
    public function setQuote(\Magento\Quote\Model\Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function unsetSessions($unsetSequence = false)
    {

        // remove sessions
        $this->getCheckoutSession()->unsSveaOrderId(); //remove svea order id from session
        $this->unsetSveaQuoteSignature(); //remove signature from session

        if ($unsetSequence) {
            $this->unsetSequence();
        }

    }

    public function unsetSequence()
    {
        $this->getCheckoutSession()->unsSveaCheckoutSequence();
    }


    public function unsetSveaQuoteSignature()
    {
        $this->getCheckoutSession()->unsetSveaQuoteSignature();
    }


}