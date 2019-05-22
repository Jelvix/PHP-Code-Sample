<?php

namespace IceksonTests\FeighClient;

use App\Serializer\EventSubscriber\UserSubscriber;
use Icekson\FeignClient\Code\ServiceClassGenerator;
use Icekson\FeignClient\Model\User;
use Icekson\FeignClient\Services\Contracts\ExampleServiceInterface;
use Icekson\FeignClient\ApiClientBuilder;
use Icekson\FeignClient\ApiClientBuilderInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ApiClientBuilderTest extends TestCase
{
    private $filesPath;

    public function setUp()
    {
        $this->filesPath = realpath(dirname(__FILE__) . "/../../../") . "/var/cache/test/app/Api/Services/Generated";
    }

    private function prepareBuilder($userJson): ApiClientBuilderInterface
    {
        $serializer = \JMS\Serializer\SerializerBuilder::create()
            ->configureListeners(function(\JMS\Serializer\EventDispatcher\EventDispatcher $dispatcher) {
                $dispatcher->addSubscriber(new UserSubscriber());
            })
            ->build();
        $logger = $this->createMock(LoggerInterface::class);
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool
            ->expects($this->once())
            ->method('save');
        $cachePool
            ->expects($this->once())
            ->method('getItem')
            ->will($this->returnValue($this->createMock(CacheItemInterface::class)));


        $params = $this->createMock(ParameterBagInterface::class);
        $params
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with('app_services')
            ->willReturn([
                'generated_classes_path' => $this->filesPath,
                ExampleServiceInterface::class => [
                    'settings' => [
                        'base_url' => 'http://test.com',
                        'access_token' => 'test'
                    ]
                ]
            ]);

        $reader = new AnnotationReader();
        $adapter = new Local($this->filesPath);
        $filesystem = new Filesystem($adapter);
        $generator = new ServiceClassGenerator($reader, $filesystem);

        $bodyStream = $this->createMock(StreamInterface::class);
        $bodyStream->expects($this->once())
            ->method('getContents')
            ->willReturn($userJson);

        $resp = $this->createMock(Response::class);
        $resp->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue($bodyStream));

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->will($this->returnValue($resp));

        $builder = new ApiClientBuilder($serializer, $logger, $cachePool, $reader, $params, $generator, $client);
        return $builder;
    }

    /**
     * @dataProvider userProvider
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function testServiceInstanceGenerate($userJson)
    {

        $builder = $this->prepareBuilder($userJson);
        /** @var ExampleServiceInterface $apiClient */
        $apiClient = $builder->byType(ExampleServiceInterface::class)->create();
        $this->assertInstanceOf(ExampleServiceInterface::class, $apiClient, 'Api builder should build object for given interface');
        $user = $apiClient->getUser('test');

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(ExampleServiceInterface::class, $apiClient, 'Api builder should build object for given interface');

    }

    public function userProvider()
    {
        return [
            ['{"id":"aaa","email":"test@test.com","name":"test","usergroup":"user","created":111111,"status":"active","geo":{"country":"UA"},"fb":{"email":"asdasd@asdas.com"}}']
        ];
    }

}
