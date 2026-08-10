<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

abstract class AbstractApiControllerTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $query
     */
    protected function createJsonRequest(string $method, array $data = [], array $query = []): Request
    {
        $request = Request::create('/', $method, $query, [], [], [], json_encode($data, JSON_THROW_ON_ERROR));
        $request->headers->set('CONTENT_TYPE', 'application/json');

        return $request;
    }

    /**
     * @param list<string> $roles
     * @param array<string, mixed> $parameters
     */
    protected function configureControllerContainer(object $controller, ?User $user = null, array $roles = ['ROLE_USER'], array $parameters = []): void
    {
        $tokenStorage = new TokenStorage();

        if ($user !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $roles));
        }

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(
            static function (string $attribute) use ($roles): bool {
                if ($attribute === 'ROLE_ADMIN') {
                    return in_array('ROLE_ADMIN', $roles, true);
                }

                if ($attribute === 'ROLE_USER') {
                    return in_array('ROLE_USER', $roles, true) || in_array('ROLE_ADMIN', $roles, true);
                }

                return false;
            }
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.authorization_checker', $authorizationChecker);
        $container->set('parameter_bag', new ParameterBag(array_merge([
            'kernel.project_dir' => sys_get_temp_dir(),
        ], $parameters)));

        $controller->setContainer($container);
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    protected function decodeJsonResponse(\Symfony\Component\HttpFoundation\Response $response): array|null
    {
        $content = $response->getContent();

        if ($content === '' || $content === false) {
            return null;
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
