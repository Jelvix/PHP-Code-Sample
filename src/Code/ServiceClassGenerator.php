<?php
/**
 * @author a.itsekson
 * @createdAt: 25.01.2019 17:09
 */

namespace Icekson\FeignClient\Code;

use Icekson\FeignClient\Annotation\RemoteApiClient;
use Icekson\FeignClient\Annotation\Route;
use Icekson\FeignClient\ResponseConfiguration\ResponseConfigInterface;
use Icekson\FeignClient\Services\AbstractService;
use Doctrine\Common\Annotations\Reader;
use GuzzleHttp\ClientInterface;
use JMS\Serializer\SerializerInterface;
use League\Flysystem\FilesystemInterface;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Psr\Log\LoggerInterface;

/**
 * Code generator for API services classes
 * Class ServiceClassGenerator
 * @package Icekson\FeignClient\Code
 */
class ServiceClassGenerator
{
    /**
     * @var Reader
     */
    private $reader;
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(Reader $reader, FilesystemInterface $filesystem)
    {
        $this->reader = $reader;
        $this->filesystem = $filesystem;
    }

    /**
     * @param string $interface
     * @param string $classNamespace
     * @return string generated class name
     * @throws \ReflectionException
     */
    public function generateClass(string $interface, string $classNamespace): string
    {
        $parts = explode('\\', $interface);
        $hash = hash('sha256', uniqid());
        $className = preg_replace("/^(\w+)(Interface)/i", "$1", end($parts)) . '_' . substr($hash, strlen($hash) - 10,
                strlen($hash));
        $type = new \ReflectionClass($interface);
        $classAnnotations = $this->reader->getClassAnnotations($type);
        $apiName = '';
        foreach ($classAnnotations as $classAnnotation) {
            if ($classAnnotation instanceof RemoteApiClient) {
                $apiName = $classAnnotation->name;
                break;
            }
        }

        $file = new PhpFile();
        $file->addComment('This file is auto-generated, DO NOT CHANGE IT MANUALLY!!!');
        $namespace = $file->addNamespace($classNamespace);
        $namespace
            ->addUse(AbstractService::class)
            ->addUse($interface)
            ->addUse(Reader::class)
            ->addUse(LoggerInterface::class)
            ->addUse(SerializerInterface::class)
            ->addUse(ResponseConfigInterface::class)
            ->addUse(ClientInterface::class);

        $class = $namespace->addClass($className);
        $class
            ->addImplement($interface)
            ->addExtend(AbstractService::class);

        $method = $class->addMethod('__construct');
        $method->addComment($className . ' constructor.')
            ->addComment('@param $host')
            ->addComment('@param $accessToken')
            ->addComment('@param SerializerInterface $serializer')
            ->addComment('@param LoggerInterface $logger')
            ->addComment('@param Reader $reader')
            ->addComment('@param ClientInterface $client');
        $method->addParameter('host')->setTypeHint('string');
        $method->addParameter('accessToken')->setTypeHint('string');
        $method->addParameter('serializer')->setTypeHint(SerializerInterface::class);
        $method->addParameter('logger')->setTypeHint(LoggerInterface::class);
        $method->addParameter('reader')->setTypeHint(Reader::class);
        $method->addParameter('client')->setTypeHint(ClientInterface::class);
        $method->setBody('
        parent::__construct("' . $apiName . '", $host, $accessToken, $serializer, $logger, $reader, $client);
        ');

        $methods = $type->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $methodRef) {
            $returnType = $methodRef->getReturnType();
            $modelType = null;
            $mAnnotations = $this->reader->getMethodAnnotations($methodRef);
            $routeFound = false;
            foreach ($mAnnotations as $mAnnotation) {
                if ($mAnnotation instanceof Route) {
                    $routeFound = true;
                    break;
                }
            }
            if (!$routeFound) {
                continue;
            }
            $class->addMethod($methodRef->getName())
                ->addComment('{@inherits}')
                ->setReturnType($returnType)
                ->setParameters(array_map(function (\ReflectionParameter $paramRef) {
                    $p = new Parameter($paramRef->getName());
                    $paramType = $paramRef->getType();
                    if ($paramType !== null) {
                        $p->setTypeHint($paramType->getName());
                    }
                    if ($paramRef->isDefaultValueAvailable()) {
                        $p->setDefaultValue($paramRef->getDefaultValue());
                    }
                    return $p;
                }, $methodRef->getParameters()))
                ->setBody('$props = $this->retrieveMethodProperties();
$args = $this->retrieveMethodArguments(func_get_args());
return $this->prepareAndCallApi($props, $args);
            ');
        }

        $printer = new PsrPrinter();
        $this->filesystem->put(
            "/{$className}.php",
            $printer->printFile($file)
        );

        return $namespace->getName() . "\\" . $class->getName();
    }
}