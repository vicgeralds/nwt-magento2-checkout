<?php declare(strict_types=1);

namespace Svea\Checkout\Block\Total;

use Magento\Checkout\Block\Total\DefaultTotal;
use Magento\Framework\Exception\LocalizedException;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\ViewModel\Total\Shipping as ShippingTotalViewModel;

class Shipping extends DefaultTotal
{
    /**
     * @inheritDoc
     */
    public function toHtml()
    {
        $service = $this->getShippingTotalService();
        $configHelper = $service->getConfigHelper();
        $checkoutSession = $service->getCheckoutSession();

        if (!$configHelper->isEnabled()) {
            return parent::toHtml();
        }

        if (!$configHelper->getSveaShippingActive()) {
            return parent::toHtml();
        }

        // If Svea Shipping is active but option isn't selected yet, hide the shipping
        $shippingAddress = $checkoutSession->getQuote()->getShippingAddress();
        $shippingMethod = $shippingAddress->getShippingMethod();
        $carrierCode = Carrier::CODE;
        if (strpos($shippingMethod, $carrierCode) !== false) {
            return parent::toHtml();
        }

        return '';
    }

    /**
     * Accessor for related view model
     *
     * @return ShippingTotalViewModel
     */
    private function getShippingTotalService(): ShippingTotalViewModel
    {
        $viewModel = $this->getData('view_model');
        if (!($viewModel instanceof ShippingTotalViewModel)) {
            throw new LocalizedException(__('Svea Checkout\'s layout xml is incorrectly configured'));
        }
        return $viewModel;
    }
}
