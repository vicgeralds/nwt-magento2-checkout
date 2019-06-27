<?php
namespace Svea\Checkout\Model\Client;


use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Svea\Checkout\Model\Client\DTO\AbstractRequest;

/**
 * Class Client
 * @package Svea\Checkout\Model\Api
 */
abstract class BaseClient
{

    /**
     * @var int
     */
    protected $timeout = 30;

    /** @var Context $apiContext */
    protected $apiContext;

    /** @var string $sharedSecret */
    protected $sharedSecret;

    /** @var string $merchantId */
    protected $merchantId;


    /** @var \GuzzleHttp\Client $httpClient */
    protected $httpClient;

    /**
     * Constructor
     *
     * @param Context $apiContext
     *
     */
    public function __construct(
        Context $apiContext
    ) {
        $this->apiContext = $apiContext;

        $this->sharedSecret = $apiContext->getHelper()->getSharedSecret();
        $this->merchantId = $apiContext->getHelper()->getMerchantId();
        
        // init curl!
        $this->setGuzzleHttpClient($this->getHelper());
    }


    /**
     * @param $endpoint
     * @param array $params
     * @param bool $useTenant
     * @return string
     */
    protected function buildEndpoint($endpoint, $params = []) {
        $buildEndpoint = ltrim($endpoint, "/");
        if (!empty($params)) {
            $query =  http_build_query($params);
            $buildEndpoint .= "?" . $query;
        }

        return $buildEndpoint;
    }

    /**
     * @param $endpoint
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function get($endpoint, $options = []){
        if (!is_array($options)) {
            $options = [];
        }

        $options = array_merge($options, $this->getDefaultOptions());

        try {
            $result = $this->httpClient->get($endpoint, $options);
            return $result->getBody()->getContents();
        } catch (BadResponseException $e) {
            $exception = $this->handleException($e);
        } catch (\Exception $e) {
            $exception = $this->handleException($e);
        }

        if ($exception) {
            $this->getLogger()->error("Failed sending request to svea integration: GET $endpoint");
            $this->getLogger()->error(json_encode($this->removeAuthForLogging($options)));
            $this->getLogger()->error($exception->getMessage());
            $this->getLogger()->error($exception->getHttpStatusCode());
            $this->getLogger()->error($exception->getResponseBody());
            throw $exception;
        }
    }

    /**
     * @param $endpoint
     * @param AbstractRequest $request
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function post($endpoint, AbstractRequest $request, $options = []){
        if (!is_array($options)) {
            $options = [];
        }

        $body = $request->toArray();
        $options = array_merge($options, $this->getDefaultOptions($body));
        $options[RequestOptions::JSON] = $body;
        $exception = null;

        // todo catch exceptions or let them be catched by magento?
        try {
            $result = $this->httpClient->post($endpoint, $options);
            return $result->getBody()->getContents();
        } catch (BadResponseException $e) {
            $exception = $this->handleException($e);
        } catch (\Exception $e) {
            $exception = $this->handleException($e);
        }

        if ($exception) {
            $this->getLogger()->error("Failed sending request to svea integration: POST $endpoint");
            $this->getLogger()->error(json_encode($this->removeAuthForLogging($options)));
            $this->getLogger()->error($request->toJSON());
            $this->getLogger()->error($exception->getMessage());
            $this->getLogger()->error($exception->getHttpStatusCode());
            $this->getLogger()->error($exception->getResponseBody());
            throw $exception;
        }

    }

    /**
     * @param $endpoint
     * @param AbstractRequest $request
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function put($endpoint, AbstractRequest $request, $options = []){
        if (!is_array($options)) {
            $options = [];
        }

        $body = $request->toArray();
        $options = array_merge($options, $this->getDefaultOptions($body));
        $options[RequestOptions::JSON] = $body;
        $exception = null;

        try {
            $result = $this->httpClient->put($endpoint, $options);
            return $result->getBody()->getContents();
        }  catch (BadResponseException $e) {
            $exception = $this->handleException($e);
        } catch (\Exception $e) {
            $exception = $this->handleException($e);
        }

        if ($exception) {
            $this->getLogger()->error("Failed sending request to svea integration: PUT $endpoint");
            $this->getLogger()->error(json_encode($this->removeAuthForLogging($options)));
            $this->getLogger()->error($request->toJSON());
            $this->getLogger()->error($exception->getMessage());
            $this->getLogger()->error($exception->getHttpStatusCode());
            $this->getLogger()->error($exception->getResponseBody());
            throw $exception;
        }
    }

    /**
     * @param $bodyArray
     * @return mixed
     */
    protected function getDefaultOptions($bodyArray = [])
    {
        $body = "";
        if (is_array($bodyArray) && !empty($bodyArray)) {
            $body = json_encode($bodyArray);
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $options['headers'] = [
            'Content-Type' => 'application/json',
            'Timestamp' => $timestamp,
            'Authorization' => $this->createAuthorizationToken($timestamp, $body),
        ];

        return $options;
    }

    private function createAuthorizationToken($timestamp, $body) {

        return "Svea " . base64_encode($this->merchantId . ':' . hash('sha512', $body . $this->sharedSecret . $timestamp));
    }

    private function removeAuthForLogging($options) {

        // we dont want to expose these values!
        if (isset($options['headers']['Authorization'])) {
            unset($options['headers']['Authorization']);
        }

        return $options;
    }


    /**
     * @param \Exception $e
     * @return ClientException
     */
    private function handleException(\Exception $e)
    {
        if ($e instanceof BadResponseException) {
            if ($e->hasResponse()) {
                return new ClientException($e->getRequest(),$e->getResponse(), $e->getMessage(),$e->getCode(), $e);
            } else {
                return new ClientException($e->getRequest(), null, $e->getMessage(), $e->getCode());
            }
        }

        return new ClientException(null, null, $e->getMessage(), $e->getCode());
    }

    /**
     * @return \Svea\Checkout\Logger\Logger
     */
    public function getLogger()
    {
        return $this->apiContext->getLogger();
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->apiContext->getHelper();
    }


    /**
     * @param \Svea\Checkout\Helper\Data $helper
     */
    protected abstract function setGuzzleHttpClient(\Svea\Checkout\Helper\Data $helper);
}









