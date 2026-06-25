<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class SecureDb
{
  /**
   * Method __construct
   *
   * @param PDO $pdo [explicit description]
   *
   * @return void
   */
  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * Method fetchRow
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return ?array
   */
  public function fetchRow(string $sql, array $params = []): ?array
  {
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row === false ? null : $row;
  }

  /**
   * Method fetchAll
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return array
   */
  public function fetchAll(string $sql, array $params = []): array
  {
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
  }

  /**
   * Method execute
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return bool
   */
  public function execute(string $sql, array $params = []): bool
  {
    $statement = $this->pdo->prepare($sql);

    return $statement->execute($params);
  }

  /**
   * Method lastInsertId
   *
   * @return string
   */
  public function lastInsertId(): string
  {
    return $this->pdo->lastInsertId();
  }

  /**
   * Method beginTransaction
   *
   * @return bool
   */
  public function beginTransaction(): bool
  {
    return $this->pdo->beginTransaction();
  }

  /**
   * Method commit
   *
   * @return bool
   */
  public function commit(): bool
  {
    return $this->pdo->commit();
  }

  /**
   * Method rollBack
   *
   * @return bool
   */
  public function rollBack(): bool
  {
    return $this->pdo->rollBack();
  }
}
