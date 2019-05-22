<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 13:06
 */

namespace Icekson\FeignClient\ResponseConfiguration;


class ResponseConfigBuilder
{
    private $_sortBy;
    private $_order = 'asc';
    private $_page = 1;
    private $_limit = 100;
    private $_filters = [];

    public function sortBy($sort, $order = 'asc'): self
    {
        $this->_sortBy = $sort;
        $this->_order = $order;
        return $this;
    }

    public function page(int $page): self
    {
        $this->_page = $page;
        return $this;
    }

    public function limit(int $limit): self {
        $this->_limit = $limit;
        return $this;
    }

    public function filters(array $filters): self
    {
        $this->_filters = $filters;
        return $this;
    }

    public function build() : ResponseConfigInterface
    {
        return new ResponseConfig($this->_page, $this->_limit, $this->_sortBy, $this->_order, $this->_filters);
    }
}