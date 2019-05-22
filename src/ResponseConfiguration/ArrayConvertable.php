<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 16:36
 */

namespace Icekson\FeignClient\ResponseConfiguration;


interface ArrayConvertable
{
    public function toArray(): array;
}