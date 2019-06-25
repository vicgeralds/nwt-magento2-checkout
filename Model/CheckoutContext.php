<?php

namespace Svea\Checkout\Model;


class CheckoutContext
{
    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var \Svea\Checkout\Logger\Logger
     */
    protected $logger;

    /** @var \Svea\Checkout\Model\Svea\Order $sveaOrderHandler */
    protected $sveaOrderHandler;

    /** @var \Magento\Sales\Api\OrderCustomerManagementInterface */
    protected $orderCustomerManagement;

    /** @var \Magento\Newsletter\Model\Subscriber $Subscriber */
    protected $subscriber;

    /** @var \Svea\Checkout\Model\Svea\Locale $sveaLocale */
    protected $sveaLocale;

   /**
     * Constructor
     *
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Svea\Checkout\Model\Svea\Order $sveaOrderHandler
     * @param \Svea\Checkout\Logger\Logger $logger
     * @param \Svea\Checkout\Model\Svea\Locale $sveaLocale,
     * @param \Magento\Sales\Api\OrderCustomerManagementInterface $orderCustomerManagement
     * @param \Magento\Newsletter\Model\Subscriber $subscriber
     *
     */
    public function __construct(
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Model\Svea\Order $sveaOrderHandler,
        \Svea\Checkout\Logger\Logger $logger,
        \Svea\Checkout\Model\Svea\Locale $sveaLocale,
        \Magento\Sales\Api\OrderCustomerManagementInterface $orderCustomerManagement,
        \Magento\Newsletter\Model\Subscriber $subscriber
    ) {
        $this->helper        = $helper;
        $this->logger = $logger;
        $this->sveaOrderHandler = $sveaOrderHandler;
        $this->sveaLocale = $sveaLocale;
        $this->orderCustomerManagement = $orderCustomerManagement;
        $this->subscriber = $subscriber;
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @return \Svea\Checkout\Logger\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

   /** @return \Svea\Checkout\Model\Svea\Order */
    public function getSveaOrderHandler()
    {
        return $this->sveaOrderHandler;
    }

    /**
     * @return \Magento\Sales\Api\OrderCustomerManagementInterface
     */
    public function getOrderCustomerManagement()
    {
        return $this->orderCustomerManagement;
    }

    /**
     * @return \Magento\Newsletter\Model\Subscriber
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @return Svea\Locale
     */
    public function getSveaLocale()
    {
        return $this->sveaLocale;
    }




}