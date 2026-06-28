<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Container\ContainerInterface;

class ServiceResolver
{
  /**
   * Method __construct
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return void
   */
  public function __construct(private readonly ContainerInterface $container)
  {
  }

  /**
   * Method resolve
   *
   * @param string $controllerClass [explicit description]
   *
   * @return ?object
   */
  public function resolve(string $controllerClass): ?object
  {
    $serviceClass = str_replace('App\\Controllers\\', 'App\\Services\\', $controllerClass);
    $parts = explode('\\', $serviceClass);
    $action = array_pop($parts);
    $serviceActions = [
      'Page' => 'PageService',
      'Get' => 'GetService',
      'Load' => 'LoadService',
      'Post' => 'PostService',
      'Put' => 'PutService',
      'Delete' => 'DeleteService',
    ];

    if (!isset($serviceActions[$action])) {
      return null;
    }

    // 마지막 액션 클래스명만 Service명으로 바꿔 Delete\Post 같은 중첩 경로를 보존한다.
    $parts[] = $serviceActions[$action];
    $serviceClass = implode('\\', $parts);

    if (!class_exists($serviceClass)) {
      return null;
    }

    return $this->container->get($serviceClass);
  }
}
