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
    // Controllers and services share namespace shape, so route actions can auto-resolve their service.
    $serviceClass = str_replace(
      ['App\\Controllers\\', '\\Page', '\\Get', '\\Load', '\\Post', '\\Put', '\\Delete'],
      ['App\\Services\\', '\\PageService', '\\GetService', '\\LoadService', '\\PostService', '\\PutService', '\\DeleteService'],
      $controllerClass
    );

    if (!class_exists($serviceClass)) {
      return null;
    }

    return $this->container->get($serviceClass);
  }
}
