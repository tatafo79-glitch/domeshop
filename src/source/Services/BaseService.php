<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\SecureDb;
use Psr\Container\ContainerInterface;

abstract class BaseService
{
  protected ?object $repo = null;

  /**
   * Method __construct
   *
   * @param ContainerInterface $container [explicit description]
   * @param SecureDb $db [explicit description]
   *
   * @return void
   */
  public function __construct(
    protected readonly ContainerInterface $container,
    protected readonly SecureDb $db
  ) {
    $this->repo = $this->resolveRepository();
  }

  /**
   * Method execute
   *
   * @param array $params [explicit description]
   *
   * @return array
   */
  abstract public function execute(array $params = []): array;

  /**
   * Method resolveRepository
   *
   * @return ?object
   */
  private function resolveRepository(): ?object
  {
    $class = static::class;
    $parts = explode('\\', $class);
    $adminIndex = array_search('Admin', $parts, true);

    if ($adminIndex === false || !isset($parts[$adminIndex + 1])) {
      return null;
    }

    // Services map to repositories by the first domain segment after Admin.
    $domain = $parts[$adminIndex + 1];
    $repoClass = 'App\\Repositories\\Admin\\' . $domain . 'Repository';

    if (!class_exists($repoClass)) {
      return null;
    }

    return $this->container->get($repoClass);
  }
}
