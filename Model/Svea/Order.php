<?php


namespace Svea\Checkout\Model\Svea;


use Magento\Sales\Model\Order\Creditmemo;
use Svea\Checkout\Model\Client\Api\Checkout;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\CreateOrder;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\GetDeliveryResponse;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;
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
     * @ar Locale $_locale;
     */
    protected $_locale;

    /**
     * @var \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi
     */
    protected $checkoutApi;


    /**
     * @var \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi
     */
    protected $orderManagementApi;


    /**
     * @var \Svea\Checkout\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $_countryFactory;

    protected $iframeSnippet = null;


    public function __construct(
        \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi,
        \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi,
        \Svea\Checkout\Helper\Data $helper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        Items $itemsHandler,
        Locale $locale
    ) {
        $this->helper = $helper;
        $this->items = $itemsHandler;
        $this->checkoutApi = $checkoutApi;
        $this->orderManagementApi = $orderManagementApi;
        $this->_countryFactory  = $countryFactory;
        $this->_locale = $locale;
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
     * @return int
     * @throws \Exception
     */
    public function initNewSveaCheckoutPaymentByQuote(\Magento\Quote\Model\Quote $quote)
    {
        // todo check if country is cvalid
        //  if(!$this->getOrderAdapter()->orderDataCountryIsValid($data,$country)){
        //    throw new Exception
        //}


        $paymentResponse = $this->createNewSveaPayment($quote);
        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
        return $paymentResponse->getOrderId();
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
     * @return void
     * @throws \Exception
     */
    public function updateCheckoutPaymentByQuoteAndOrderId(Quote $quote, $paymentId)
    {
        // TODO handle this exception?
        $items = $this->items->generateOrderItemsFromQuote($quote);

        $payment = new UpdateOrderCart();
        $payment->setItems($items);
        $payment->setMerchantData($this->generateReferenceByQuoteId($quote->getId()));

        $paymentResponse = $this->checkoutApi->updateOrder($payment, $paymentId);

        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
    }


    /**
     * This function will create a new svea payment.
     * The payment ID which is returned in the response will be added to the SVEA javascript API, to load the payment iframe.
     *
     * @param Quote $quote
     * @throws ClientException|\Exception
     * @return GetOrderResponse
     */
    protected function createNewSveaPayment(Quote $quote)
    {
        $isTestMode = $this->helper->isTestMode();
        $countryCode = $quote->getShippingAddress()->getCountryId();
        $items = $this->items->generateOrderItemsFromQuote($quote);
        $refId = $this->generateReferenceByQuoteId($quote->getId());


        $merchantUrls = new MerchantSettings();
        $merchantUrls->setCheckoutUri($this->helper->getCheckoutUrl());
        $merchantUrls->setTermsUri($this->helper->getTermsUrl());
        $merchantUrls->setConfirmationUri($this->helper->getConfirmationUrl($isTestMode));
        $merchantUrls->setPushUri($this->helper->getPushUrl($isTestMode));
        //$merchantUrls->setCheckoutValidationCallBackUri($this->helper->getValidationUrl($mode));



        // we generate the order here, amount and items
        $paymentOrder = new CreateOrder();

        $paymentOrder->setLocale($this->getLocale()->getLocaleByCountryCode($countryCode));
        $paymentOrder->setCountryCode($countryCode);
        $paymentOrder->setCurrency($quote->getCurrency()->getQuoteCurrencyCode());
        $paymentOrder->setClientOrderNumber($refId);
        $paymentOrder->setMerchantData($refId); // could be more data
        $paymentOrder->setMerchantSettings($merchantUrls);
        $paymentOrder->setCartItems($items);

        if ($isTestMode) {
            $presetValues = [];
            $testValues = $this->getLocale()->getTestPresetValuesByCountryCode($countryCode);
            foreach ($testValues as $key => $val) {
                $presetValue = new PresetValue();
                $presetValue->setTypeName($key)->setValue($val);
                $presetValues[] = $presetValue;
            }

            if (!empty($presetValues)) {
                $paymentOrder->setPresetValues($presetValues);
            }
        }


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
    public function convertSveaShippingToMagentoAddress(GetOrderResponse $payment)
    {
        if ($payment->getShippingAddress() === null) {
            return array();
        }

        $address = $payment->getShippingAddress();

         // TODO
        $streets = [];
        if (is_array($address->getAddressLines())) {
            $streets = $address->getAddressLines();
        } else {
            $streets[] = $address->getStreetAddress();
        }

        // TODO COMPANY $data['company'] = ....


        $data = [
            'firstname' => $address->getFirstName(),
            'lastname' => $address->getLastName(),
            'telephone' => $payment->getPhoneNumber(),
            'email' => $payment->getEmailAddress(),
            'street' =>$streets,
            'city' => $address->getCity(),
            'postcode' => $address->getPostalCode(),
            'country_id' => $payment->getCountryCode(),
        ];

        if ($payment->getCustomer()->getIsCompany()) {
            $data['company'] = $payment->getBillingAddress()->getFullName();
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
            // cancel it now!
            $this->orderManagementApi->cancelOrder($this->generateCancelOrderObject(), $paymentId);

        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an svea payment ID to void.')
            );
        }
    }

    protected function generateCancelOrderObject()
    {
        $obj = new CancelOrder();
        $obj->setIsCancelled(true);
        return $obj;
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

            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->loadSveaOrderById($paymentId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order'));
            }

            // generate items
            $this->items->addSveaItemsByInvoice($invoice);

            // lets att the invoice fee if it exists!
            if ($invoiceFeeRow = $sveaOrder->getInvoiceFeeRow()) {
                $this->items->addInvoiceFeeItem($invoiceFeeRow);
            }

            // We validate the items before we send them to Svea. This might throw an exception!
            $this->items->validateTotals($invoice->getGrandTotal());

            try {
                // we need order row ids
                $rowIds = $this->items->getOrderRowNumbers($sveaOrder->getCartItems(), $this->items->getCart());
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not map order row numbers, so we cannot perform this action. Please do it manually'));
            }


            $paymentObj = new DeliverOrder();
            $paymentObj->setOrderRowIds($rowIds);

            // capture/deliver it now!
            $response = $this->orderManagementApi->deliverOrder($paymentObj, $paymentId);

            // save queue_id, we need it later! if a refund will be made
            $payment->setAdditionalInformation('svea_queue_id', $response->getQueueId());
            $payment->setTransactionId($response->getQueueId());


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
        $queueId = $payment->getAdditionalInformation('svea_queue_id');
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');

        if ($queueId && $sveaOrderId) {

            $responseArray = $this->orderManagementApi->getTask($queueId);
            if (isset($responseArray['Status']) && $responseArray['Status'] === "InProgress") {
                throw new LocalizedException(__("This delivery is still in progress. Try again soon."));
            }

            if (!isset($responseArray['Deliveries'][0])) {
                throw new LocalizedException(__("Found no deliveries to refund on. Please refund offline, and do the rest manually in Svea."));
            }

            $deliveryArray = $responseArray['Deliveries'][0];
            $delivery = new GetDeliveryResponse($deliveryArray);

            if (!$delivery->getCanCreditOrderRows()) {
                throw new LocalizedException(__("Can't refund this invoice. Please refund offline, and do the rest manually in Svea."));
            }

            // the creditmemo from the payment/invoice
            /** @var Creditmemo $creditMemo */
            $creditMemo = $payment->getCreditMemo();

            $creditMemoTotal = $creditMemo->getGrandTotal();
            $invoiceFeeRow = $delivery->getInvoiceFeeRow();

            // convert credit memo to svea items!
            $this->items->addSveaItemsByCreditMemo($creditMemo);


            // we only refund invoice fee if its a full refund!
            if ($this->isFullRefund($this->items->getCart(), $delivery->getCartItems())) {

                // lets add the invoice fee if it exists, since its a full refund!
                if ($invoiceFeeRow) {
                    $this->items->addInvoiceFeeItem($invoiceFeeRow);
                }

            } else {

                // if not a full refund and there is a invoice fee, it has to be added as an adjustment fee!
                if ($invoiceFeeRow) {
                    $invoiceFee = ($invoiceFeeRow->getUnitPrice() / 100);

                    // invoice fee is never removed from svea in partial refunds, because some issues we have in magento
                    if ($creditMemo->getAdjustmentNegative() < $invoiceFee) {
                        throw new LocalizedException(__('This is a partial credit memo. You have to add an adjustment fee that is the same amount as the svea invoice fee.'));
                    }
                }
            }


            // We validate the items before we send them to Svea. This might throw an exception!
            $this->items->validateTotals($creditMemoTotal);

            try {
                // we need order row ids
                $rowIds = $this->items->getOrderRowNumbers($delivery->getCartItems(), $this->items->getCart());
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not map order row numbers, so we cannot perform this action. Please do it manually'));
            }

            // refund request
            $paymentObj = new RefundPayment();
            $paymentObj->setOrderRowIds($rowIds);

            // try to refund it now!
            $this->orderManagementApi->refundPayment($paymentObj, $sveaOrderId, $delivery->getId());


        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You need an svea ID and Svea Delivery ID to refund.')
            );
        }
    }


    /**
     * @param $creditMemoItems array
     * @param $deliveryItems array
     * @return bool
     */
    protected function isFullRefund($creditMemoItems, $deliveryItems)
    {
        $countMemoItems = count($creditMemoItems);
        $countDeliveryItems = 0;
        foreach ($deliveryItems as $item) {
            /** @var $item OrderRow */
            if ($item->getName() === "InvoiceFee") {
                continue;
            }

            $countDeliveryItems++;
        }

        return $countMemoItems >= $countDeliveryItems;
    }


    /**
    protected function isFullRefund($creditMemoItems, $deliveryItems)
    {
        $countMemoItems = 0;
        foreach ($creditMemoItems as $creditMemoItem) {
            /** @var $creditMemoItem OrderRow *
            $countMemoItems +=  $creditMemoItem->getQuantity();
        }


        $countDeliveryItems = 0;
        foreach ($deliveryItems as $item) {
            /** @var $item OrderRow *
            if ($item->getName() === "InvoiceFee") {
                continue;
            }

            $countDeliveryItems += $item->getQuantity();
        }

        return $countMemoItems >= $countDeliveryItems;
    }
    */

    /**
     * @param $paymentId
     * @return GetOrderResponse
     * @throws ClientException
     */
    public function loadSveaOrderById($paymentId, $saveIframe = false)
    {
        $order =  $this->checkoutApi->getOrder($paymentId);
        if ($saveIframe) {
            $this->setIframeSnippet($order->getGui()->getSnippet());
        }

        return $order;
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
     * @return Checkout
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

    public function setIframeSnippet($snippet)
    {
        $this->iframeSnippet = $snippet;
    }

    public function getIframeSnippet()
    {
        return $this->iframeSnippet;
    }

    public function getLocale()
    {
        return $this->_locale;
    }
}