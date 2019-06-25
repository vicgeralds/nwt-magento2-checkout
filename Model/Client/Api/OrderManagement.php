<?php


namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\Client;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelPayment;
use Svea\Checkout\Model\Client\DTO\ChargePayment;
use Svea\Checkout\Model\Client\DTO\CreatePaymentChargeResponse;
use Svea\Checkout\Model\Client\DTO\CreateRefundResponse;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\OrderManagementClient;

class OrderManagement extends OrderManagementClient
{


    /**
     * @param CancelPayment $payment
     * @param string $paymentId
     * @throws ClientException
     * @return void
     */
    public function cancelPayment(CancelPayment $payment, $paymentId)
    {
        try {
            $this->post("/v1/payments/" . $paymentId . "/cancels", $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param ChargePayment $payment
     * @param string $paymentId
     * @throws ClientException
     * @return CreatePaymentChargeResponse
     */
    public function chargePayment(ChargePayment $payment, $paymentId)
    {
        try {
            $response = $this->post("/v1/payments/" . $paymentId . "/charges", $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new CreatePaymentChargeResponse($response);
    }


    /**
     * @param RefundPayment $paymentCharge
     * @param string $chargeId
     * @throws ClientException
     * @return CreateRefundResponse
     */
    public function refundPayment(RefundPayment $paymentCharge, $chargeId)
    {
        try {
           $response = $this->post("/v1/charges/" . $chargeId . "/refunds", $paymentCharge);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new CreateRefundResponse($response);
    }

}