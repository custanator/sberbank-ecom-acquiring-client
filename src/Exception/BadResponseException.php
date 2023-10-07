<?php

declare(strict_types=1);

namespace custanator\SberbankEcomAcquiring\Exception;

/**
 * @author Rustam Salikhov <custanator@mail.ru>
 */
class BadResponseException extends SberbankAcquiringException
{
    /**
     * @var string
     */
    private $response;

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }
}
