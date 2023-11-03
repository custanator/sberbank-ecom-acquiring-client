<?php

declare(strict_types=1);

namespace custanator\SberbankEcomAcquiring;

use custanator\SberbankEcomAcquiring\Exception\ActionException;
use custanator\SberbankEcomAcquiring\Exception\BadResponseException;
use custanator\SberbankEcomAcquiring\Exception\NetworkException;
use custanator\SberbankEcomAcquiring\Exception\ResponseParsingException;
use custanator\SberbankEcomAcquiring\HttpClient\CurlClient;
use custanator\SberbankEcomAcquiring\HttpClient\HttpClientInterface;

/**
 * Client for working with Sberbanks's aquiring REST API.
 *
 * @author Rustam Salikhov <custanator@mail.ru>
 * @see https://ecomtest.sberbank.ru/doc#section/Obshaya-informaciya/Terminy
 */
class Client
{
    const ACTION_SUCCESS = 0;

    const API_URI            = 'https://ecommerce.sberbank.ru';
    const API_URI_TEST       = 'https://ecomtest.sberbank.ru';
    const API_PREFIX_DEFAULT = '/ecomm/gw/partner/api/v1/';

    /**
     * @var string
     */
    private $userName;

    /**
     * @var string
     */
    private $password;

    /**
     * Currency code in ISO 4217 format.
     *
     * @var int
     */
    private $currency;

    /**
     * A language code in ISO 639-1 format ('en', 'ru' and etc.).
     *
     * @var string
     */
    private $language;

    /**
     * An API uri.
     *
     * @var string
     */
    private $apiUri;

    /**
     * Default API endpoints prefix.
     *
     * @var string
     */
    private $prefixDefault;

    /**
     * An HTTP method.
     *
     * @var string
     */
    private $httpMethod = HttpClientInterface::METHOD_POST;

    private $dateFormat = 'YmdHis';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(array $options = [])
    {
        if (!\extension_loaded('json')) {
            throw new \RuntimeException('JSON extension is not loaded.');
        }

        $allowedOptions = [
            'apiUri',
            'currency',
            'httpClient',
            'httpMethod',
            'language',
            'password',
            'userName',
            'merchantLogin',
            'prefixDefault',
        ];

        $unknownOptions = \array_diff(\array_keys($options), $allowedOptions);

        if (!empty($unknownOptions)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unknown option "%s". Allowed options: "%s".',
                    \reset($unknownOptions),
                    \implode('", "', $allowedOptions)
                )
            );
        }

        if (isset($options['userName']) && isset($options['password'])) {

            $this->userName = $options['userName'];
            $this->password = $options['password'];
        } else {
            throw new \InvalidArgumentException('You must provide authentication credentials: "userName" and "password"');
        }

        $this->language = $options['language'] ?? null;
        $this->currency = $options['currency'] ?? null;
        $this->apiUri = $options['apiUri'] ?? self::API_URI;
        $this->prefixDefault = $options['prefixDefault'] ?? self::API_PREFIX_DEFAULT;

        if (isset($options['httpMethod'])) {
            if (!\in_array($options['httpMethod'], [HttpClientInterface::METHOD_GET, HttpClientInterface::METHOD_POST])) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'An HTTP method "%s" is not supported. Use "%s" or "%s".',
                        $options['httpMethod'],
                        HttpClientInterface::METHOD_GET,
                        HttpClientInterface::METHOD_POST
                    )
                );
            }

            $this->httpMethod = $options['httpMethod'];
        }

        if (isset($options['httpClient'])) {
            if (!$options['httpClient'] instanceof HttpClientInterface) {
                throw new \InvalidArgumentException('An HTTP client must implement HttpClientInterface.');
            }

            $this->httpClient = $options['httpClient'];
        }
    }

    /**
     * Register a new order.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/register
     *
     * @param int|string $orderId   An order identifier
     * @param int        $amount    An order amount
     * @param string     $returnUrl An url for redirecting a user after successfull order handling
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function registerOrder($orderId, int $amount, string $returnUrl, array $data = []): array
    {
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, $this->prefixDefault . 'register.do');
    }

    /**
     * Register a new order using a 2-step payment process.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/registerPreAuth
     *
     * @param int|string $orderId   An order identifier
     * @param int        $amount    An order amount
     * @param string     $returnUrl An url for redirecting a user after successfull order handling
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function registerOrderPreAuth($orderId, int $amount, string $returnUrl, array $data = []): array
    {
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, $this->prefixDefault . 'registerPreAuth.do');
    }

    /**
     * Deposit an existing order.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/deposit
     *
     * @param int|string $orderId An order identifier
     * @param int        $amount  An order amount
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function deposit($orderId, int $amount, array $data = []): array
    {
        $data['orderId'] = $orderId;
        $data['amount']  = $amount;

        return $this->execute($this->prefixDefault . 'deposit.do', $data);
    }

    private function doRegisterOrder($orderId, int $amount, string $returnUrl, array $data = [], $method = 'register.do'): array
    {
        $data['orderNumber'] = $orderId . "";
        $data['amount']      = $amount;
        $data['returnUrl']   = $returnUrl;

        if (!isset($data['currency']) && null !== $this->currency) {
            $data['currency'] = $this->currency;
        }

        if (isset($data['jsonParams']) && !is_array($data['jsonParams'])) {
            throw new \InvalidArgumentException('The "jsonParams" parameter must be an array.');
        }

        if (isset($data['orderBundle']) && !is_array($data['orderBundle'])) {
            throw new \InvalidArgumentException('The "orderBundle" parameter must be an array.');
        }

        return $this->execute($method, $data);
    }

    /**
     * Reverse an existing order.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/reverse
     *
     * @param int|string $orderId An order identifier
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function reverseOrder($orderId, array $data = []): array
    {
        $data['orderId'] = $orderId;

        return $this->execute($this->prefixDefault . 'reverse.do', $data);
    }

    /**
     * Refund an existing order.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/refund
     *
     * @param int|string $orderId An order identifier
     * @param int        $amount  An amount to refund
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function refundOrder($orderId, int $amount, array $data = []): array
    {
        $data['orderId'] = $orderId;
        $data['amount']  = $amount;

        return $this->execute($this->prefixDefault . 'refund.do', $data);
    }

    /**
     * Get an existing order's status by Sberbank's gateway identifier.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/getOrderStatusExtended
     *
     * @param int|string $orderId A Sberbank's gateway order identifier
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function getOrderStatus($orderId, array $data = []): array
    {
        $data['orderId'] = $orderId;

        return $this->execute($this->prefixDefault . 'getOrderStatusExtended.do', $data);
    }

    /**
     * Payment order binding.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/bindingServices/operation/paymentOrderBinding
     *
     * @param int|string $orderId   An order identifier
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function paymentOrderBinding($orderId, $bindingId, array $data = []): array
    {
        $data['mdOrder']   = $orderId;
        $data['bindingId'] = $bindingId;

        return $this->execute($this->prefixDefault . 'paymentOrderBinding.do', $data);
    }

    /**
     * Activate a binding.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/bindingServices/operation/bindCard
     *
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function bindCard($bindingId, array $data = []): array
    {
        $data['bindingId'] = $bindingId;

        return $this->execute($this->prefixDefault . 'bindCard.do', $data);
    }

    /**
     * Deactivate a binding.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/bindingServices/operation/unBindCard
     *
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function unBindCard($bindingId, array $data = []): array
    {
        $data['bindingId'] = $bindingId;

        return $this->execute($this->prefixDefault . 'unbindCard.do', $data);
    }

    /**
     * Get bindings.
     *
     * @see https://ecomtest.sberbank.ru/doc#tag/bindingServices/operation/getBindings
     *
     * @param int|string $clientId A binding identifier
     * @param array      $data     Additional data
     *
     * @return array A server's response
     */
    public function getBindings($clientId, array $data = []): array
    {
        $data['clientId'] = $clientId;

        return $this->execute($this->prefixDefault . 'getBindings.do', $data);
    }

    /**
     * Execute an action.
     *
     * @param string $action An action's name e.g. 'register.do'
     * @param array  $data   An action's data
     *
     * @throws NetworkException
     *
     * @return array A server's response
     */
    public function execute(string $action, array $data = []): array
    {
        // Add '/payment/rest/' prefix for BC compatibility if needed
        if ($action[0] !== '/') {
            $action = $this->prefixDefault . $action;
        }

        $uri = $this->apiUri . $action;

        if (!isset($data['language']) && null !== $this->language) {
            $data['language'] = $this->language;
        }

        $method = $this->httpMethod;

        $headers['Content-Type'] = 'application/json';
        $data['userName'] = $this->userName;
        $data['password'] = $this->password;
        $data = \json_encode($data);
        $method = HttpClientInterface::METHOD_POST;

        $httpClient = $this->getHttpClient();

        list($httpCode, $response) = $httpClient->request($uri, $method, $headers, $data);

        if (200 !== $httpCode) {
            $badResponseException = new BadResponseException(sprintf('Bad HTTP code: %d.', $httpCode), $httpCode);
            $badResponseException->setResponse($response);

            throw $badResponseException;
        }

        $response = $this->parseResponse($response);
        $this->handleErrors($response);

        return $response;
    }

    /**
     * Parse a servers's response.
     *
     * @param string $response A string in the JSON format
     *
     * @throws ResponseParsingException
     *
     * @return array
     */
    private function parseResponse(string $response): array
    {
        $response  = \json_decode($response, true);
        $errorCode = \json_last_error();

        if (\JSON_ERROR_NONE !== $errorCode || null === $response) {
            throw new ResponseParsingException(\json_last_error_msg(), $errorCode);
        }

        return $response;
    }

    /**
     * Normalize server's response.
     *
     * @param array $response A response
     *
     * @throws ActionException
     */
    private function handleErrors(array &$response)
    {
        // Server's response can contain an error code and an error message in differend fields.
        if (isset($response['errorCode'])) {
            $errorCode = (int) $response['errorCode'];
        } elseif (isset($response['ErrorCode'])) {
            $errorCode = (int) $response['ErrorCode'];
        } elseif (isset($response['error']['code'])) {
            $errorCode = (int) $response['error']['code'];
        } else {
            $errorCode = self::ACTION_SUCCESS;
        }

        unset($response['errorCode']);
        unset($response['ErrorCode']);

        if (isset($response['errorMessage'])) {
            $errorMessage = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $errorMessage = $response['ErrorMessage'];
        } elseif (isset($response['error']['message'])) {
            $errorMessage = $response['error']['message'];
        } elseif (isset($response['error']['description'])) {
            $errorMessage = $response['error']['description'];
        } else {
            $errorMessage = 'Unknown error.';
        }

        unset($response['errorMessage']);
        unset($response['ErrorMessage']);
        unset($response['error']);
        unset($response['success']);

        if (self::ACTION_SUCCESS !== $errorCode) {
            throw new ActionException($errorMessage, $errorCode);
        }
    }

    /**
     * Get an HTTP client.
     */
    private function getHttpClient(): HttpClientInterface
    {
        if (null === $this->httpClient) {
            $this->httpClient = new CurlClient([
                \CURLOPT_VERBOSE => false,
                \CURLOPT_SSL_VERIFYHOST => false,
                \CURLOPT_SSL_VERIFYPEER => false,
            ]);
        }

        return $this->httpClient;
    }
}
