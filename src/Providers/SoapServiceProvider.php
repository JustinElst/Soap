<?php

namespace RicorocksDigitalAgency\Soap\Providers;

use Illuminate\Support\ServiceProvider;
use RicorocksDigitalAgency\Soap\Parameters\Builder;
use RicorocksDigitalAgency\Soap\Parameters\IntelligentBuilder;
use RicorocksDigitalAgency\Soap\Request\Request;
use RicorocksDigitalAgency\Soap\Request\SoapClientRequest;
use RicorocksDigitalAgency\Soap\Soap;
use RicorocksDigitalAgency\Soap\Support\Fakery\Fakery;

class SoapServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton('soap', fn() => app(Soap::class, ['fakery' => app(Fakery::class)]));
        $this->app->bind(Request::class, SoapClientRequest::class);
        $this->app->bind(Builder::class, IntelligentBuilder::class);
    }

    public function boot()
    {
        require_once __DIR__ . '/../helpers.php';
    }

}
