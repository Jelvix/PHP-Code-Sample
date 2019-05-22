<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 18:50
 */

namespace Icekson\FeignClient\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Response
 * @Annotation
 * @Target({"CLASS"})
 */
class RemoteApiClient extends Annotation implements ApiMethodAnnotation
{
    public $name;
}