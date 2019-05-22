<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 19:04
 */

namespace Icekson\FeignClient\ResponseConfiguration;

use Symfony\Component\HttpFoundation\HeaderBag;

interface ResponseMetadataInterface
{
    public function headers(): HeaderBag;

    public function status(): int;
}