# Server To Server Api Client

This project was created in order to show programming skills of PHP developers from Jelvix.

Current component was inspired by  [OpenFeign/feign](https://github.com/OpenFeign/feign), and makes much easier to develop S2S integrations.  

### Usage

Firstly, you need to create an interface which describes API service

```php
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
```
Than you can create and configure `ApiClientBuilder` and `ApiClient`.
`ApiClientBuilder` will generate class for you which implements passed interface. 

```php
use Icekson\FeignClient\Services\Contracts\ExampleServiceInterface;
use Icekson\FeignClient\ApiClientInterface

$serializer = \JMS\Serializer\SerializerBuilder::create();

// Create Api builder
/** @var ApiClientInterface */
$apiClient = ApiClientBuilder::build()
            ->setSerializer($serializer)
            ->setGeneratedClassesPath($somePath)            
            ->byType(ExampleServiceInterface::class, 'http://test.com')            
            ->create();

// Call some API that predefined in `ExampleServiceInterface`     
/** @var App\Model\User */            
$user = $apiClient->getUser($id)
```

**Note: FOR CODE DEMONSTRATION ONLY.**