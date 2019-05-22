<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 19:05
 */

namespace Icekson\FeignClient\ResponseConfiguration;


use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Response;

class ResponseMetadata implements ResponseMetadataInterface
{
    /**
     * @var HeaderBag
     */
    private $_headers;
    /**
     * @var int
     */
    private $_status;

    public function __construct($status = Response::HTTP_OK, $headers = null)
    {
        if ($headers === null) {
            $headers = [];
        }
        $this->_status = $status;
        $this->_headers = new HeaderBag($headers);
    }

    public function headers(): HeaderBag
    {
        return $this->_headers;
    }

    public function status(): int
    {
        return $this->_status;
    }

}