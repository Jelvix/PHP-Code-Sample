<?php
/**
 * Created by PhpStorm.
 * User: Alexey
 * Date: 31.01.2019
 * Time: 0:30
 */

namespace Icekson\FeignClient;


use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class RetryHandler
{
    /**
     * @var int
     */
    private $retries;
    /**
     * @var int
     */
    private $delay;

    public function __construct(int $retries, int $delay)
    {
        $this->retries = $retries;
        $this->delay = $delay;
    }

    public function retryDecider()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
          //  echo 'retry: ' . $retries;
            // Limit the number of retries to 5
            if ($retries >= $this->retries) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // Retry on server errors
                if ($response->getStatusCode() >= 500) {
                    return true;
                }
            }

            return false;
        };
    }

    public function retryDelay()
    {
        return function ($numberOfRetries) {
            return 1000 * $this->delay;
        };
    }
}