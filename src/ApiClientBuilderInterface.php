<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 18:53
 */

namespace Icekson\FeignClient;

interface ApiClientBuilderInterface
{
    function create(): ApiClientInterface;

    function host($host): ApiClientBuilderInterface;

    function accessToken($token): ApiClientBuilderInterface;

    function byName($serviceName): ApiClientBuilderInterface;

    function byType($serviceInterfaceName): ApiClientBuilderInterface;

}