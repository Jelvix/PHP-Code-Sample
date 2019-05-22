<?php
/**
 * Created by PhpStorm.
 * User: alexey.itsekson
 * Date: 08.02.2019
 * Time: 13:05
 */

namespace Icekson\FeignClient\ResponseConfiguration;


use Symfony\Component\HttpFoundation\Request;

class ResponseConfig implements ResponseConfigInterface, ArrayConvertable
{
    private const SORT_KEY = 'sort';
    private const SORT_DIR_KEY = 'order';
    private const FILTER_KEY = 'filters';
    private const PAGE_KEY = 'page';
    private const PAGE_LIMIT_KEY = 'limit';
    public const DATA_KEY = 'data';
    public const DEFAULT_COUNT_PER_PAGE = 100;

    /**
     * @var int
     */
    private $page;
    /**
     * @var int
     */
    private $limit;
    /**
     * @var int
     */
    private $sortBy;
    /**
     * @var int
     */
    private $order;
    /**
     * @var array
     */
    private $filters;

    public static function builder(): ResponseConfigBuilder
    {
        return new ResponseConfigBuilder();
    }

    public static function createFromRequest(Request $request): ResponseConfigInterface
    {
        return self::builder()
            ->page($request->query->getInt(self::PAGE_KEY, 1))
            ->limit($request->query->getInt(self::PAGE_LIMIT_KEY, self::DEFAULT_COUNT_PER_PAGE))
            ->sortBy($request->query->get(self::SORT_KEY), $request->query->get(self::SORT_DIR_KEY, 'asc'))
            ->filters($request->query->get(self::FILTER_KEY, []))
            ->build();

    }

    public function __construct($page, $limit, $sortBy, $order, $filters)
    {
        $this->page = $page;
        $this->limit = $limit;
        $this->sortBy = $sortBy;
        $this->order = $order;
        $this->filters = $filters;
    }

    /**
     * {@inheritdoc}
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * {@inheritdoc}
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * {@inheritdoc}
     */
    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder(): string
    {
        return $this->order;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function toArray(): array
    {
        $res = [
            self::PAGE_KEY => $this->page,
            self::PAGE_LIMIT_KEY => $this->limit,
            self::FILTER_KEY => $this->filters,
        ];
        if ($this->sortBy !== null) {
            $res[self::SORT_KEY] = $this->sortBy;
            $res[self::SORT_DIR_KEY] = $this->order;
        }
        return $res;
    }


}