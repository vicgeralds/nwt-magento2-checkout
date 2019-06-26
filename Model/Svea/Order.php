<?php


namespace Svea\Checkout\Model\Svea;


use Svea\Checkout\Model\Client\Api\Checkout;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\ChargePayment;
use Svea\Checkout\Model\Client\DTO\CreateOrder;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\DTO\UpdateOrderCart;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Invoice;

class Order
{

    /**
     * @var Items $items
     */
    protected $items;

    /**
     * @var \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi
     */
    protected $checkoutApi;

    /**
     * @var \Svea\Checkout\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $_countryFactory;


    public function __construct(
        \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi,
        \Svea\Checkout\Helper\Data $helper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        Items $itemsHandler
    ) {
        $this->helper = $helper;
        $this->items = $itemsHandler;
        $this->checkoutApi = $checkoutApi;
        $this->_countryFactory  = $countryFactory;

    }

    /** @var $_quote Quote */
    protected $_quote;

    /**
     * @throws LocalizedException
     * @return $this
     */
    public function assignQuote(Quote $quote,$validate = true)
    {

        if ($validate) {
            if (!$quote->hasItems()) {
                throw new LocalizedException(__('Empty Cart'));
            }
            if ($quote->getHasError()) {
                throw new LocalizedException(__('Cart has errors, cannot checkout.'));
            }

            // TOdo we should check that the currency is valid (SEK, NOK, DKK)
        }

        $this->_quote = $quote;
        return $this;
    }


    /**
     * @param Quote $quote
     * @return string
     * @throws \Exception
     */
    public function initNewSveaCheckoutPaymentByQuote(\Magento\Quote\Model\Quote $quote)
    {
        // todo check if country is cvalid
        //  if(!$this->getOrderAdapter()->orderDataCountryIsValid($data,$country)){
        //    throw new Exception
        //}


        $paymentResponse = $this->createNewSveaPayment($quote);
        return $paymentResponse->getPaymentId();
    }

    /**
     * @param $newSignature
     * @param $currentSignature
     * @return bool
     */
    public function checkIfPaymentShouldBeUpdated($newSignature, $currentSignature)
    {

        // if the current signature is not set, then we must update payment
        if ($currentSignature == "" || $currentSignature == null) {
            return true;
        }

        // if the signatures doesn't match, it must mean that the quote has been changed!
        if ($newSignature != $currentSignature) {
            return true;
        }

        // nothing happened to the quote, we dont need to update payment at svea!
        return false;
    }


    /**
     * @param Quote $quote
     * @param $paymentId
     * @return Update
     * @throws \Exception
     */
    public function updateCheckoutPaymentByQuoteAndOrderId(Quote $quote, $paymentId)
    {
        // TODO handle this exception?
        $items = $this->items->generateOrderItemsFromQuote($quote);

        $payment = new UpdateOrderCart();
        $payment->setItems($items);
        $payment->setMerchantData($this->generateReferenceByQuoteId($quote->getId()));

        return $this->checkoutApi->UpdateOrder($payment, $paymentId);
    }


    /**
     * This function will create a new svea payment.
     * The payment ID which is returned in the response will be added to the SVEA javascript API, to load the payment iframe.
     *
     * @param Quote $quote
     * @throws ClientException
     * @return GetOrderResponse
     */
    protected function createNewSveaPayment(Quote $quote)
    {

        // TODO handle this exception?
        $items = $this->items->generateOrderItemsFromQuote($quote);



        $merchantUrls = new MerchantSettings();
        //$merchantUrls->setConfirmationUri("")
        $merchantUrls->setCheckoutUri($this->helper->getCheckoutUrl());
        $merchantUrls->setTermsUri($this->helper->getTermsUrl());




        // we generate the order here, amount and items
        $paymentOrder = new CreateOrder();

        $refId = $this->generateReferenceByQuoteId($quote->getId());

        $paymentOrder->setLocale("sv-SE"); // TODO
        $paymentOrder->setCountryCode($quote->getCountry()->getId());
        $paymentOrder->setCurrency($quote->getCurrency()->getQuoteCurrencyCode());
        $paymentOrder->setClientOrderNumber($refId);
        $paymentOrder->setMerchantData($refId); // could be more data
        $paymentOrder->setMerchantSettings($merchantUrls);
        $paymentOrder->setCartItems($items);


        return $this->checkoutApi->createNewOrder($paymentOrder);
    }


    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $paymentId
     * @return void
     * @throws ClientException
     *
    public function updateMagentoPaymentReference(\Magento\Sales\Model\Order $order, $paymentId)
    {
        $reference = new UpdatePaymentReference();
        $reference->setReference($order->getIncrementId());
        $reference->setCheckoutUrl($this->helper->getCheckoutUrl());
        $this->checkoutApi->UpdatePaymentReference($reference, $paymentId);
    }
     * */


    /**
     * @param GetOrderResponse $payment
     * @param null $countryIdFallback
     * @return array
     */
    public function convertSveaShippingToMagentoAddress(GetOrderResponse $payment, $countryIdFallback = null)
    {
        if ($payment->getShippingAddress() === null) {
            return array();
        }


        $company = null;
        // if company name is set, then contact details are too
        if ($payment->getCustomer()->) {
            $companyObj = $payment->getConsumer()->getCompany();
            $contact = $companyObj->getContactDetails();
            $firstname =$contact->getFirstName();
            $lastName = $contact->getLastName();
            $company = $companyObj->getName();
            $phone = $contact->getPhoneNumber()->getPhoneNumber();
            $email = $contact->getEmail();
        } else {
            $private = $payment->getConsumer()->getPrivatePerson();
            $firstname =$private->getFirstName();
            $lastName = $private->getLastName();
            $phone = $private->getPhoneNumber()->getPhoneNumber();
            $email = $private->getEmail();
        }

        $address = $payment->getConsumer()->getShippingAddress();
        $streets[] = $address->getAddressLine1();
        if ($address->getAddressLine2()) {
            $streets[] = $address->getAddressLine2();
        }

        $data = [
            'firstname' => $firstname,
            'lastname' => $lastName,
            'company' => $company,
            'telephone' => $phone,
            'email' => $email,
            'street' => $streets,
            'city' => $address->getCity(),
            'postcode' => $address->getPostalCode(),
        ];

        try {
            $countryId = $this->_countryFactory->create()->loadByCode($address->getCountry())->getId();
        } catch (\Exception $e) {
            $countryId = $countryIdFallback;
        }

        if ($countryId) {
            $data['country_id'] = $countryId;
        }


        return $data;
    }


    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @throws ClientException
     * @throws LocalizedException
     */
    public function cancelSveaPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        $paymentId = $payment->getAdditionalInformation('svea_order_id');
        if ($paymentId) {

            // we load the payment from svea api instead, then we will get full amount!
            $payment = $this->loadSveaPaymentById($paymentId);

            $paymentObj = new CancelPayment();
            $paymentObj->setAmount($payment->getSummary()->getReservedAmount());

            // cancel it now!
            $this->checkoutApi->cancelPayment($paymentObj, $paymentId);

        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an svea payment ID to void.')
            );
        }
    }


    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @throws ClientException
     * @throws LocalizedException
     */
    public function captureSveaPayment(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $paymentId = $payment->getAdditionalInformation('svea_order_id');
        if ($paymentId) {

            /** @var Invoice $invoice */
            $invoice = $payment->getCapturedInvoice(); // we get this from Observer\PaymentCapture
            if(!$invoice) {
                throw new LocalizedException(__('Cannot capture online, no invoice set'));
            }

            // generate items
            $this->items->addSveaItemsByInvoice($invoice);

            // at this point we got VAT/Tax Rate from items above.
            if ($invoice->getSveaInvoiceFee()) {
                $this->items->addInvoiceFeeItem($this->helper->getInvoiceFeeLabel(), $invoice->getSveaInvoiceFee(), true);
            }

            // We validate the items before we send them to Svea. This might throw an exception!
            $this->items->validateTotals($invoice->getGrandTotal());

            // now we have our items...
            $captureItems = $this->items->getCart();

            $paymentObj = new ChargePayment();
            $paymentObj->setAmount($this->fixPrice($amount));
            $paymentObj->setItems($captureItems);

            // capture/charge it now!
            $response = $this->checkoutApi->chargePayment($paymentObj, $paymentId);

            // save charge id, we need it later! if a refund will be made
            $payment->setAdditionalInformation('svea_charge_id', $response->getChargeId());
            $payment->setTransactionId($response->getChargeId());


        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an svea payment ID to capture.')
            );
        }
    }


    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @throws ClientException
     * @throws LocalizedException
     */
    public function refundSveaPayment(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $chargeId = $payment->getAdditionalInformation('svea_charge_id');
        if ($chargeId) {

            $creditMemo = $payment->getCreditMemo();
            $this->items->addSveaItemsByCreditMemo($creditMemo);

            // remove svea invoice fee from amount
            if ($creditMemo->getSveaInvoiceFee()) {
                $this->items->addInvoiceFeeItem($this->helper->getInvoiceFeeLabel(), $creditMemo->getSveaInvoiceFee(), true);
            }

            // We validate the items before we send them to Svea. This might throw an exception!
            $this->items->validateTotals($creditMemo->getGrandTotal());

            $refundItems = $this->items->getCart();
            $amountToRefund = $this->fixPrice($amount);


            $paymentObj = new RefundPayment();
            $paymentObj->setAmount($amountToRefund);
            $paymentObj->setItems($refundItems);

            // refund now!
            $response = $this->checkoutApi->refundPayment($paymentObj, $chargeId);

            try {
                // save refund id, just for debugging purposes
                $payment->setAdditionalInformation('svea_refund_id', $response->getRefundId());
                $payment->setTransactionId($response->getRefundId());
            } catch (\Exception $e) {
                // do nothing we dont really  need this
            }


        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an svea charge ID to refund.')
            );
        }
    }


    /**
     * @param $paymentId
     * @return GetOrderResponse
     * @throws ClientException
     */
    public function loadSveaPaymentById($paymentId)
    {
        return $this->checkoutApi->getPayment($paymentId);
    }

    /**
     * @param $price
     * @return float|int
     */
    protected function fixPrice($price)
    {
        return $price * 100;
    }


    /**
     * @return Payment
     */
    public function getPaymentApi()
    {
        return $this->checkoutApi;
    }

    /**
     * @param $quoteId
     * @return string
     */
    public function generateReferenceByQuoteId($quoteId)
    {
       return "quote_id_" . $quoteId;
    }
}