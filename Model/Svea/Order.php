<?php

namespace Svea\Checkout\Model\Svea;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Svea\Checkout\Model\Client\Api\Checkout;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\CancelOrderAmount;
use Svea\Checkout\Model\Client\DTO\CreateOrder;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\GetDeliveryResponse;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\Address;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\DTO\RefundPaymentAmount;
use Svea\Checkout\Model\Client\DTO\UpdateOrderCart;

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

    /** @var \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper */
    protected $sveaCheckoutReferenceHelper;

    protected $iframeSnippet = null;

    public function __construct(
        \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi,
        \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi,
        \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper,
        \Svea\Checkout\Helper\Data $helper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        Items $itemsHandler,
        Locale $locale
    ) {
        $this->helper = $helper;
        $this->items = $itemsHandler;
        $this->checkoutApi = $checkoutApi;
        $this->sveaCheckoutReferenceHelper = $sveaCheckoutReferenceHelper;
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
    public function assignQuote(Quote $quote, $validate = true)
    {
        if ($validate) {
            if (!$quote->hasItems()) {
                throw new LocalizedException(__('Empty Cart'));
            }
            if ($quote->getHasError()) {
                throw new LocalizedException(__('Cart has errors, cannot checkout.'));
            }
        }

        $this->_quote = $quote;
        return $this;
    }

    /**
     * @param Quote $quote
     * @return GetOrderResponse
     * @throws \Exception
     */
    public function initNewSveaCheckoutPaymentByQuote(\Magento\Quote\Model\Quote $quote)
    {
        $paymentResponse = $this->createNewSveaPayment($quote);
        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
        return $paymentResponse;
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
     * @throws ClientException
     */
    public function updateCheckoutPaymentByQuoteAndOrderId(Quote $quote, $paymentId)
    {
        $items = $this->items->generateOrderItemsFromQuote($quote);
        $items = $this->items->fixCartItems($items);

        $payment = new UpdateOrderCart();
        $payment->setItems($items);
        $payment->setMerchantData($this->generateMerchantData());

        $paymentResponse = $this->checkoutApi->updateOrder($payment, $paymentId);

        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
    }

    /**
     * @param Quote $quote
     * @return string
     */
    protected function generateMerchantData()
    {
        return json_encode([
            "quote_id" => $this->getRefHelper()->getQuoteId(),
            "client_order_number" => $this->getRefHelper()->getClientOrderNumber(),
        ]);
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
        $countryCode = $quote->getBillingAddress()->getCountryId();
        if (!in_array($countryCode, $this->getLocale()->getAllowedCountries())) {
            throw new \Exception("The country is not supported.");
        }

        $sveaHash = $this->getRefHelper()->getSveaHash();

        $isTestMode = $this->helper->isTestMode();
        $refId = $this->getRefHelper()->getClientOrderNumber();

        // generate items
        $items = $this->items->generateOrderItemsFromQuote($quote);
        $items = $this->items->fixCartItems($items);

        // set merchant settings, urls
        $merchantUrls = new MerchantSettings();
        $merchantUrls->setCheckoutUri($this->helper->getCheckoutUrl());
        $merchantUrls->setTermsUri($this->helper->getTermsUrl());
        $merchantUrls->setConfirmationUri($this->helper->getConfirmationUrl($sveaHash));
        $merchantUrls->setPushUri($this->helper->getPushUrl($sveaHash));

        if ($isTestMode && $this->helper->useLocalhost())  {
            // when testing in localhost we don't set a validation callback uri, cuz it will always fail!

        } else {
            $merchantUrls->setCheckoutValidationCallBackUri($this->helper->getValidationUrl($sveaHash));
        }

        // we generate the order here, amount and items
        $paymentOrder = new CreateOrder();

        $paymentOrder->setLocale($this->getLocale()->getLocaleByCountryCode($countryCode));
        $paymentOrder->setCountryCode($countryCode);
        $paymentOrder->setCurrency($quote->getStore()->getCurrentCurrencyCode());
        $paymentOrder->setClientOrderNumber($refId);
        $paymentOrder->setMerchantData($this->generateMerchantData());
        $paymentOrder->setMerchantSettings($merchantUrls);
        $paymentOrder->setCartItems($items);

        // set preset values if test mode! we could also set values if customer is logged in
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

        // now call the api
        return $this->checkoutApi->createNewOrder($paymentOrder);
    }

    /**
     * @param GetOrderResponse $payment
     * @param Address $address
     * @param null $countryIdFallback
     * @return array
     */
    public function convertSveaAddressToMagentoAddress(GetOrderResponse $payment, Address $address)
    {
        if ($address=== null) {
            return [];
        }

        $streets = [];
        if (is_array($address->getAddressLines())) {
            $streets = $address->getAddressLines();
        } else {
            $streets[] = $address->getStreetAddress();
        }

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
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');
        if ($sveaOrderId) {

            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order'));
            }

            if ($sveaOrder->canCancel()) {
                // cancel it now!
                $this->cancelSveaPaymentById($sveaOrderId);
            } else {

                // NOT ALL orders are cancelable, direct payments which gets an delivery deirectly in their system must be refunded instead!


                if ($sveaOrder->canRefund()) {
                    $deliveryToRefund = $sveaOrder->getFirstRefundableDelivery();

                    switch ($deliveryToRefund->getRefundType()) {
                        case "rows":
                            // if we can refund we do it instead!
                            $paymentObj = new RefundPayment();
                            $paymentObj->setOrderRowIds($deliveryToRefund->getCreditableRowsIds());

                            // try to refund it now!
                            $this->orderManagementApi->refundPayment($paymentObj, $sveaOrderId, $deliveryToRefund->getId());
                            break;
                        case "amount":
                            $paymentObj = new RefundPaymentAmount();
                            $paymentObj->setCreditedAmount($deliveryToRefund->getDeliveryAmount());
                            $this->orderManagementApi->refundPaymentAmount($paymentObj, $sveaOrderId, $deliveryToRefund->getId());
                            break;
                        default:
                            throw new LocalizedException(
                                __('Could not cancel order. Not marked as cancelable in Svea, and its missing deliveries!')
                            );
                    }


                } else {
                    throw new LocalizedException(
                        __('Could not cancel order. Not marked as cancelable in Svea, and its missing deliveries!')
                    );
                }
            }
        } else {
            throw new LocalizedException(
                __('You need an svea payment ID to void.')
            );
        }
    }

    /**
     * @param $sveaOrderId
     * @throws ClientException
     */
    public function cancelSveaPaymentById($sveaOrderId)
    {
        $this->orderManagementApi->cancelOrder($this->generateCancelOrderObject(), $sveaOrderId);
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
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');
        if ($sveaOrderId) {

            /** @var Invoice $invoice */
            $invoice = $payment->getCapturedInvoice(); // we get this from Observer\PaymentCapture
            if (!$invoice) {
                throw new LocalizedException(__('Cannot capture online, no invoice set'));
            }

            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order'));
            }

            // some orders are already delivered! i.e direct payments...
            if ($sveaOrder->canDeliver()) {
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
                $response = $this->orderManagementApi->deliverOrder($paymentObj, $sveaOrderId);

                // save queue_id, we need it later! if a refund will be made
                $payment->setAdditionalInformation('svea_queue_id', $response->getQueueId());
                $payment->setTransactionId($response->getQueueId());
            } else {

                // so we guess its a direct payment, since you cant deliver this order.
                // we save some info if client wants to refund later!

                $delivery = $sveaOrder->getFirstDeliveredDelivery();
                if ($delivery) {
                    // we set the id here so we can refund it later :)
                    $payment->setAdditionalInformation('svea_delivery_id', $delivery->getId());
                    $payment->setTransactionId($delivery->getId());
                }
            }


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
        $deliveryId = $payment->getAdditionalInformation('svea_delivery_id');
        $queueId = $payment->getAdditionalInformation('svea_queue_id');
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');

        if ($sveaOrderId && ($queueId || $deliveryId)) {

            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order'));
            }


            $deliveryToRefund = null;
            if ($queueId) {

                // not sure if this is good, but we have the  queue_id, and can retrieve the delivery from it!
                // or we could just loop through $sveaOrder->getDeliveries() and take the first one... that would be one less api call!
                $responseArray = $this->orderManagementApi->getTask($queueId);
                if (isset($responseArray['Status']) && $responseArray['Status'] === "InProgress") {
                    throw new LocalizedException(__("This delivery is still in progress. Try again soon."));
                }

                if (!isset($responseArray['Deliveries'][0])) {
                    throw new LocalizedException(__("Found no deliveries to refund on. Please refund offline, and do the rest manually in Svea."));
                }

                $deliveryArray = $responseArray['Deliveries'][0];
                $deliveryToRefund = new GetDeliveryResponse($deliveryArray);
            } else {
                foreach ($sveaOrder->getDeliveries() as $delivery) {
                    if ($delivery->getId() == $deliveryId) {
                        $deliveryToRefund = $delivery;
                        breaK;
                    }
                }
            }

            // wasn't found :/
            if (!$deliveryToRefund) {
                throw new LocalizedException(__("Found no deliveries to refund on. Please refund offline, and do the rest manually in Svea."));
            }


            // the creditmemo from the payment/invoice
            /** @var Creditmemo $creditMemo */
            $creditMemo = $payment->getCreditMemo();

            $creditMemoTotal = $creditMemo->getGrandTotal();
            $invoiceFeeRow = $deliveryToRefund->getInvoiceFeeRow();

            // convert credit memo to svea items!
            $this->items->addSveaItemsByCreditMemo($creditMemo);

            // we only refund invoice fee if its a full refund!
            if ($this->isFullRefund($this->items->getCart(), $deliveryToRefund->getCreditableItems())) {

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

            // last validation! we do it here cuz me need amount
            if (!$deliveryToRefund->canRefund() && $sveaOrder->canCancelAmount()) {
                $amountToCancel = $this->items->getSveaOrderAmountByItems($deliveryToRefund->getCartItems(), $this->items->getCart());
                $this->cancelDeliveryAmount($sveaOrderId, $amountToCancel);
                return;
            } else if (!$deliveryToRefund->canRefund() && !$sveaOrder->canCancelAmount()) {
                throw new LocalizedException(__("Can't refund this invoice. Please refund offline, and do the rest manually in Svea."));
            }

            switch ($deliveryToRefund->getRefundType()) {
                case "rows":
                    // if we can refund we do it instead!

                    try {
                        // we need order row ids
                        $rowIds = $this->items->getOrderRowNumbers($deliveryToRefund->getCreditableItems(), $this->items->getCart());
                    } catch (\Exception $e) {
                        throw new LocalizedException(__('Could not map order row numbers, so we cannot perform this action. Please do it manually'));
                    }

                    // refund request
                    $paymentObj = new RefundPayment();
                    $paymentObj->setOrderRowIds($rowIds);

                    // try to refund it now!
                    $this->orderManagementApi->refundPayment($paymentObj, $sveaOrderId, $deliveryToRefund->getId());

                    break;
                case "amount":

                    // we calculate the amount to send to svea, according to the rows existing in the svea delivery and magento!
                    $amountToCredit = $this->items->getSveaOrderAmountByItems($deliveryToRefund->getCreditableItems(), $this->items->getCart());

                    $paymentObj = new RefundPaymentAmount();
                    $paymentObj->setCreditedAmount($amountToCredit);
                    $this->orderManagementApi->refundPaymentAmount($paymentObj, $sveaOrderId, $deliveryToRefund->getId());
                    break;
                default:
                    throw new LocalizedException(
                        __('Could not refund invoice. This delivery is not marked as refundable in Svea.')
                    );
            }

        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Missing Svea ID or delivery id. Please handle this manually.')
            );
        }
    }

    /**
     * @param $sveaOrderId
     * @param $amount
     * @throws LocalizedException
     */
    public function cancelDeliveryAmount($sveaOrderId,$amount)
    {
        $paymentObj = new CancelOrderAmount();
        $paymentObj->setCancelledAmount($amount);
        try {

            $this->orderManagementApi->cancelOrderAmount($paymentObj, $sveaOrderId);
        } catch (\Exception $e) {
            throw new LocalizedException(__("Can't cancel delivery amount. Use the Offline button and do the rest manually in Svea."));
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

    public function getRefHelper()
    {
        return $this->sveaCheckoutReferenceHelper;
    }
}
