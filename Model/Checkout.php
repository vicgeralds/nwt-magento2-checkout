<?php

namespace Svea\Checkout\Model;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;

class Checkout extends \Magento\Checkout\Model\Type\Onepage
{
    protected $_paymentMethod = 'sveacheckout';

    /** @var CheckoutContext $context */
    protected $context;

    protected $_allowedCountries;

    protected $_doNotMarkCartDirty  = false;

    /**
     * @param CheckoutContext $context
     */
    public function setCheckoutContext(CheckoutContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->context->getHelper();
    }

    /**
     * @return CheckoutOrderNumberReference
     */
    public function getRefHelper()
    {
        return $this->context->getSveaCheckoutReferenceHelper();
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * @param bool $reloadIfCurrencyChanged
     * @return $this
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function initCheckout($reloadIfCurrencyChanged = true)
    {
        if (!($this->context instanceof CheckoutContext)) {
            throw new \Exception("Svea Context must be set first!");
        }

        $quote  = $this->getQuote();
        $this->checkCart();

        //init checkout
        $customer = $this->getCustomerSession();
        if ($customer->getId()) {
            $quote->assignCustomer($customer->getCustomerDataObject()); //this will set also primary billing/shipping address as billing address
            $quote->setCustomer($customer->getCustomerDataObject());
        }

        $allowCountries = $this->getAllowedCountries(); //this is not null (it is checked in $this->checkCart())
        $defaultCountry = $this->getHelper()->getDefaultCountry();

        $billingAddress  = $quote->getBillingAddress();
        if ($quote->isVirtual()) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingAddress = $quote->getShippingAddress();
        }

        if (!$shippingAddress->getCountryId()) {
            $this->_logger->info(__("No country set, change to %1", $defaultCountry));
            $this->changeCountry($defaultCountry, $save = false);
        } elseif (!in_array($shippingAddress->getCountryId(), $allowCountries)) {
            $this->_logger->info(__("Wrong country set %1, change to %2", $shippingAddress->getCountryId(), $defaultCountry));
            $this->messageManager->addNoticeMessage(__("Svea checkout is not available for %1, country was changed to %2.", $shippingAddress->getCountryId(), $defaultCountry));
            $this->changeCountry($defaultCountry, $save = false);
        }

        if (!$billingAddress->getCountryId() || $billingAddress->getCountryId() != $shippingAddress->getCountryId()) {
            //$this->_logger->info(__("Billing country [%1] != shipping [%2]",$billingAddress->getCountryId(),$shippingAddress->getCountryId()));
            $this->changeCountry($shippingAddress->getCountryId(), $save = false);
        }

        $currencyChanged = $this->checkAndChangeCurrency();
        $payment = $quote->getPayment();

        //force payment method  to our payment method
        $paymentMethod     = $payment->getMethod();

        $shipPaymentMethod = $shippingAddress->getPaymentMethod();

        if (!$paymentMethod || !$shipPaymentMethod || $paymentMethod != $this->_paymentMethod || $shipPaymentMethod != $paymentMethod) {
            $payment->unsMethodInstance()->setMethod($this->_paymentMethod);
            $quote->setTotalsCollectedFlag(false);
            //if quote is virtual, shipping is set as billing (see above)
            //setCollectShippingRates because in onepagecheckout is affirmed that shipping rates could depends by payment method
            $shippingAddress->setPaymentMethod($payment->getMethod())->setCollectShippingRates(true);
        }

        try {
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save(); //REQUIRED (maybe shipping amount was changed)
        } catch (\Exception $e) {
            // do nothing
        }

        $billingAddress->save();
        $shippingAddress->save();

        $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);
        $this->totalsCollector->collectQuoteTotals($quote);

        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        if ($currencyChanged && $reloadIfCurrencyChanged) {
            //not needed
            $this->throwReloadException(__('Checkout was reloaded.'));
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getQuoteSignature()
    {
        return $this->getHelper()->generateHashSignatureByQuote($this->getQuote());
    }

    /**
     * @return bool
     * @throws CheckoutException
     */
    public function checkCart()
    {
        $quote = $this->getQuote();

        //@see OnePage::initCheckout
        if ($quote->getIsMultiShipping()) {
            $quote->setIsMultiShipping(false)->removeAllAddresses();
        }

        if (!$this->getHelper()->isEnabled()) {
            $this->throwRedirectToCartException(__('The Svea Checkout is not enabled, please use an alternative checkout method.'));
        }

        if (!$this->getAllowedCountries()) {
            $this->throwRedirectToCartException(__('The Svea Checkout is NOT available (no allowed country), please use an alternative checkout method.'));
        }

        if (!$quote->hasItems()) {
            $this->throwRedirectToCartException(__('There are no items in your cart.'));
        }

        if ($quote->getHasError()) {
            $this->throwRedirectToCartException(__('The cart contains errors.'));
        }

        if (!$quote->validateMinimumAmount()) {
            $error =$this->getHelper()->getStoreConfig('sales/minimum_order/error_message');
            if (!$error) {
                $error = __('Subtotal must exceed minimum order amount.');
            }

            $this->throwRedirectToCartException($error);
        }

        return true;
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    public function checkAndChangeCurrency()
    {
        $quote  = $this->getQuote();
        $store  = $quote->getStore();
        $country    = $quote->getBillingAddress()->getCountryId();
        $currentCurrency = $quote->getQuoteCurrencyCode();
        $requiredCurrency = $this->getSveaPaymentHandler()->getlocale()->getCurrencyByCountryCode($country);

        if (!$country || !$requiredCurrency) {
            throw new LocalizedException(__('Country is not set.')); // this shouldn't happen
        }

        if ($requiredCurrency == $currentCurrency) {
            //currency not changed
            return false;
        }

        // this will try to change currency only if currency is available
        $store->setCurrentCurrencyCode($requiredCurrency);

        // check if it was possible to set the currency code!
        if ($store->getCurrentCurrency()->getCode() != $requiredCurrency) {
            $this->throwRedirectToCartException(__('This currency is not available, please use an alternative checkout.'));
        }

        $quote->setTotalsCollectedFlag(false);
        if (!$quote->isVirtual() && $quote->getShippingAddress()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        // we add a message
        $this->messageManager->addNoticeMessage(__('Currency was changed to %1.', $requiredCurrency));

        //currency was changed
        return true;
    }

    /**
     * @param $country
     * @param bool $saveQuote
     * @throws LocalizedException
     */
    public function changeCountry($country, $saveQuote = false)
    {
        $allowCountries = $this->getAllowedCountries();
        if (!$country || !in_array($country, $allowCountries)) {
            throw new LocalizedException(__('Invalid country (%1)', $country));
        }

        $blankAddress = $this->getBlankAddress($country);
        $quote        = $this->getQuote();

        $quote->getBillingAddress()->addData($blankAddress);
        if (!$quote->isVirtual()) {
            $quote->getShippingAddress()->addData($blankAddress)->setCollectShippingRates(true);
        }
        if ($saveQuote) {
            $this->checkAndChangeCurrency();
            $quote->collectTotals()->save();
        }
    }

    /**
     * @param $country
     * @return array
     */
    public function getBlankAddress($country)
    {
        $blankAddress = [
            'customer_address_id'=>0,
            'save_in_address_book'=>0,
            'same_as_billing'=>0,
            'street'=>'',
            'city'=>'',
            'postcode'=>'',
            'region_id'=>'',
            'country_id'=>$country
        ];
        return $blankAddress;
    }

    /**
     * @return array
     */
    public function getAllowedCountries()
    {
        if (is_null($this->_allowedCountries)) {
            $this->_allowedCountries = $this->getHelper()->getCountries();
        }

        return $this->_allowedCountries;
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    public function initSveaCheckout()
    {
        $quote       = $this->getQuote();
        $sveaHandler = $this->getSveaPaymentHandler()->assignQuote($quote); // this will also validate the quote!

        // a signature is a md5 hashed value of the customer quote. Using this we can store the hash in session and compare the values
        $newSignature = $this->getHelper()->generateHashSignatureByQuote($quote);

        //check session for Svea Order Id
        $sveaOrderId = $this->getRefHelper()->getSveaOrderId();

        // check if we already have started a payment flow with svea
        if ($sveaOrderId) {
            try {

                // here we should check if we need to update the svea order!
                if ($sveaHandler->checkIfPaymentShouldBeUpdated($newSignature, $this->getRefHelper()->getQuoteSignature())) {
                    // try to update svea order data
                    $sveaHandler->updateCheckoutPaymentByQuoteAndOrderId($quote, $sveaOrderId);

                    // Update new svea quote signature!
                    $this->getRefHelper()->setQuoteSignature($newSignature);
                } else {

                    // if we should update the order, we also set the svea iframe here
                    $sveaOrder = $sveaHandler->loadSveaOrderById($sveaOrderId, true);

                    // do some validations!
                    // if the svea order status is final, and the client order number matches with the current quote
                    // we will cancel this svea order and throw an exception ( a new svea order will be created),
                    $this->validateCheckoutSveaOrder($sveaOrder);
                }
            } catch (\Exception $e) {

                // We log this!
                $this->getLogger()->error("Trying to create an new order because we could not Update Svea Checkout Payment for ID: {$sveaOrderId}, Error: {$e->getMessage()} (see exception.log)");
                $this->getLogger()->error($e);
                // If we couldn't update the svea order flow for any reason, we try to create an new one...

                // remove sessions, remove client order number
                $this->getRefHelper()->unsetSessions();

                // will help us reassure client order number will be unique
                $this->getRefHelper()->addToSequence();

                try {
                    // this will create an api call to svea and initiaze an new payment
                    $sveaOrder = $sveaHandler->initNewSveaCheckoutPaymentByQuote($quote);
                    $sveaOrderId = $sveaOrder->getOrderId();

                    //save the payment id and quote signature in checkout/session
                    $this->getRefHelper()->setSveaOrderId($sveaOrderId);
                    $this->getRefHelper()->setQuoteSignature($newSignature);
                } catch (\Exception $e2) {
                    $this->getLogger()->error("Could not create an new order again. " . $e2->getMessage());
                    $this->getLogger()->error($e2);

                    $this->throwRedirectToCartException("An error occurred, try again.", $e2);
                }
            }
        } else {
            // when a customer visits checkout first time

            try {
                // this will create an api call to svea and initiaze a new payment
                $sveaOrder = $sveaHandler->initNewSveaCheckoutPaymentByQuote($quote);

                // do some validations!
                // if the svea order status is final, and the client order number matches with the current quote
                // we will cancel this svea order and throw an exception ( a new svea order will be created),
                $this->validateCheckoutSveaOrder($sveaOrder);


                //save svea uri in checkout/session
                $sveaOrderId = $sveaOrder->getOrderId();
                $this->getRefHelper()->setSveaOrderId($sveaOrderId);
                $this->getRefHelper()->setQuoteSignature($newSignature);
            } catch (\Exception $e) {
                $this->getLogger()->error("Could not create an new order: " . $e->getMessage());
                $this->getLogger()->error($e);

                // remove sessions, remove client order number
                $this->getRefHelper()->unsetSessions();

                // will help us reassure client order number will be unique
                $this->getRefHelper()->addToSequence();

                $this->throwRedirectToCartException("An error occurred, try again.", $e);
            }
        }

        return $this;
    }

    /**
     * @param $sveaOrder GetOrderResponse
     * @throws \Exception
     */
    private function validateCheckoutSveaOrder($sveaOrder)
    {
        if ($sveaOrder->getStatus() === 'Final') {

            if ($this->getRefHelper()->clientIdIsMatching($sveaOrder->getClientOrderNumber())) {
                try {
                    $this->context->getSveaOrderHandler()->cancelSveaPaymentById($sveaOrder->getOrderId());
                } catch (\Exception $e) {
                    // do nothing!
                }
            }

            throw new \Exception("This order is already placed in Svea. Creating a new.");
        }

        if ($sveaOrder->getStatus() === "Cancelled") {
            throw new \Exception("This order is already placed in Svea and has been cancelled.");
        }
    }

    /**
     * @param $sveaOrderId
     * @throws ClientException
     * @throws LocalizedException
     */
    public function updateSveaPayment($sveaOrderId)
    {
        $quote       = $this->getQuote();
        $sveaHandler = $this->getSveaPaymentHandler()->assignQuote($quote); // this will also validate the quote!

        // a signature is a md5 hashed value of the customer quote. Using this we can store the hash in session and compare the values
        $newSignature = $this->getHelper()->generateHashSignatureByQuote($quote);

        $sveaHandler->updateCheckoutPaymentByQuoteAndOrderId($quote, $sveaOrderId);

        // Update new svea quote signature!
        $this->getRefHelper()->setQuoteSignature($newSignature);
    }

    //Checkout ajax updates

    /**
     * Set shipping method to quote, if needed
     *
     * @param string $methodCode
     * @return void
     */
    public function updateShippingMethod($methodCode)
    {
        $quote = $this->getQuote();
        if ($quote->isVirtual()) {
            return;
        }
        $shippingAddress = $quote->getShippingAddress();
        if ($methodCode != $shippingAddress->getShippingMethod()) {
            $this->ignoreAddressValidation();
            $shippingAddress->setShippingMethod($methodCode)->setCollectShippingRates(true);
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        }
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @return void
     */
    private function ignoreAddressValidation()
    {
        $quote = $this->getQuote();
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$quote->getIsVirtual()) {
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    /**
     * @param GetOrderResponse $sveaOrder
     * @param Quote $quote
     * @return mixed
     * @throws \Exception
     */
    public function placeOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {

        //prevent observer to mark quote dirty, we will check here if quote was changed and, if yes, will redirect to checkout
        $this->setDoNotMarkCartDirty(true);

        //this will be saved in order
        $quote->setSveaOrderId($sveaOrder->getOrderId());

        // we convert the addresses
        $shipping = $this->getSveaPaymentHandler()->convertSveaAddressToMagentoAddress($sveaOrder, $sveaOrder->getShippingAddress());
        $billing = $this->getSveaPaymentHandler()->convertSveaAddressToMagentoAddress($sveaOrder, $sveaOrder->getBillingAddress());

        // we set the addresses
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->addData($billing)
            ->setCustomerAddressId(0)
            ->setSaveInAddressBook(0)
            ->setShouldIgnoreValidation(true);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($shipping)
            ->setSameAsBilling(1)
            ->setCustomerAddressId(0)
            ->setSaveInAddressBook(0)
            ->setShouldIgnoreValidation(true);

        $quote->setCustomerEmail($billingAddress->getEmail());

        $customer      = $quote->getCustomer(); //this (customer_id) is set into self::init
        $createCustomer = false;

        if ($customer && $customer->getId()) {
            $quote->setCheckoutMethod(self::METHOD_CUSTOMER)
                ->setCustomerId($customer->getId())
                ->setCustomerEmail($customer->getEmail())
                ->setCustomerFirstname($customer->getFirstname())
                ->setCustomerLastname($customer->getLastname())
                ->setCustomerIsGuest(false);
        } else {
            //checkout method
            $quote->setCheckoutMethod(self::METHOD_GUEST)
                ->setCustomerId(null)
                ->setCustomerEmail($billingAddress->getEmail())
                ->setCustomerFirstname($billingAddress->getFirstname())
                ->setCustomerLastname($billingAddress->getLastname())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            // register the customer, if its required, the customer will then be registered after order is placed
            if ($billingAddress->getEmail() && $this->getHelper()->registerCustomerOnCheckout()) {
                if (!$this->_customerEmailExists($billingAddress->getEmail(), $quote->getStore()->getWebsiteId())) {
                    $createCustomer = true;
                }
            }
        }

        //set payment
        $payment = $quote->getPayment();

        //force payment method
        if (!$payment->getMethod() || $payment->getMethod() != $this->_paymentMethod) {
            $payment->unsMethodInstance()->setMethod($this->_paymentMethod);
        }

        $paymentData = (new DataObject())
            ->setSveaOrderId($sveaOrder->getOrderId())
            ->setCountryId($shippingAddress->getCountryId());

        $quote->getPayment()->getMethodInstance()->assignData($paymentData);
        $quote->setSveaOrderId($sveaOrder->getOrderId()); //this is used by pushAction

        // we need to add invoice fee here to order if its enabled
        if ($sveaInvoiceFeeRow = $this->getInvoiceFeeRow($sveaOrder->getCartItems())) {
            $fee  = $sveaInvoiceFeeRow->getUnitPrice() / 100;
            $quote->setSveaInvoiceFee($fee);
         //   $quote->setGrandTotal($quote->getGrandTotal() + $fee);
         //   $quote->setBaseGrandTotal($quote->getGrandTotal() + $fee);

            $quote->collectTotals();
        }

        //- do not recollect totals
        $quote->setTotalsCollectedFlag(true);

        //!
        // Now we create the order from the quote
        $order = $this->quoteManagement->submit($quote);

        $this->_eventManager->dispatch(
            'checkout_type_onepage_save_order_after',
            ['order' => $order, 'quote' => $this->getQuote()]
        );

        if ($order->getCanSendNewEmailFlag()) {
            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }

        $this->_eventManager->dispatch(
            'checkout_submit_all_after',
            [
                'order' => $order,
                'quote' => $this->getQuote()
            ]
        );

        if ($createCustomer) {
            //@see Magento\Checkout\Controller\Account\Create
            try {
                $this->context->getOrderCustomerManagement()->create($order->getId());
            } catch (\Exception $e) {
                $this->_logger->error(__("Order %1, cannot create customer [%2]: %3", $order->getIncrementId(), $order->getCustomerEmail(), $e->getMessage()));
                $this->_logger->critical($e);
            }
        }

        if ($order->getCustomerEmail() && $this->getHelper()->subscribeNewsletter($this->getQuote())) {
            try {
                //subscribe to newsletter
                $this->orderSubscribeToNewsLetter($order);
            } catch (\Exception $e) {
                $this->_logger->error("Cannot subscribe customer ({$order->getCustomerEmail()}) to the Newsletter: " . $e->getMessage());
            }
        }

        return $order;
    }

    /**
     * @param $orderItems array
     * @return OrderRow|null
     */
    public function getInvoiceFeeRow($orderItems)
    {
        foreach ($orderItems as $item) {
            /** @var $item OrderRow */
            if ($item->getName() === "InvoiceFee") {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     * @throws \Exception
     */
    protected function orderSubscribeToNewsLetter(\Magento\Sales\Model\Order $order)
    {
        $email = $order->getCustomerEmail();
        if (!$email) {
            return false;
        }

        $subscriber = $this->context->getSubscriber();
        $subscriber->loadByEmail($email);

        if ($subscriber->getId()) {
            return false;
        }

        return $subscriber->subscribe($email);
    }

    /**
     * @param $message
     * @param $exception
     * @throws CheckoutException
     */
    protected function throwRedirectToCartException($message, $exception = null)
    {
        if (($exception instanceof \Exception) && $this->getHelper()->isTestMode()) {
            $message = __($message . " Error: %1", $exception->getMessage());
        }

        throw new CheckoutException($message, 'checkout/cart');
    }

    /**
     * @param $message
     * @throws CheckoutException
     */
    protected function throwReloadException($message)
    {
        throw new CheckoutException($message, '*/*');
    }

    /**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     * @codeCoverageIgnore
     */
    public function getCheckoutSession()
    {
        return $this->_checkoutSession; //@see Onepage::__construct
    }

    /** @return \Svea\Checkout\Model\Svea\Order */
    public function getSveaPaymentHandler()
    {
        return $this->context->getSveaOrderHandler();
    }

    /**
     * @param $markDirty
     */
    public function setDoNotMarkCartDirty($markDirty)
    {
        $this->_doNotMarkCartDirty = (bool) $markDirty;
    }

    /**
     * @return bool
     */
    public function getDoNotMarkCartDirty()
    {
        return $this->_doNotMarkCartDirty;
    }
}
