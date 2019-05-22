<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 19:27
 */

namespace Icekson\FeignClient\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Class Header
 * @Annotation
 * @Target({"METHOD"})
 */
class Header extends Annotation implements ApiMethodAnnotation
{
    public $name;
    public $value;
}