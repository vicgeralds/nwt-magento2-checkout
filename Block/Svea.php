<?php
namespace Svea\Checkout\Block;


class Svea extends \Magento\Framework\View\Element\Template
{


    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    protected $getCurrentSveaPaymentIdService;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $coreRegistry,
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Svea\Checkout\Service\GetCurrentSveaPaymentId $getCurrentSveaPaymentIdService,
     * @param array $data
     */

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Service\GetCurrentSveaPaymentId $getCurrentSveaPaymentIdService,
        array $data = []
    )
    {

        $this->getCurrentSveaPaymentIdService = $getCurrentSveaPaymentIdService;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }


    public function getSveaLocale()
    {
        // Todo remove hardcode
        return "sv-SE";
    }

    public function getSveaCheckoutKey()
    {
        return $this->getHelper()->getMerchantId();
    }


    public function getSveaPaymentId()
    {
        return $this->getCurrentSveaPaymentIdService->getSveaPaymentId();
    }

    public function getHelper(){
        return $this->helper;
    }
 }

