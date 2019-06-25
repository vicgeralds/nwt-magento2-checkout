<?php
namespace Svea\Checkout\Model\Client;

/**
 * Class Client
 * @package Svea\Checkout\Model\Api
 */
abstract class ApiClient extends BaseClient
{

    /**
     * We test the http client with the correct Api URL
     * @param \Svea\Checkout\Helper\Data $helper
     */
    protected function setGuzzleHttpClient(\Svea\Checkout\Helper\Data $helper)
    {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => $helper->getApiUrl(),
        //    'verify' => false,
        ]);
    }

}









