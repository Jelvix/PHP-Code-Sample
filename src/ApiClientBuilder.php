<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 19:00
 */

namespace Icekson\FeignClient;

use Icekson\FeignClient\Code\ServiceClassGenerator;
use Doctrine\Common\Annotations\Reader;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class ApiClientBuilder implements ApiClientBuilderInterface
{
    public const GENERATED_SERVICE_NAMESPACE = 'App\\Api\\Services\\Generated';
    private const CACHE_KEY = '__app__api_client_service__generated_class__';
    private const CACHE_LIFETIME = 9999999999999;

    /**
     * @var CacheItemPoolInterface
     */
    private $cachePool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $_host;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var null | string
     */
    private $apiInterfaceName = null;

    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var string
     */
    private $_accessToken;

    /**
     * @var Reader
     */
    private $reader;
    /**
     * @var ServiceClassGenerator
     */
    private $classGenerator;
    /**
     * @var ClientInterface
     */
    private $client;


    public function __construct(SerializerInterface $serializer = null,
                                LoggerInterface $logger = null,
                                CacheItemPoolInterface $cache = null,
                                Reader $reader = null,
                                ParameterBagInterface $params = null,
                                ServiceClassGenerator $generator = null,
                                ClientInterface $client = null)
    {
        $this->serializer = $serializer;
        $this->cachePool = $cache;
        $this->logger = $logger;
        $this->reader = $reader? $reader: new AnnotationReader();
        $this->params = $params;
        $this->classGenerator = $generator;
        $this->client = $client ? $client : new Client();
    }

    public static function build()
    {
        return new self();
    }

    /**
     * @param SerializerInterface $serializer
     * @return ApiClientBuilder
     */
    public function setSerializer(SerializerInterface $serializer): ApiClientBuilderInterface
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * @param ClientInterface $client
     * @return ApiClientBuilderInterface
     */
    public function setHttpClient(ClientInterface $client): ApiClientBuilderInterface
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @param ParameterBagInterface $params
     * @return ApiClientBuilderInterface
     */
    public function setParametersBag(ParameterBagInterface $params): ApiClientBuilderInterface
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param string $path
     * @return ApiClientBuilderInterface
     */
    public function setGeneratedClassesPath(string $path): ApiClientBuilderInterface
    {
        $adapter = new Local($path);
        $filesystem = new Filesystem($adapter);
        $generator = new ServiceClassGenerator($this->reader, $filesystem);
        $this->classGenerator = $generator;
        return $this;
    }

    /**
     * @param CacheItemPoolInterface $cache
     * @return ApiClientBuilderInterface
     */
    public function setCachePool(CacheItemPoolInterface $cache): ApiClientBuilderInterface
    {
        $this->cachePool = $cache;
        return $this;
    }

    /**
     * @param LoggerInterface $logger
     * @return ApiClientBuilderInterface
     */
    public function setLogger(LoggerInterface $logger): ApiClientBuilderInterface
    {
        $this->logger = $logger;
        return $this;
    }


    /**
     * Create instance of client for configured API service
     * @return ApiClientInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function create(): ApiClientInterface
    {
        if ($this->apiInterfaceName === null) {
            throw new \InvalidArgumentException("Empty API interface name, call byType() method");
        }
        $settings = $this->params->get('app_services');
        $serviceSettings = isset($settings[$this->apiInterfaceName]) ? $settings[$this->apiInterfaceName] : [];

        if (!$this->_host && isset($serviceSettings['settings']['base_url'])) {
            $this->host($serviceSettings['settings']['base_url']);
        }

        if (!$this->_accessToken && isset($serviceSettings['settings']['access_token'])) {
            $this->accessToken($serviceSettings['settings']['access_token']);
        }

        return $this->generateServiceInstance();
    }

    /**
     * @param $host
     * @return ApiClientBuilderInterface
     */
    public function host($host): ApiClientBuilderInterface
    {
        $validator = Validation::createValidator();
        $errors = $validator->validate($host, [new Url()]);
        if ($errors->count() > 0) {
            throw new \InvalidArgumentException('Invalid host is given');
        }
        $this->_host = $host;
        return $this;
    }

    function accessToken($token): ApiClientBuilderInterface
    {
        $this->_accessToken = $token;
        return $this;
    }


    /**
     * @param $serviceInterfaceName
     * @param string|null $host
     * @return ApiClientBuilderInterface
     */
    public function byType($serviceInterfaceName, ?string $host = null): ApiClientBuilderInterface
    {
        if (!interface_exists($serviceInterfaceName)) {
            throw new \InvalidArgumentException('Invalid service interface name is given, interface does not exist');
        }
        $this->host($host);
        $this->apiInterfaceName = $serviceInterfaceName;
        return $this;
    }

    function byName($serviceName): ApiClientBuilderInterface
    {
        // TODO: Implement byName() method.
    }

    /**
     * Generate class based on given service interface, return instance of this class
     * @return ApiClientInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function generateServiceInstance(): ApiClientInterface
    {
        $config = $this->params->get('app_services');
        $path = $config['generated_classes_path'];
        $cacheKey = self::CACHE_KEY . '__' . str_replace('\\', '_', $this->apiInterfaceName);
        $cache = $this->cachePool->getItem($cacheKey);
        $needGenerate = false;
        if ($cache && $cache->isHit()) {
            $className = $cache->get();
            $filePath = $this->classNameToFilePath($className, $path);
            if (!file_exists($filePath)) {
                $needGenerate = true;
            }
        } else {
            $needGenerate = true;
        }

        if ($needGenerate) {
            $className = $this->classGenerator->generateClass($this->apiInterfaceName, self::GENERATED_SERVICE_NAMESPACE);
            $expiresAt = new \DateTime();
            $expiresAt->setTimestamp(time() + self::CACHE_LIFETIME);
            $cache->set($className);
            $this->cachePool->save($cache);
        }
        $filePath = $this->classNameToFilePath($className, $path);
        require_once $filePath;
        $client = new $className($this->_host, $this->_accessToken, $this->serializer, $this->logger, $this->reader, $this->client);
        return $client;
    }

    private function classNameToFilePath($className, $basePath)
    {
        $parts = explode('\\', $className);
        $fileName = end($parts);
        $filePath = $basePath . '/' . $fileName . '.php';
        return $filePath;
    }

}