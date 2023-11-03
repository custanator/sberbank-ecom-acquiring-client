<?php

declare(strict_types=1);

namespace custanator\SberbankEcomAcquiring\Tests;

use PHPUnit\Framework\TestCase;
use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\Exception\ActionException;
use custanator\SberbankEcomAcquiring\Exception\BadResponseException;
use custanator\SberbankEcomAcquiring\Exception\ResponseParsingException;
use custanator\SberbankEcomAcquiring\HttpClient\HttpClientInterface;

/**
 * @author Rustam Salikhov <custanator@mail.ru>
 */
class ClientTest extends TestCase
{
    public function testThrowsAnExceptionIfUnkownOptionProvided()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "foo".');

        $client = new Client(['token' => 'token', 'foo' => 'bar']);
    }

    public function testAllowsToUseAUsernameAndAPasswordForAuthentication()
    {
        $httpClient = $this->mockHttpClient();
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo('anything=anything&userName=rustam&password=querty123')
            );

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'querty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/ecomm/gw/partner/api/v1/somethig.do', ['anything' => 'anything']);
    }

    public function testAllowsToUseATokenForAuthentication()
    {
        $httpClient = $this->mockHttpClient();
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo('anything=anything&token=querty123')
            );

        $client = new Client([
            'token' => 'querty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/ecomm/gw/partner/api/v1/somethig.do', ['anything' => 'anything']);
    }

    public function testThrowsAnExceptionIfBothAPasswordAndATokenUsed()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You can use either "userName" and "password" or "token".');

        $client = new Client(['userName' => 'username', 'password' => 'password', 'token' => 'token']);
    }

    public function testThrowsAnExceptionIfNoCredentialsProvided()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You must provide authentication credentials: "userName" and "password", or "token".');

        $client = new Client();
    }

    public function testThrowsAnExceptionIfAnInvalidHttpMethodSpecified()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An HTTP method "PUT" is not supported. Use "GET" or "POST".');

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpMethod' => 'PUT'
        ]);
    }

    public function testAllowsToUseACustomHttpClient()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request');

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient
        ]);

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfAnInvalidHttpClientSpecified()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An HTTP client must implement HttpClientInterface.');

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => new \stdClass(),
        ]);
    }

    /**
     * @testdox Uses an HTTP method POST by default
     */
    public function testUsesAPostHttpMethodByDefault()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                HttpClientInterface::METHOD_POST,
                $this->anything(),
                $this->anything()
            );

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/ecomm/gw/partner/api/v1/testAction.do');
    }

    public function testAllowsToSetAnHttpMethodAndApiUrl()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'https://github.com/custanator/sberbank-ecom-acquiring-client/ecomm/gw/partner/api/v1/testAction.do',
                HttpClientInterface::METHOD_GET
            );

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
            'httpMethod' => HttpClientInterface::METHOD_GET,
            'apiUri' => 'https://github.com/custanator/sberbank-ecom-acquiring-client',
        ]);

        $client->execute('/ecomm/gw/partner/api/v1/testAction.do');
    }

    public function testThrowsAnExceptionIfABadResponseReturned()
    {
        $httpClient = $this->mockHttpClient([500, 'Internal server error.']);

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $this->expectException(BadResponseException::class);
        $this->expectExceptionMessage('Bad HTTP code: 500.');

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfAMalformedJsonReturned()
    {
        $httpClient = $this->mockHttpClient([200, 'Malformed json!']);

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $this->expectException(ResponseParsingException::class);

        $client->execute('testAction');
    }

    /**
     * @dataProvider provideErredResponses
     */
    public function testThrowsAnExceptionIfAServerSetAnErrorCode(array $response)
    {
        $httpClient = $this->mockHttpClient($response);

        $client = new Client([
            'userName' => 'rustam',
            'password' => 'qwerty123',
            'httpClient' => $httpClient
        ]);

        $this->expectException(ActionException::class);
        $this->expectExceptionMessage('Error!');

        $client->execute('testAction');
    }

    public function provideErredResponses(): iterable
    {
        yield [[200, \json_encode(['errorCode' => 100, 'errorMessage' => 'Error!'])]];
        yield [[200, \json_encode(['ErrorCode' => 100, 'ErrorMessage' => 'Error!'])]];
        yield [[200, \json_encode(['error' => ['code' => 100, 'message' => 'Error!']])]];
        yield [[200, \json_encode(['error' => ['code' => 100, 'description' => 'Error!']])]];
    }

    public function testRegistersANewOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/register.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/custanator/sberbank-ecom-acquiring-client', ['currency' => 330]);
    }

    public function testRegistersANewOrderWithCustomPrefix()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/other/prefix/register.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'prefixDefault' => '/other/prefix/'
        ]);

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/custanator/sberbank-ecom-acquiring-client', ['currency' => 330]);
    }

    public function testRegisterANewPreAuthorizedOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/registerPreAuth.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrderPreAuth('eee-eee-eee', 1200, 'https://github.com/custanator/sberbank-ecom-acquiring-client', ['currency' => 330]);
    }

    /**
     * @testdox Throws an exception if a "jsonParams" is not an array.
     */
    public function testThrowsAnExceptionIfAJsonParamsIsNotAnArray()
    {
        $client = new Client(['userName' => 'rustam', 'password' => 'qwerty123']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "jsonParams" parameter must be an array.');

        $client->registerOrder(1, 1, 'returnUrl', ['jsonParams' => '{}']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "jsonParams" parameter must be an array.');

        $client->registerOrderPreAuth(1, 1, 'returnUrl', ['jsonParams' => '{}']);
    }

    /**
     * @testdox Encodes to JSON a "jsonParams" parameter.
     */
    public function testEncodesToJSONAJsonParamsParameter()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/register.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'jsonParams=%7B%22showApplePay%22%3Atrue%2C%22showGooglePay%22%3Atrue%7D&orderNumber=1&amount=1&returnUrl=returnUrl&token=abc'
        );

        $client = new Client([
            'token' => 'abc',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrder(1, 1, 'returnUrl', [
            'jsonParams' => [
                'showApplePay' => true,
                'showGooglePay' => true,
            ],
        ]);
    }

    /**
     * @testdox Encodes to JSON an "orderBundle" parameter.
     */
    public function testEncodesToJSONAnOrderBundleParameter()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/register.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'orderBundle=%7B%22items%22%3A%5B%22item1%22%2C%22item2%22%5D%7D&orderNumber=1&amount=1&returnUrl=returnUrl&token=abc'
        );

        $client = new Client([
            'token' => 'abc',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrder(1, 1, 'returnUrl', [
            'orderBundle' => [
                'items' => [
                    'item1',
                    'item2',
                ],
            ],
        ]);
    }

    public function testDepositsAPreAuthorizedOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/deposit.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=810&orderId=aaa-bbb-yyy&amount=1000&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->deposit('aaa-bbb-yyy', 1000, ['currency' => 810]);
    }

    public function testReversesAnOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/reverse.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=480&orderId=aaa-bbb-yyy&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->reverseOrder('aaa-bbb-yyy', ['currency' => 480]);
    }

    public function testRefundsAnOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/refund.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=456&orderId=aaa-bbb-yyy&amount=5050&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->refundOrder('aaa-bbb-yyy', 5050, ['currency' => 456]);
    }

    public function testGetsAnOrderStatus()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/rest/getOrderStatusExtended.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'currency=100&orderId=aaa-bbb-yyy&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->getOrderStatus('aaa-bbb-yyy', ['currency' => 100]);
    }

    public function testPaysAnOrderUsingBinding()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/paymentOrderBinding.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'language=en&mdOrder=xxx-yyy-zzz&bindingId=600&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->paymentOrderBinding('xxx-yyy-zzz', '600', ['language' => 'en']);
    }

    public function testBindsACard()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/bindCard.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'language=ru&bindingId=bbb000&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->bindCard('bbb000', ['language' => 'ru']);
    }

    public function testUnbindsACard()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/unbindCard.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'language=en&bindingId=uuu800&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->unBindCard('uuu800', ['language' => 'en']);
    }

    public function testGetsBindings()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/ecomm/gw/partner/api/v1/getBindings.do',
            'POST',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'language=ru&clientId=clientIDABC&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->getBindings('clientIDABC', ['language' => 'ru']);
    }

    public function testAddsASpecialPrefixToActionForBackwardCompatibility()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with($this->equalTo(Client::API_URI . '/ecomm/gw/partner/api/v1/getOrderStatusExtended.do'));

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'apiUri' => Client::API_URI,
        ]);

        $client->execute('getOrderStatusExtended.do');
    }

    private function mockHttpClient(array $response = null)
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        if (null === $response) {
            $response = [200, \json_encode(['errorCode' => 0, 'errorMessage' => 'No error.'])];
        }

        $httpClient
            ->method('request')
            ->willReturn($response);

        return $httpClient;
    }

    private function getHttpClientToTestSendingData(string $uri, string $method, array $headers = [], string $data = '')
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringEndsWith($uri),
                $this->equalTo($method),
                $this->equalTo($headers),
                $this->equalTo($data)
            );

        return $httpClient;
    }
}
