<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Register;

use App\Repositories\Admin\MemberAuditLogRepository;
use App\Repositories\Common\BankRepository;
use App\Repositories\Common\UploadRepository;
use App\Services\BaseService;
use PDOException;
use Throwable;

class PostService extends BaseService
{
  private const ROLES = ['ADMIN', 'SELLER', 'VENDOR'];
  private const APPROVAL_STATUSES = ['APPROVED', 'PENDING', 'REJECTED'];
  private const STATUSES = ['ACTIVE', 'SUSPENDED', 'WITHDRAWN'];
  private const BOOLEAN_VALUES = ['0', '1'];

  /**
   * 회원 등록 데이터를 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $actorName = trim((string) ($params['actor_name'] ?? 'Admin')) ?: 'Admin';
    $ipAddress = isset($params['ip_address']) ? (string) $params['ip_address'] : null;

    $normalizedResult = $this->normalize($data);
    if (($normalizedResult['success'] ?? false) !== true) {
      return $normalizedResult;
    }

    $insertData = $normalizedResult['data'];

    $this->db->beginTransaction();
    try {
      $vendorCode = $this->repo?->issueVendorCode();
      $insertData['vendor_code'] = $vendorCode;
      $memberId = (int) $this->repo?->insertMember($insertData);
      $this->syncFileUsage($insertData);

      $auditRepo = $this->container->get(MemberAuditLogRepository::class);
      $auditRepo->insertLog($memberId, $actorName, 'CREATE', $this->buildCreateLogData($insertData), $ipAddress);

      $this->db->commit();
    } catch (PDOException $e) {
      $this->db->rollBack();
      if ($e->getCode() === '23000') {
        return $this->fail('ID, email, or vendor code is already in use.', 'user_id', 409);
      }

      throw $e;
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => 'Member has been created.',
      'redirect' => $this->adminUrl('/member/detail/' . $memberId),
      'data' => ['id' => $memberId],
    ];
  }

  /**
   * 입력 데이터를 저장 가능한 회원 데이터로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalize(array $data): array
  {
    $insertData = [];

    $userId = trim((string) ($data['user_id'] ?? ''));
    if ($userId === '') {
      return $this->fail('Invalid or duplicated ID.', 'user_id');
    }

    if (preg_match('/^[A-Za-z0-9_]{4,50}$/', $userId) !== 1) {
      return $this->fail('Invalid or duplicated ID.', 'user_id');
    }

    if ($this->repo?->isUserIdDuplicated($userId) === true) {
      return $this->fail('Invalid or duplicated ID.', 'user_id');
    }
    $insertData['user_id'] = $userId;

    $passwordNew = (string) ($data['password_new'] ?? '');
    $passwordConfirm = (string) ($data['password_confirm'] ?? '');
    if ($passwordNew === '') {
      return $this->fail('Password must be at least 8 characters.', 'password_new');
    }

    if (mb_strlen($passwordNew) < 8) {
      return $this->fail('Password must be at least 8 characters.', 'password_new');
    }

    if ($passwordConfirm === '') {
      return $this->fail('Password confirmation is invalid.', 'password_confirm');
    }

    if ($passwordNew !== $passwordConfirm) {
      return $this->fail('Password confirmation is invalid.', 'password_confirm');
    }
    $insertData['password'] = password_hash($passwordNew, PASSWORD_BCRYPT);

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      return $this->fail('Please enter a contact name.', 'name');
    }
    $insertData['name'] = $name;

    $mobileResult = $this->normalizeRequiredPhone($data, 'mobile', 'Mobile');
    if (($mobileResult['success'] ?? false) !== true) {
      return $mobileResult;
    }
    $insertData['mobile'] = $mobileResult['value'];

    $email = trim((string) ($data['email'] ?? ''));
    if ($email === '') {
      return $this->fail('Invalid or duplicated email.', 'email');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      return $this->fail('Invalid or duplicated email.', 'email');
    }

    if ($this->repo?->isEmailDuplicated($email) === true) {
      return $this->fail('Invalid or duplicated email.', 'email');
    }
    $insertData['email'] = $email;

    foreach (['is_email_agreed', 'is_sms_agreed'] as $field) {
      $value = (string) ($data[$field] ?? '');
      if (!in_array($value, self::BOOLEAN_VALUES, true)) {
        return $this->fail('Agreement value is invalid.', $field);
      }
      $insertData[$field] = (int) $value;
    }

    $role = (string) ($data['role'] ?? '');
    if (!in_array($role, self::ROLES, true)) {
      return $this->fail('Please select a valid member role.', 'role');
    }
    $insertData['role'] = $role;

    $approvalStatus = (string) ($data['approval_status'] ?? '');
    if ($role !== 'ADMIN' && !in_array($approvalStatus, self::APPROVAL_STATUSES, true)) {
      return $this->fail('Please select a valid approval status.', 'approval_status');
    }

    $status = (string) ($data['status'] ?? '');
    if (!in_array($status, self::STATUSES, true)) {
      return $this->fail('Please select a valid account status.', 'status');
    }
    $insertData['status'] = $status;

    $roleResult = $this->normalizeRoleSpecificData($role, $data, $name);
    if (($roleResult['success'] ?? false) !== true) {
      return $roleResult;
    }

    $insertData = array_merge($insertData, $roleResult['data']);
    $insertData['approval_status'] = $role === 'ADMIN' ? null : $approvalStatus;
    $insertData['deposit'] = 0;
    $insertData['mileage'] = 0;
    $insertData['login_fail_count'] = 0;
    $insertData['locked_until'] = null;
    $insertData['last_login_at'] = null;

    return ['success' => true, 'data' => $insertData];
  }

  /**
   * 회원 유형별 수집 정책에 맞게 입력 데이터를 정규화합니다.
   *
   * @param string $role 회원 유형
   * @param array $data 입력 데이터
   * @param string $name 대표자명
   *
   * @return array
   */
  private function normalizeRoleSpecificData(string $role, array $data, string $name): array
  {
    if ($role === 'ADMIN') {
      return [
        'success' => true,
        'data' => [
          'company_name' => $name,
          'business_number' => null,
          'business_license_file' => null,
          'business_type' => null,
          'business_item' => null,
          'company_phone' => null,
          'fax' => null,
          'zipcode' => null,
          'address' => null,
          'address_detail' => null,
          'bank_name' => null,
          'account_number' => null,
          'account_holder' => null,
          'bank_book_file' => null,
        ],
      ];
    }

    $companyName = trim((string) ($data['company_name'] ?? ''));
    if ($companyName === '') {
      return $this->fail('Please enter a company name.', 'company_name');
    }

    $businessNumberResult = $this->normalizeBusinessNumber($data);
    if (($businessNumberResult['success'] ?? false) !== true) {
      return $businessNumberResult;
    }

    $businessLicenseFile = trim((string) ($data['business_license_file'] ?? ''));
    if ($businessLicenseFile === '') {
      return $this->fail('Please upload a business license image.', 'business_license_file');
    }

    if ($this->isValidUploadFile($businessLicenseFile, 'business-license') !== true) {
      return $this->fail('Please upload a valid business license image.', 'business_license_file');
    }

    $businessType = trim((string) ($data['business_type'] ?? ''));
    if ($businessType === '') {
      return $this->fail('Please enter a business type.', 'business_type');
    }

    $businessItem = trim((string) ($data['business_item'] ?? ''));
    if ($businessItem === '') {
      return $this->fail('Please enter a business item.', 'business_item');
    }

    $companyPhoneResult = $this->normalizeRequiredPhone($data, 'company_phone', 'Company phone');
    if (($companyPhoneResult['success'] ?? false) !== true) {
      return $companyPhoneResult;
    }

    $faxResult = $this->normalizeOptionalPhone($data, 'fax', 'Fax');
    if (($faxResult['success'] ?? false) !== true) {
      return $faxResult;
    }

    $zipCode = trim((string) ($data['zip_code'] ?? ''));
    if ($zipCode === '' || preg_match('/^\d{5}$/', $zipCode) !== 1) {
      return $this->fail('Please select a business address postal code.', 'zip_code');
    }

    $address = trim((string) ($data['address'] ?? ''));
    if ($address === '') {
      return $this->fail('Please select a business address.', 'address');
    }

    $roleData = [
      'company_name' => $companyName,
      'business_number' => $businessNumberResult['value'],
      'business_license_file' => $businessLicenseFile,
      'business_type' => $businessType,
      'business_item' => $businessItem,
      'company_phone' => $companyPhoneResult['value'],
      'fax' => $faxResult['value'],
      'zipcode' => $zipCode,
      'address' => $address,
      'address_detail' => trim((string) ($data['address_detail'] ?? '')),
    ];

    if ($role === 'SELLER') {
      return [
        'success' => true,
        'data' => array_merge($roleData, [
          'bank_name' => null,
          'account_number' => null,
          'account_holder' => null,
          'bank_book_file' => null,
        ]),
      ];
    }

    $bankName = trim((string) ($data['bank_name'] ?? ''));
    if ($bankName === '') {
      return $this->fail('Please select a valid bank.', 'bank_name');
    }

    if ($this->container->get(BankRepository::class)->activeBankExists($bankName) !== true) {
      return $this->fail('Please select a valid bank.', 'bank_name');
    }

    $accountNumber = trim((string) ($data['account_number'] ?? ''));
    if ($accountNumber === '') {
      return $this->fail('Please enter a valid account number.', 'account_number');
    }

    if (preg_match('/^[0-9-]+$/', $accountNumber) !== 1) {
      return $this->fail('Please enter a valid account number.', 'account_number');
    }

    $accountHolder = trim((string) ($data['account_holder'] ?? ''));
    if ($accountHolder === '') {
      return $this->fail('Please enter an account holder.', 'account_holder');
    }

    $bankBookFile = trim((string) ($data['bank_book_file'] ?? ''));
    if ($bankBookFile === '') {
      return $this->fail('Please upload a bank book image.', 'bank_book_file');
    }

    if ($this->isValidUploadFile($bankBookFile, 'bank-book') !== true) {
      return $this->fail('Please upload a valid bank book image.', 'bank_book_file');
    }

    return [
      'success' => true,
      'data' => array_merge($roleData, [
        'bank_name' => $bankName,
        'account_number' => $accountNumber,
        'account_holder' => $accountHolder,
        'bank_book_file' => $bankBookFile,
      ]),
    ];
  }

  /**
   * 필수 전화번호를 하이픈 포함 문자열로 조립합니다.
   *
   * @param array $data 입력 데이터
   * @param string $prefix 필드 접두어
   * @param string $label 화면 표시명
   *
   * @return array
   */
  private function normalizeRequiredPhone(array $data, string $prefix, string $label): array
  {
    $parts = $this->getPhoneParts($data, $prefix);
    if (in_array('', $parts, true)) {
      return $this->fail($label . ' is invalid.', $prefix . '_1');
    }

    if ($this->hasInvalidPhonePart($parts)) {
      return $this->fail($label . ' is invalid.', $prefix . '_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 선택 전화번호를 하이픈 포함 문자열로 조립합니다.
   *
   * @param array $data 입력 데이터
   * @param string $prefix 필드 접두어
   * @param string $label 화면 표시명
   *
   * @return array
   */
  private function normalizeOptionalPhone(array $data, string $prefix, string $label): array
  {
    $parts = $this->getPhoneParts($data, $prefix);
    if ($parts === ['', '', '']) {
      return ['success' => true, 'value' => null];
    }

    if (in_array('', $parts, true)) {
      return $this->fail($label . ' is invalid.', $prefix . '_1');
    }

    if ($this->hasInvalidPhonePart($parts)) {
      return $this->fail($label . ' is invalid.', $prefix . '_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 전화번호 조각 배열을 가져옵니다.
   *
   * @param array $data 입력 데이터
   * @param string $prefix 필드 접두어
   *
   * @return array
   */
  private function getPhoneParts(array $data, string $prefix): array
  {
    return [
      trim((string) ($data[$prefix . '_1'] ?? '')),
      trim((string) ($data[$prefix . '_2'] ?? '')),
      trim((string) ($data[$prefix . '_3'] ?? '')),
    ];
  }

  /**
   * 전화번호 조각에 숫자가 아닌 값이 있는지 확인합니다.
   *
   * @param array $parts 전화번호 조각
   *
   * @return bool
   */
  private function hasInvalidPhonePart(array $parts): bool
  {
    foreach ($parts as $part) {
      if (preg_match('/^\d+$/', $part) !== 1) {
        return true;
      }
    }

    return false;
  }

  /**
   * 사업자등록번호를 검증하고 조립합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizeBusinessNumber(array $data): array
  {
    $parts = [
      trim((string) ($data['business_number_1'] ?? '')),
      trim((string) ($data['business_number_2'] ?? '')),
      trim((string) ($data['business_number_3'] ?? '')),
    ];

    if (in_array('', $parts, true)) {
      return $this->fail('Please enter a valid business registration number.', 'business_number_1');
    }

    if (
      preg_match('/^\d{3}$/', $parts[0]) !== 1
      || preg_match('/^\d{2}$/', $parts[1]) !== 1
      || preg_match('/^\d{5}$/', $parts[2]) !== 1
    ) {
      return $this->fail('Please enter a valid business registration number.', 'business_number_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 새로 사용된 업로드 파일의 사용 상태를 동기화합니다.
   *
   * @param array $data 저장 데이터
   *
   * @return void
   */
  private function syncFileUsage(array $data): void
  {
    $uploadRepo = $this->container->get(UploadRepository::class);
    foreach (['business_license_file', 'bank_book_file'] as $field) {
      $filePath = (string) ($data[$field] ?? '');
      if ($filePath !== '') {
        $uploadRepo->markAsUsed($filePath);
      }
    }
  }

  /**
   * 업로드 장부에 등록된 파일인지 확인합니다.
   *
   * @param string $filePath 파일 경로
   * @param string $category 업로드 분류
   *
   * @return bool
   */
  private function isValidUploadFile(string $filePath, string $category): bool
  {
    if ($filePath === '') {
      return false;
    }

    return $this->container
      ->get(UploadRepository::class)
      ->existsByPathAndCategory($filePath, $category);
  }

  /**
   * 생성 감사 로그에 기록할 데이터를 구성합니다.
   *
   * @param array $data 저장 데이터
   *
   * @return array
   */
  private function buildCreateLogData(array $data): array
  {
    $logData = $data;
    unset($logData['password']);
    $logData['password'] = ['old' => null, 'new' => '(등록됨)'];

    foreach ($logData as $field => $value) {
      if (is_array($value) && array_key_exists('new', $value)) {
        continue;
      }

      $logData[$field] = ['old' => null, 'new' => $value];
    }

    return $logData;
  }

  /**
   * 관리자 경로를 포함한 내부 URL을 생성합니다.
   *
   * @param string $path 관리자 하위 경로
   *
   * @return string
   */
  private function adminUrl(string $path): string
  {
    $settings = $this->container->get('settings');
    $adminDir = (string) ($settings['site']['admin_directory'] ?? 'dmmt');

    return '/' . trim($adminDir, '/') . $path;
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param string $field 오류 필드명
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}
