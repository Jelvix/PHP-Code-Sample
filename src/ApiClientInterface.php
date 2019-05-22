<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 18:40
 */

namespace Icekson\FeignClient;

use Icekson\FeignClient\ResponseConfiguration\ResponseMetadataInterface;

interface ApiClientInterface
{
    public function getLastResponseMetaData(): ResponseMetadataInterface;
}