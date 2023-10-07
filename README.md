# sberbank-ecom-acquiring-client

PHP client for Sberbank Ecommerce REST API.

## Requirements

- PHP 7.1 or above
- TLS 1.2 or above
- `php-json` extension installed

## Installation

```sh
composer require 'custanator/sberbank-ecom-acquiring-client'
```

## Usage

### Instantiating a client

In most cases to instantiate a client you need to pass your username and password to a constructor:

```php
<?php

use custanator\SberbankEcomAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);
```

More advanced example:

```php
<?php

use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\Currency;
use custanator\SberbankEcomAcquiring\HttpClient\HttpClientInterface;

$client = new Client([
    'userName' => 'username',
    'password' => 'password',
    // A language code in ISO 639-1 format.
    // Use this option to set a language of error messages.
    'language' => 'ru',

    // A currency code in ISO 4217 format.
    // Use this option to set a currency used by default.
    'currency' => Currency::RUB,

    // An uri to send requests.
    // Use this option if you want to use the Sberbank's test server.
    'apiUri' => Client::API_URI_TEST,

    // An HTTP method to use in requests.
    // Must be "GET" or "POST" ("POST" is used by default).
    'httpMethod' => HttpClientInterface::METHOD_GET,

    // An HTTP client for sending requests.
    // Use this option when you don't want to use
    // a default HTTP client implementation distributed
    // with this package (for example, when you have'nt
    // a CURL extension installed in your server).
    'httpClient' => new YourCustomHttpClient(),
]);
```

Also you can use an adapter for the [Guzzle](https://github.com/guzzle/guzzle):

```php
<?php

use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\HttpClient\GuzzleAdapter;

use GuzzleHttp\Client as Guzzle;

$client = new Client(
    'userName' => 'username',
    'password' => 'password',
    'httpClient' => new GuzzleAdapter(new Guzzle()),
]);
```

Also, there are available adapters for [Symfony](https://symfony.com/doc/current/http_client.html) and [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP clents.

### Low level method "execute"

You can interact with the Sberbank REST API using a low level method `execute`:

```php
$client->execute('/ecomm/gw/partner/api/v1/register.do', [
    'orderNumber' => 1111,
    'amount' => 10,
    'returnUrl' => 'http://localhost/sberbank/success',
]);

$status = $client->execute('/ecomm/gw/partner/api/v1/getOrderStatusExtended.do', [
    'orderId' => '64fc8831-a2b0-721b-64fc-883100001553',
]);
```

But it's more convenient to use one of the shortcuts listed below.

### Creating a new order

[/ecomm/gw/partner/api/v1/register.do](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/register)

```php
<?php

use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\Currency;

$client = new Client(['userName' => 'username', 'password' => 'password']);

// Required arguments
$orderId     = 1234;
$orderAmount = 1000;
$returnUrl   = 'http://mycoolshop.local/payment-success';

// You can pass additional parameters like a currency code and etc.
$params['currency'] = Currency::EUR;
$params['failUrl']  = 'http://mycoolshop.local/payment-failure';

$result = $client->registerOrder($orderId, $orderAmount, $returnUrl, $params);

$paymentOrderId = $result['orderId'];
$paymentFormUrl = $result['formUrl'];

header('Location: ' . $paymentFormUrl);
```

If you want to use UUID identifiers ([ramsey/uuid](https://github.com/ramsey/uuid)) for orders you should convert them to a hex format:

```php
use Ramsey\Uuid\Uuid;

$orderId = Uuid::uuid4();

$result = $client->registerOrder($orderId->getHex(), $orderAmount, $returnUrl);
```

Use a `registerOrderPreAuth` method to create a 2-step order.

### Getting a status of an exising order

[/ecomm/gw/partner/api/v1/getOrderStatusExtended.do](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/getOrderStatusExtended)

```php
<?php

use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\OrderStatus;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->getOrderStatus($orderId);

if (OrderStatus::isDeposited($result['orderStatus'])) {
    echo "Order #$orderId is deposited!";
}

if (OrderStatus::isDeclined($result['orderStatus'])) {
    echo "Order #$orderId was declined!";
}
```

Also, you can get an order's status by using you own identifier (e.g. assigned by your database):

```php
<?php

use custanator\SberbankEcomAcquiring\Client;
use custanator\SberbankEcomAcquiring\OrderStatus;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->getOrderStatusByOwnId($orderId);
```

### Reversing an exising order

[/ecomm/gw/partner/api/v1/reverse.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:reverse)

```php
<?php

use custanator\SberbankEcomAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->reverseOrder($orderId);
```

### Refunding an exising order

[/ecomm/gw/partner/api/v1/refund.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund)

```php
<?php

use custanator\SberbankEcomAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->refundOrder($orderId, $amountToRefund);
```

---

See `Client` source code to find methods for payment bindings and dealing with 2-step payments.

## License

Based on [https://github.com/voronkovich/sberbank-acquiring-client](https://github.com/voronkovich/sberbank-acquiring-client)
Voronkovich Oleg.

Distributed under the MIT.
