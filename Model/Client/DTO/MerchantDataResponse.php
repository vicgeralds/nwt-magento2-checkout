<?php
namespace Svea\Checkout\Model\Client\DTO;

class MerchantDataResponse
{

    /**
     * @var $originalData string
     */
    protected $originalData;

    /** @var $quoteId int */
    protected $quoteId;

    /** @var $clientOrderNumber string */
    protected $clientOrderNumber;

    /**
     * We try to parse the JSON response according to \Svea\Checkout\Model\Svea\Order::generateMerchantData()
     *
     * @param $data string
     * @return void
     */
    public function setDataFromResponse($data = "")
    {
        // we set the data for debugging if anything is wrong here!
        $this->setOriginalData($data);

        if (!$data || !is_string($data)) {
            return;
        }

        $merchantArray = json_decode($data, true);
        if ($merchantArray === null) {
            return;
        }

        if (isset($merchantArray['quote_id'])) {
            $this->setQuoteId($merchantArray['quote_id']);
        }

        if (isset($merchantArray['client_order_number'])) {
            $this->setClientOrderNumber($merchantArray['client_order_number']);
        }
    }

    /**
     * Used for debugging
     * @return string
     */
    public function getOriginalData()
    {
        return $this->originalData;
    }

    /**
     * @param string $originalData
     * @return MerchantDataResponse
     */
    public function setOriginalData($originalData)
    {
        $this->originalData = $originalData;
        return $this;
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->quoteId;
    }

    /**
     * @param int $quoteId
     * @return MerchantDataResponse
     */
    public function setQuoteId($quoteId)
    {
        $this->quoteId = $quoteId;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientOrderNumber()
    {
        return $this->clientOrderNumber;
    }

    /**
     * @param string $clientOrderNumber
     * @return MerchantDataResponse
     */
    public function setClientOrderNumber($clientOrderNumber)
    {
        $this->clientOrderNumber = $clientOrderNumber;
        return $this;
    }

}