<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 16:03
 */

namespace Icekson\FeignClient\ResponseConfiguration;


interface ResponseConfigInterface
{
    /**
     * @return int
     */
    public function getPage(): int;

    /**
     * @return int
     */
    public function getLimit(): int;

    /**
     * @return string|null
     */
    public function getSortBy(): ?string;

    /**
     * @return string
     */
    public function getOrder(): string;

    /**
     * @return array
     */
    public function getFilters(): array;


}