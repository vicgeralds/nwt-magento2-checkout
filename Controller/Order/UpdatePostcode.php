<?php
namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\View\Result\PageFactory;
use Svea\Checkout\Model\Checkout as SveaCheckout;
use Svea\Checkout\Model\CheckoutContext;

/**
 * Controller to handle postcode updates from Svea Checkout
 */
class UpdatePostcode implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    private Session $checkoutSession;

    private Request $request;

    private JsonFactory $resultJsonFactory;

    private RedirectFactory $redirectFactory;

    private QuoteRepository $quoteRepo;

    private SveaCheckout $sveaCheckout;

    private CheckoutContext $sveaCheckoutContext;

    private PageFactory $pageFactory;

    public function __construct(
        Session $checkoutSession,
        Request $request,
        JsonFactory $resultJsonFactory,
        RedirectFactory $redirectFactory,
        QuoteRepository $quoteRepo,
        SveaCheckout $sveaCheckout,
        CheckoutContext $sveaCheckoutContext,
        PageFactory $pageFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->redirectFactory = $redirectFactory;
        $this->quoteRepo = $quoteRepo;
        $this->sveaCheckout = $sveaCheckout;
        $this->sveaCheckoutContext = $sveaCheckoutContext;
        $this->pageFactory = $pageFactory;
    }

    /**
     * Checks if tax amount has changed. If so, updates the Svea order and returns updated cart and shipping blocks.
     *
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->request->isXmlHttpRequest()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('*');
            return $redirect;
        }

        $this->sveaCheckoutContext->getSveaShippingInfoService()->setExcludeSveaShipping(false);
        $postcode = $this->request->getParam('postcode', false);
        $quote = $this->checkoutSession->getQuote();
        $calculationAddress = $quote->getShippingAddress();
        if ($quote->getIsVirtual()) {
            $calculationAddress = $quote->getBillingAddress();
        }
        $currentTaxAmount = $calculationAddress->getTaxAmount();
        $newTaxAmount = $currentTaxAmount;

        if (!!$postcode) {
            // Collect totals to update tax amount
            $calculationAddress->setPostcode($postcode);
            $quote->collectTotals();
            $newTaxAmount = $calculationAddress->getTaxAmount();
        }

        $responseData = ['ok' => true, 'updates' => []];

        if (floatval($currentTaxAmount) !== floatval($newTaxAmount)) {
            // Update Svea order
            $this->sveaCheckout->setCheckoutContext($this->sveaCheckoutContext);
            $this->sveaCheckout->updateSveaPayment($quote->getSveaOrderId());
            $responseData['ctrlkey'] = $this->sveaCheckout->getQuoteSignature();

            // Load updated blocks
            $page = $this->pageFactory->create();
            $page->addHandle('svea_checkout_order_update');
            $page->getLayout()->getUpdate()->load();
            foreach (['cart', 'shipping'] as $id) {
                $name = "svea_checkout.{$id}";
                $block =$page->getLayout()->getBlock($name);
                if ($block) {
                    $blockHtml = $block->toHtml();
                    $responseData['updates'][$id] = $blockHtml;
                }
            }

            // Save quote
            $this->quoteRepo->save($quote);
        }

        // Send response with updated block html for the checkout to handle
        $response = $this->resultJsonFactory->create();
        $response->setData($responseData);
        return $response;
    }
}
