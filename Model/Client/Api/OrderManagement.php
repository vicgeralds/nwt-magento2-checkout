<?php


namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\CreatePaymentChargeResponse;
use Svea\Checkout\Model\Client\DTO\CreateRefundResponse;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\OrderManagementClient;

class OrderManagement extends OrderManagementClient
{


    /**
     * @param CancelOrder $payment
     * @param string $paymentId
     * @throws ClientException
     * @return void
     */
    public function cancelOrder(CancelOrder $payment, $paymentId)
    {
        try {
            $this->patch("/api/v1/orders/" . $paymentId, $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param DeliverOrder $payment
     * @param string $orderId
     * @throws ClientException
     * @return CreatePaymentChargeResponse
     */
    public function deliveryOrder(DeliverOrder $payment, $orderId)
    {
        try {
            $response = $this->post("/api/v1/orders/" . $orderId . "/deliveries", $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new CreatePaymentChargeResponse($response);
    }


    /**
     * @param RefundPayment $creditRow
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return CreateRefundResponse
     */
    public function refundPayment(RefundPayment $creditRow, $orderId, $deliveryId)
    {
        try {
           $response = $this->post("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId . "/credits", $creditRow);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new CreateRefundResponse($response);
    }

}