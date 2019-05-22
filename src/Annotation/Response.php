<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 18:44
 */

namespace Icekson\FeignClient\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Response
 * @Annotation
 * @Target({"METHOD"})
 */
class Response extends Annotation implements ApiMethodAnnotation
{
    public $model;
    public $serializationGroups = [];
}