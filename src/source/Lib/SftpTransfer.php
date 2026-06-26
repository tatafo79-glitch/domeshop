<?php

declare(strict_types=1);

namespace App\Lib;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpTransfer
{
  /**
   * SFTP 서버로 파일을 전송합니다.
   *
   * @param array $config CDN SFTP 설정
   * @param string $localPath 로컬 임시 파일 경로
   * @param string $remotePath 원격 저장 경로
   *
   * @return void
   */
  public function upload(array $config, string $localPath, string $remotePath): void
  {
    $host = (string) ($config['host'] ?? '');
    $port = (int) ($config['port'] ?? 22);
    $user = (string) ($config['user'] ?? '');

    if ($host === '' || $user === '') {
      throw new \RuntimeException('CDN 접속 정보가 올바르지 않습니다.');
    }

    $sftp = new SFTP($host, $port > 0 ? $port : 22);
    $this->login($sftp, $config, $user);
    $this->ensureDirectory($sftp, dirname($remotePath));

    if (!$sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
      throw new \RuntimeException('이미지 파일을 CDN에 업로드하지 못했습니다.');
    }
  }

  /**
   * SFTP 서버의 파일을 삭제합니다.
   *
   * @param array $config CDN SFTP 설정
   * @param string $remotePath 원격 파일 경로
   *
   * @return void
   */
  public function delete(array $config, string $remotePath): void
  {
    $host = (string) ($config['host'] ?? '');
    $port = (int) ($config['port'] ?? 22);
    $user = (string) ($config['user'] ?? '');

    if ($host === '' || $user === '' || $remotePath === '') {
      return;
    }

    $sftp = new SFTP($host, $port > 0 ? $port : 22);
    $this->login($sftp, $config, $user);

    if ($sftp->file_exists($remotePath)) {
      $sftp->delete($remotePath);
    }
  }

  /**
   * 설정된 인증 방식으로 SFTP에 로그인합니다.
   *
   * @param SFTP $sftp SFTP 연결 객체
   * @param array $config CDN SFTP 설정
   * @param string $user 접속 계정
   *
   * @return void
   */
  private function login(SFTP $sftp, array $config, string $user): void
  {
    $authMethod = (string) ($config['auth_method'] ?? 'password');

    if ($authMethod === 'key') {
      $keyPath = (string) ($config['key_file_path'] ?? '');
      if ($keyPath === '' || !is_file($keyPath)) {
        throw new \RuntimeException('SFTP 키 파일을 찾을 수 없습니다.');
      }

      $key = PublicKeyLoader::loadPrivateKey(file_get_contents($keyPath), (string) ($config['key_passphrase'] ?? ''));
      if (!$sftp->login($user, $key)) {
        throw new \RuntimeException('SFTP 키 인증에 실패했습니다.');
      }

      return;
    }

    if (!$sftp->login($user, (string) ($config['password'] ?? ''))) {
      throw new \RuntimeException('SFTP 비밀번호 인증에 실패했습니다.');
    }
  }

  /**
   * 원격 디렉터리를 재귀적으로 생성합니다.
   *
   * @param SFTP $sftp SFTP 연결 객체
   * @param string $directory 원격 디렉터리 경로
   *
   * @return void
   */
  private function ensureDirectory(SFTP $sftp, string $directory): void
  {
    $normalized = str_replace('\\', '/', $directory);
    $segments = array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== ''));
    $path = str_starts_with($normalized, '/') ? '/' : '';

    foreach ($segments as $segment) {
      $path = rtrim($path, '/') . '/' . $segment;
      if (!$sftp->is_dir($path) && !$sftp->mkdir($path)) {
        throw new \RuntimeException('CDN 저장 디렉터리를 생성하지 못했습니다.');
      }
    }
  }
}