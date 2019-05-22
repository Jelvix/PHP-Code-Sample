<?php

namespace Icekson\FeignClient\Services;

use Icekson\FeignClient\Annotation\ApiMethodAnnotation;
use Icekson\FeignClient\Annotation\Header;
use Icekson\FeignClient\Annotation\PathParam;
use Icekson\FeignClient\Annotation\Response as ApiResponse;
use Icekson\FeignClient\Annotation\Route;
use Icekson\FeignClient\Exception\ApiCallException;
use Icekson\FeignClient\MetadataInterface;
use Icekson\FeignClient\ResponseConfiguration\ArrayConvertable;
use Icekson\FeignClient\ResponseConfiguration\ResponseMetadata;
use Icekson\FeignClient\ResponseConfiguration\ResponseMetadataInterface;
use Icekson\FeignClient\ApiClientInterface;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Reader;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\DeserializationContext;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

abstract class AbstractService implements ApiClientInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $host;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var Reader
     */
    private $reader;

    private $classType = null;

    private $timeout = 30;

    private $defaultHeaders;

    private $accessToken;
    /**
     * @var ResponseMetadataInterface
     */
    private $metaData = null;

    public function __construct(
        $name,
        $host,
        $accessToken,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        Reader $reader,
        ClientInterface $client
    ) {
        $this->host = $host;
        $this->name = $name;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->reader = $reader;
        $this->client = $client;
        $this->accessToken = $accessToken;
        $this->metaData = new ResponseMetadata();
    }

    /**
     * @param $annotationProperties
     * @param $arguments
     * @return object|array
     * @throws ApiCallException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws HttpException
     * @throws BadRequestHttpException
     * @throws UnprocessableEntityHttpException
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     */
    protected function prepareAndCallApi($annotationProperties, $arguments)
    {
        $body = [];
        if (isset($annotationProperties['params'])) {
            foreach ($arguments as $argName => $arg) {
                $isPresent = false;
                foreach ($annotationProperties['params'] as $pathParamName) {
                    if ($pathParamName === $argName && isset($arguments[$pathParamName])) {
                        if (\is_string($arguments[$argName]->value) || is_numeric($arguments[$argName]->value)) {
                            $annotationProperties['uri'] = preg_replace("/\{" . $argName . "\}/",
                                $arguments[$argName]->value, $annotationProperties['uri']);
                        }
                        $isPresent = true;
                    }
                }
                if (!$isPresent) {
                    $body[$argName] = $arg->value;
                }
            }
        } else {
            foreach ($arguments as $argName => $arg) {
                $body[$argName] = $arg->value;
            }
        }
        $query = [];
        foreach ($body as $key => $item) {
            if ($item instanceof ArrayConvertable) {
                $query = array_merge($query, $item->toArray());
            } else {
                $query[$key] = $item;
            }
        }
        $annotationProperties['uri'] .= ($annotationProperties['method'] === 'GET' && count($query) > 0 ? '?' . $this->toQueryParamsString($query) : '');
        return $this->_callApi(
            $annotationProperties['method'],
            $annotationProperties['uri'],
            $annotationProperties['returnType'],
            $annotationProperties['method'] !== 'GET' ? $query : null,
            $annotationProperties['serializationGroups'] ?? []
        );
    }

    /**
     * @param string $method
     * @param string $uri
     * @param string $returnType
     * @param null $body
     * @param array|null $serializationGroups
     * @return object|array
     * @throws ApiCallException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws HttpException
     * @throws BadRequestHttpException
     * @throws UnprocessableEntityHttpException
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     */
    protected function _callApi(
        string $method,
        string $uri,
        string $returnType,
        $body = null,
        $serializationGroups = null
    ) {
        try {
            $response = $this->client->request($method, $this->host . $uri,
                ['json' => $body, 'headers' => ['Authorization' => 'Bearer ' . $this->accessToken]]);
            $res = $response->getBody()->getContents();
            $context = null;
            if ($serializationGroups) {
                $context = DeserializationContext::create();
                $context->setGroups($serializationGroups);
            }
            if (empty($res)) {
                throw new HttpException(Response::HTTP_NO_CONTENT, "No content was returned");
            }
            $r = $this->serializer->deserialize($res, $returnType, 'json', $context);
            $this->metaData = new ResponseMetadata($response->getStatusCode(), $response->getHeaders());
            return $r;
        } catch (ConnectException $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            throw new ServiceUnavailableHttpException(1,
                'Service unreachable: failed to connect to the service ' . $this->host, $ex);
        } catch (RequestException $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            $resp = $ex->getResponse();
            if (!$resp) {
                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR,
                    'Http internal error: ' . $ex->getMessage(), $ex);
            }
            $this->metaData = new ResponseMetadata($resp->getStatusCode(), $resp->getHeaders());
            switch ($resp->getStatusCode()) {
                case Response::HTTP_NOT_FOUND:
                    throw new NotFoundHttpException('Api endpoint \'' . $this->host . $uri . '\' not found', $ex);
                    break;
                case Response::HTTP_BAD_REQUEST:
                    throw new BadRequestHttpException('Bad request', $ex);
                    break;
                case Response::HTTP_INTERNAL_SERVER_ERROR:
                    throw new HttpException($ex->getMessage(), $ex);
                    break;
                case Response::HTTP_UNPROCESSABLE_ENTITY:
                    throw new UnprocessableEntityHttpException('Unproccessable entity sent', $ex);
                    break;
                case Response::HTTP_FORBIDDEN:
                    throw new AccessDeniedHttpException('Access denied to service ' . $this->host, $ex);
                    break;
                case Response::HTTP_UNAUTHORIZED:
                    throw new UnauthorizedHttpException('', 'Unauthorized access', $ex);
                    break;
                default:
                    throw new HttpException($resp->getStatusCode(), 'Http error', $ex);
            }
        } catch (HttpException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            throw new ApiCallException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return Annotation[]
     */
    private function getMethodAnnotations(\ReflectionMethod $method): array
    {
        try {
            if ($method !== null) {
                $annotations = $this->reader->getMethodAnnotations($method);
                return array_filter($annotations, function (Annotation $annotation) {
                    return $annotation instanceof ApiMethodAnnotation;
                });
            }

        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            throw new $ex;
        }
        return [];
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    protected function retrieveMethodProperties()
    {
        $e = new \Exception();
        $trace = $e->getTrace();
        //position 0 would be the line that called this function so we ignore it
        $lastCall = $trace[1];
        if (!isset($lastCall['function']) || !isset($lastCall['class'])) {
            throw new LogicException("retrieveMethodProperties has been called from wrong environment");
        }
        $props = [];
        $class = $this->getClassType();
        $method = $class->getMethod($lastCall['function']);
        $annotations = $this->getMethodAnnotations($method);
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Route) {
                $props['uri'] = $annotation->path;
                $props['method'] = $annotation->method;
            } else {
                if ($annotation instanceof Header) {
                    if (!isset($props['headers'])) {
                        $props['headers'] = [];
                    }
                    $props['headers'][$annotation->name] = $annotation->value;
                } else {
                    if ($annotation instanceof ApiResponse) {
                        $returnType = $method->getReturnType();
                        $props['returnType'] = $returnType && $returnType->getName() === 'array' ? 'array<' . $annotation->model . '>' : $annotation->model;
                        $props['serializationGroups'] = $annotation->serializationGroups ?? null;
                    } else {
                        if ($annotation instanceof PathParam) {
                            if (!isset($props['params'])) {
                                $props['params'] = [];
                            }
                            $props['params'][] = $annotation->name;
                        }
                    }
                }
            }
        }
        if (!isset($props['method'])) {
            $props['method'] = 'GET';
        }
        if (!isset($props['headers'])) {
            $props['headers'] = [
                'Content-Type' => 'application/json'
            ];
        }
        if (!isset($props['returnType'])) {
            $props['returnType'] = 'array';
        }
        if (!isset($props['uri'])) {
            throw new \InvalidArgumentException('setup @Route for service api');
        }
        return $props;
    }

    /**
     * @param $calledArgumentsValues
     * @return array
     * @throws \ReflectionException
     */
    protected function retrieveMethodArguments($calledArgumentsValues)
    {
        $e = new \Exception();
        $trace = $e->getTrace();
        //position 0 would be the line that called this function so we ignore it
        $lastCall = $trace[1];
        if (!isset($lastCall['function']) || !isset($lastCall['class'])) {
            throw new LogicException("retrieveMethodProperties has been called from wrong environment");
        }
        $class = $this->getClassType();
        $method = $class->getMethod($lastCall['function']);
        $arguments = $method->getParameters();
        $args = array_map(function (\ReflectionParameter $arg) use ($calledArgumentsValues) {
            return (object)[
                'name' => $arg->getName(),
                'value' => isset($calledArgumentsValues[$arg->getPosition()]) ? $calledArgumentsValues[$arg->getPosition()] : null
            ];
        }, $arguments);
        $res = [];
        foreach ($args as $arg) {
            $res[$arg->name] = $arg;
        }
        return $res;
    }

    /**
     * @return null|\ReflectionClass
     * @throws \ReflectionException
     */
    private function getClassType()
    {
        if ($this->classType === null) {
            $class = new \ReflectionClass($this);

            $interfaces = $class->getInterfaces();
            $implemtedInterface = null;
            foreach ($interfaces as $item) {
                if ($item->isInterface() && $item->isSubclassOf(ApiClientInterface::class)) {
                    $implemtedInterface = $item;
                }
            }
            if ($implemtedInterface === null) {
                throw new \LogicException('Service API class "' . $class->getName() . '" should implement ' . ApiClientInterface::class);
            }
            $this->classType = $implemtedInterface;
        }
        return $this->classType;
    }

    private function toQueryParamsString($arr)
    {
        $res = [];
        $keys = array_keys($arr);
        foreach ($keys as $key) {
            if (is_array($arr[$key])) {
                $res[] = $this->toQueryParamsStringAsArray($key, $arr[$key]);
            } else {
                $res[] = $key . '=' . $arr[$key];
            }
        }
        return implode('&', $res);
    }

    private function toQueryParamsStringAsArray($paramKey, $arr)
    {
        $res = [];
        $keys = array_keys($arr);
        foreach ($keys as $key) {
            $res[] = $paramKey . '[' . $key . ']' . '=' . $arr[$key];
        }
        return implode('&', $res);
    }

    public function getLastResponseMetaData(): ResponseMetadataInterface
    {
        return $this->metaData;
    }


}