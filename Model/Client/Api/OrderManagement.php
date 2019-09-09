<?php


namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\CreatePaymentChargeResponse;
use Svea\Checkout\Model\Client\DTO\GetOrderInfoResponse;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\DTO\RefundPaymentAmount;
use Svea\Checkout\Model\Client\OrderManagementClient;

class OrderManagement extends OrderManagementClient
{

    /**
     * @param $paymentId
     * @return GetOrderInfoResponse
     * @throws ClientException
     */
    public function getOrder($paymentId)
    {
        try {
            $response = $this->get("/api/v1/orders/" . $paymentId);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new GetOrderInfoResponse($response);
    }



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
    public function deliverOrder(DeliverOrder $payment, $orderId)
    {
        try {
           $this->post("/api/v1/orders/" . $orderId . "/deliveries", $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        try {
            $location = $this->getLastResponse()->getHeader("Location")[0];
        } catch (\Exception $exception) {
            $location = "";
        }

        return new CreatePaymentChargeResponse($location);
    }


    /**
     * @param RefundPayment $creditRow
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return void
     */
    public function refundPayment(RefundPayment $creditRow, $orderId, $deliveryId)
    {
        try {
            $this->post("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId . "/credits", $creditRow);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }


    /**
     * @param RefundPaymentAmount $creditAmount
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return void
     */
    public function refundPaymentAmount(RefundPaymentAmount $creditAmount, $orderId, $deliveryId)
    {
        try {
            $this->patch("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId, $creditAmount);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param $taskId
     * @return array
     * @throws ClientException
     */
    public function getTask($taskId)
    {
        try {
            $response = $this->get("/api/v1/queue/" . $taskId);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return json_decode($response, true);
    }
}