<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 19:21
 */

namespace Icekson\FeignClient\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Param
 * @Annotation
 * @Target({"METHOD"})
 */
class PathParam extends Annotation implements ApiMethodAnnotation
{
    public $name;

}