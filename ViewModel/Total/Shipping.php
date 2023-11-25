<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Total;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Service\SveaShippingInfo;

class Shipping implements ArgumentInterface
{
    private Session $checkoutSession;

    private Data $configHelper;

    private SveaShippingInfo $sveaShippingInfoService;

    public function __construct(
        Session $checkoutSession,
        Data $helper,
        SveaShippingInfo $sveaShippingInfoService
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $helper;
        $this->sveaShippingInfoService = $sveaShippingInfoService;
    }

    public function getCheckoutSession(): Session
    {
        return $this->checkoutSession;
    }

    public function getConfigHelper(): Data
    {
        return $this->configHelper;
    }

    public function getSveaShippingInfoService(): SveaShippingInfo
    {
        return $this->sveaShippingInfoService;
    }
}
