<?php
namespace Svea\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Sales\Model\Order\AddressFactory;
use Magento\Sales\Model\Order;
use Svea\Checkout\Service\SveaShippingInfo;

class EmailOrderSetTemplateVarsBefore implements ObserverInterface
{
    /**
     * @var Renderer
     */
    private Renderer $renderer;

    /**
     * @var AddressFactory
     */
    private AddressFactory $addressFactory;

    /**
     * @var SveaShippingInfo
     */
    private SveaShippingInfo $sveaShippingInfo;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    public function __construct(
        Renderer $renderer,
        AddressFactory $addressFactory,
        SveaShippingInfo $sveaShippingInfo,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->renderer = $renderer;
        $this->addressFactory = $addressFactory;
        $this->sveaShippingInfo = $sveaShippingInfo;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Add svea shipping location address to email template variables
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $transportObject = $observer->getEvent()->getData('transportObject');
        if (!$transportObject instanceof DataObject) {
            return;
        }

        $order = $this->getOrderFromTransportObject($transportObject);
        if (!$order) {
            return;
        }

        $address = $this->addressFactory->create();
        $shipInfo = $this->sveaShippingInfo->getFromOrder($order);
        $locationData = $shipInfo->getLocation();
        $locationObj = $this->dataObjectFactory->create()->setData($locationData);
        if (!$locationObj->getName()) {
            $transportObject->setData(
                'sveaShippingDestinationAddress',
                $transportObject->getFormattedShippingAddress()
            );
            return;
        }

        $address = $this->sveaShippingInfo->createOrderAddressFromLocation($locationObj);
        $formattedAddress = $this->renderer->format($address, 'html');
        $transportObject->setData('sveaShippingDestinationAddress', $formattedAddress);
        $transportObject->unsetData('formattedShippingAddress');
    }

    /**
     * @param DataObject $transportObject
     * @return Order|null
     */
    private function getOrderFromTransportObject(DataObject $transportObject): ?Order
    {
        return $transportObject->getOrder();
    }
}
