<?php

namespace Svea\Checkout\Block\Payment\Checkout;

use Svea\Checkout\Model\Client\Api\OrderManagement;

/**
 * @method OrderManagement getSveaOrderManagement()
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Svea_Checkout::payment/checkout/info.phtml';

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Svea_Checkout::payment/checkout/pdf.phtml');
        return $this->toHtml();
    }

    public function getSveaPaymentMethod()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_payment_method');
        } catch (\Exception $e) {
            return "";
        }
    }

    public function getSveaCheckoutId()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_order_id');
        } catch (\Exception $e) {
            return "";
        }
    }

    public function getSveaBillingReferences(): array
    {
        // Only company orders paid with invoice has billing references
        // We skip the API call to check for them in other cases
        $isCompany = $this->getInfo()->getAdditionalInformation('is_company');
        $isInvoicePayment = ('INVOICE' === $this->getSveaPaymentMethod());
        if (!$isCompany || !$isInvoicePayment) {
            return [];
        }

        $handler = $this->getSveaOrderManagement();
        $handler->resetCredentials($this->getMethod()->getStore());
        try {
            $sveaOrder = $handler->getOrder($this->getSveaCheckoutId());
            return $sveaOrder->getBillingReferences();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSveaCustomerReference()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_customer_reference');
        } catch (\Exception $e) {
            return "";
        }
    }
}
