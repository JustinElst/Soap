<?php

declare(strict_types=1);

namespace RicorocksDigitalAgency\Soap\Request;

use Closure;
use Illuminate\Support\Collection;
use RicorocksDigitalAgency\Soap\Contracts\Builder;
use RicorocksDigitalAgency\Soap\Contracts\Client;
use RicorocksDigitalAgency\Soap\Contracts\Request;
use RicorocksDigitalAgency\Soap\Header;
use RicorocksDigitalAgency\Soap\Response\Response;
use RicorocksDigitalAgency\Soap\Support\DecoratedClient;
use RicorocksDigitalAgency\Soap\Support\Tracing\Trace;
use SoapClient;
use SoapHeader;

final class SoapClientRequest implements Request
{
    private Builder $builder;

    /**
     * @var Closure(string, array<string, mixed>): Client
     */
    private Closure $clientResolver;

    private Client $client;

    private string $endpoint;
    private string $method;

    /**
     * @var array<string, mixed>
     */
    private $body = [];

    private Response $response;

    /**
     * @var array{beforeRequesting: Collection<int, callable(Request): mixed>, afterRequesting: Collection<int, callable(Request, Response): mixed>}
     */
    private array $hooks;

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * @var array<int, Header>
     */
    private array $headers = [];

    /**
     * @param Closure(string $endpoint, array<string, mixed> $options): Client|null $clientResolver
     */
    public function __construct(Builder $builder, Closure $clientResolver = null)
    {
        $this->builder = $builder;
        $this->clientResolver = $clientResolver ?? fn (string $endpoint, array $options) => new DecoratedClient(new SoapClient($endpoint, $options));

        $this->hooks = [
            'beforeRequesting' => collect(),
            'afterRequesting' => collect(),
        ];
    }

    public function to(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function __call(string $name, array $parameters): mixed
    {
        return $this->call($name, $parameters[0] ?? []);
    }

    public function call(string $method, array $body = []): Response
    {
        $this->method = $method;
        $this->body = $body;

        $this->hooks['beforeRequesting']->each(fn ($callback) => $callback($this));
        $this->body = $this->builder->handle($this->body);

        $response = $this->getResponse();
        $this->hooks['afterRequesting']->each(fn ($callback) => $callback($this, $response));

        return $response;
    }

    private function getResponse(): Response
    {
        return $this->response ??= $this->getRealResponse();
    }

    private function getRealResponse(): Response
    {
        return tap(
            Response::new($this->makeRequest()),
            fn ($response) => data_get($this->options, 'trace')
                ? $response->setTrace(Trace::client($this->client()))
                : $response
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRequest(): array
    {
        return $this->client()->call($this->getMethod(), $this->getBody());
    }

    private function client(): Client
    {
        return $this->client ??= call_user_func($this->clientResolver, $this->endpoint, $this->options)->setHeaders($this->constructHeaders());
    }

    /**
     * @return array<int, SoapHeader>
     */
    private function constructHeaders(): array
    {
        if (empty($this->headers)) {
            return [];
        }

        return array_map(fn ($header) => new SoapHeader(
            $header->namespace,
            $header->name,
            $header->data,
            $header->mustUnderstand,
            $header->actor ?? SOAP_ACTOR_NONE
        ), $this->headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @return array<int, string>
     */
    public function functions(): array
    {
        return $this->client()->getFunctions();
    }

    /**
     * @param callable(Request): mixed ...$closures
     */
    public function beforeRequesting(callable ...$closures): self
    {
        $this->hooks['beforeRequesting']->push(...$closures);

        return $this;
    }

    /**
     * @param callable(Request, Response): mixed ...$closures
     */
    public function afterRequesting(callable ...$closures): self
    {
        $this->hooks['afterRequesting']->push(...$closures);

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @param callable(Request): Response|Response|null $response
     */
    public function fakeUsing(Response|callable|null $response): self
    {
        if (empty($response)) {
            return $this;
        }

        $this->response = $response instanceof Response ? $response : $response($this);

        return $this;
    }

    public function set(string $key, mixed $value): self
    {
        data_set($this->body, $key, $value);

        return $this;
    }

    public function trace(bool $shouldTrace = true): self
    {
        $this->options['trace'] = $shouldTrace;

        return $this;
    }

    public function withBasicAuth(string $login, string $password): self
    {
        $this->options['authentication'] = SOAP_AUTHENTICATION_BASIC;
        $this->options['login'] = $login;
        $this->options['password'] = $password;

        return $this;
    }

    public function withDigestAuth(string $login, string $password): Request
    {
        $this->options['authentication'] = SOAP_AUTHENTICATION_DIGEST;
        $this->options['login'] = $login;
        $this->options['password'] = $password;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): Request
    {
        $this->options = array_merge($this->getOptions(), $options);

        return $this;
    }

    public function withHeaders(Header ...$headers): Request
    {
        $this->headers = array_merge($this->getHeaders(), $headers);

        return $this;
    }

    /**
     * @return array<int, Header>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
