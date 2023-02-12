<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Checkout\Model\ShippingInformationFactory;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Service\SveaShippingInfo;

/**
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Confirmshipping implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var ShippingInformationFactory
     */
    private $shipInfoFactory;

    /**
     * @var ShippingInformationManagement
     */
    private $shipInfoManagement;

    /**
     * @var SveaShippingInfo
     */
    private $shipInfoService;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepo;

    public function __construct(
        JsonFactory $jsonFactory,
        Session $checkoutSession,
        HttpRequest $request,
        ShippingInformationFactory $shipInfoFactory,
        ShippingInformationManagement $shipInfoManagement,
        SveaShippingInfo $shipInfoService
    ) {
        $this->jsonResultFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->shipInfoFactory = $shipInfoFactory;
        $this->shipInfoManagement = $shipInfoManagement;
        $this->shipInfoService = $shipInfoService;
    }

    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        $content = $this->request->getPost()->toArray();
        $carrier = $content['carrier'];

        try {
            $this->shipInfoService->setInQuote($quote, $content);
        } catch (\Exception $e) {
            return $this->returnError();
        }

        $shipInfo = $this->shipInfoFactory->create();
        $shipInfo->setBillingAddress($quote->getBillingAddress());
        $shipInfo->setShippingAddress($quote->getShippingAddress());
        $shipInfo->setShippingCarrierCode(Carrier::CODE);
        $shipInfo->setShippingMethodCode(strtolower($carrier));

        try {
            $this->shipInfoManagement->saveAddressInformation($quote->getId(), $shipInfo);
        } catch (\Exception $e) {
            return $this->returnError();
        }

        return $this->jsonResultFactory->create()->setData(
            ['success' => true]
        );
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function returnError(): \Magento\Framework\Controller\Result\Json
    {
        return $this->jsonResultFactory->create()->setData(
            [
                'success' => false,
                'messages' => __('This shipping method is unavailable. Please try a different one.')
            ]
        );
    }
}
