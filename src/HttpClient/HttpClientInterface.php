<?php

declare(strict_types=1);

namespace custanator\SberbankEcomAcquiring\HttpClient;

use custanator\SberbankEcomAcquiring\Exception\NetworkException;

/**
 * Simple HTTP client interface.
 *
 * @author Rustam Salikhov <custanator@mail.ru>
 */
interface HttpClientInterface
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    /**
     * Send an HTTP request.
     *
     * @throws NetworkException
     *
     * @return array A response
     */
    public function request(string $uri, string $method = self::METHOD_GET, array $headers = [], string $data = ''): array;
}
