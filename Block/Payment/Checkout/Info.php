<?php

namespace Svea\Checkout\Block\Payment\Checkout;


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
}
