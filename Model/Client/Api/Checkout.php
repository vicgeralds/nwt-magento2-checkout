<?php


namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\ApiClient;
use Svea\Checkout\Model\Client\Client;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CreatePayment;
use Svea\Checkout\Model\Client\DTO\GetPaymentResponse;
use Svea\Checkout\Model\Client\DTO\UpdatePaymentCart;
use Svea\Checkout\Model\Client\DTO\CreatePaymentResponse;
use Svea\Checkout\Model\Client\DTO\UpdatePaymentReference;

class Checkout extends ApiClient
{

    /**
     * @param CreatePayment $createPayment
     * @return CreatePaymentResponse
     * @throws ClientException
     */
    public function createNewPayment(CreatePayment $createPayment)
    {
        try {
            $response = $this->post("/v1/payments", $createPayment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new CreatePaymentResponse($response);
    }


    /**
     * @param UpdatePaymentCart $cart
     * @param $paymentId
     * @return void
     * @throws \Exception
     */
    public function UpdatePaymentCart(UpdatePaymentCart $cart, $paymentId)
    {
        try {
            $this->put("/v1/payments/".$paymentId."/orderitems", $cart);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

    }

    /**
     * @param UpdatePaymentReference $reference
     * @param $paymentId
     * @return void
     * @throws ClientException
     */
    public function UpdatePaymentReference(UpdatePaymentReference $reference, $paymentId)
    {
        try {
            $this->put("/v1/payments/".$paymentId."/referenceinformation", $reference);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

    }

    /**
     * @param string $paymentId
     * @return GetPaymentResponse
     * @throws ClientException
     */
    public function getPayment($paymentId)
    {
        try {
            $response = $this->get("/v1/payments/" . $paymentId);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new GetPaymentResponse($response);
    }

}