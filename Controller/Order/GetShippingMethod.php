<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\Country\Postcode\ValidatorInterface;
use Svea\Checkout\Helper\Cart as SveaCartHelper;
use Svea\Checkout\Model\Checkout as SveaCheckout;
use Svea\Checkout\Model\CheckoutContext as SveaCheckoutCOntext;

class GetShippingMethod extends Update
{

    /**
     * @var ValidatorInterface
     */
    protected $validatorInterface;

    /**
     * @var SveaCartHelper
     */
    protected $sveaCartHelper;

    /**
     * GetShippingMethod constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param SveaCheckout $sveaCheckout
     * @param SveaCheckoutCOntext $sveaCheckoutContext
     * @param ValidatorInterface $validatorInterface
     * @param SveaCartHelper $sveaCartHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Svea\Checkout\Model\PushFactory $pushFactory,
        SveaCheckout $sveaCheckout,
        SveaCheckoutCOntext $sveaCheckoutContext,
        ValidatorInterface $validatorInterface,
        SveaCartHelper $sveaCartHelper
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement,
            $checkoutSession,
            $storeManager,
            $resultPageFactory,
            $jsonResultFactory,
            $quoteFactory,
            $pushFactory,
            $sveaCheckout,
            $sveaCheckoutContext
        );
        $this->validatorInterface = $validatorInterface;
        $this->sveaCartHelper = $sveaCartHelper;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $countryId = (string)$this->getRequest()->getParam('country_id');
        $postalCode = (string)$this->getRequest()->getParam('postal');
        $postalCode = preg_replace("/[^0-9]/", "", $postalCode);

        if (!$postalCode) {
            $this->getResponse()->setBody(json_encode(['messages' => 'Please choose a valid Postal code.']));
            return;
        }

        if (!$this->validateCountryId($countryId)) {
            $this->getResponse()->setBody(json_encode(['messages' => 'Please select a Valid Country.']));
            return;
        }

        /*
            if (!$this->validatePostalCode($countryId, $postalCode)) {
            $this->getResponse()->setBody(json_encode(array('messages' => "Postal code is not valid for " . $this->sveaCartHelper->getCountryNameByCode($countryId) . ".")));
            return;
        }*/

        if ($postalCode) {
            try {
                $quote = $this->getSveaCheckout()->getQuote();
                $quote->getShippingAddress()
                    ->setPostcode($postalCode)
                    ->setCountryId($countryId)
                    ->setCollectShippingRates(true);

                $quote->getBillingAddress()
                    ->setCountryId($countryId)
                    ->setPostcode($postalCode);

                // save!
                $quote->save();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    $e->getMessage()
                );
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('We can\'t update your Country / postal code.')
                );
            }
        }
        $this->_sendResponse(['shipping_method','cart','coupon','messages', 'svea','newsletter','grand_total']);
    }

    public function validatePostalCode($countryId, $postalCode)
    {
        return (bool)$this->validatorInterface->validate($postalCode, $countryId);
    }

    public function validateCountryId($countryId)
    {
        return (bool)in_array($countryId, $this->sveaCartHelper->getAllowedCountriesList());
    }
}
