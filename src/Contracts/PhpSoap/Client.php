<?php

declare(strict_types=1);

namespace RicorocksDigitalAgency\Soap\Contracts\PhpSoap;

interface Client
{
    /**
     * Set the given headers on the request.
     *
     * @param array<string, mixed> $headers
     */
    public function setHeaders(array $headers): static;

    /**
     * Make a request and return its response.
     */
    public function call(string $method, mixed $body): mixed;

    /**
     * Get an array of supported SOAP functions.
     *
     * @return array<int, string>
     */
    public function getFunctions(): array;

    public function __getLastRequest(): ?string;

    public function __getLastResponse(): ?string;

    public function __getLastRequestHeaders(): ?string;

    public function __getLastResponseHeaders(): ?string;
}