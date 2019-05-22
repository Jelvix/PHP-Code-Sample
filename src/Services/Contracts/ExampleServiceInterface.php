<?php
/**
 * @author a.itsekson
 * @createdAt: 24.01.2019 19:04
 */

namespace Icekson\FeignClient\Services\Contracts;

use Icekson\FeignClient\ResponseConfiguration\ResponseConfigInterface;
use Icekson\FeignClient\ApiClientInterface;
use Icekson\FeignClient\Annotation\{Route, Response, Header, PathParam, RemoteApiClient};

/**
 * Interface for Suite API
 * @RemoteApiClient(name="example")
 */
interface ExampleServiceInterface extends ApiClientInterface
{
    /**
     * @Route(path="/api/user")
     * @Response(model=User::class, serializationGroups={"external"})
     * @param ResponseConfigInterface|null $responseConfiguration
     * @return User[]
     */
    public function getUsers(ResponseConfigInterface $responseConfiguration = null): array;

    /**
     * @Route(path="/api/user/{id}")
     * @Response(model=User::class, serializationGroups={"external"})
     * @PathParam(name="id")
     * @param $id
     * @return User
     */
    public function getUser($id);

}