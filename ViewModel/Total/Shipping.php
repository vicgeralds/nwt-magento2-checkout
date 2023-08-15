<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Total;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Svea\Checkout\Helper\Data;

class Shipping implements ArgumentInterface
{
    private Session $checkoutSession;

    private Data $configHelper;

    public function __construct(
        Session $checkoutSession,
        Data $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $helper;
    }

    public function getCheckoutSession(): Session
    {
        return $this->checkoutSession;
    }

    public function getConfigHelper(): Data
    {
        return $this->configHelper;
    }
}
