<?php
namespace Svea\Checkout\Block;


class Script extends \Magento\Framework\View\Element\Template
{

    const SVEA_JAVASCRIPT_TEST = "https://test.checkout.sveapayment.eu/v1/checkout.js?v=1";
    const SVEA_JAVASCRIPT_LIVE = "https://checkout.sveapayment.eu/v1/checkout.js?v=1";

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;


    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Svea\Checkout\Helper\Data $helper
     * @param array $data
     */

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Svea\Checkout\Helper\Data $helper,
        array $data = []
    )
    {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }


    public function getSource()
    {
        if ($this->helper->isTestMode()) {
            return  self::SVEA_JAVASCRIPT_TEST;
        } else {
            return self::SVEA_JAVASCRIPT_LIVE;
        }

    }

}

