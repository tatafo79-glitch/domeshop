<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Detail;

use App\Repositories\Admin\MemberAuditLogRepository;
use App\Repositories\Common\BankRepository;
use App\Repositories\Common\UploadRepository;
use App\Services\BaseService;
use Throwable;

class PutService extends BaseService
{
  private const ROLES = ['ADMIN', 'SELLER', 'VENDOR'];
  private const APPROVAL_STATUSES = ['APPROVED', 'PENDING', 'REJECTED'];
  private const STATUSES = ['ACTIVE', 'SUSPENDED', 'WITHDRAWN'];
  private const BOOLEAN_VALUES = ['0', '1'];

  /**
   * 회원 상세 정보를 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $memberId = (int) ($params['id'] ?? 0);
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $actorName = trim((string) ($params['actor_name'] ?? '관리자')) ?: '관리자';
    $ipAddress = isset($params['ip_address']) ? (string) $params['ip_address'] : null;

    if ($memberId <= 0) {
      return $this->fail('수정할 회원 정보가 올바르지 않습니다.', 'id');
    }

    $member = $this->repo?->getMemberById($memberId);
    if ($member === null) {
      return $this->fail('회원을 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'id', 404);
    }

    $normalizedResult = $this->normalize($data, $member);
    if (($normalizedResult['success'] ?? false) !== true) {
      return $normalizedResult;
    }

    $updateData = $normalizedResult['data'];
    $changedData = $this->buildChangedData($member, $updateData);
    if (isset($updateData['password'])) {
      $changedData['password'] = ['old' => '(변경 전 비밀번호)', 'new' => '(변경됨)'];
    }

    $this->db->beginTransaction();
    try {
      $this->repo?->updateMemberDetail($memberId, $updateData);
      $this->syncFileUsage($member, $updateData);

      if (count($changedData) > 0) {
        $auditRepo = $this->container->get(MemberAuditLogRepository::class);
        $auditRepo->insertLog($memberId, $actorName, 'UPDATE', $changedData, $ipAddress);
      }

      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '회원 정보가 저장되었습니다.',
      'data' => ['id' => $memberId],
    ];
  }

  /**
   * 폼 데이터를 저장 가능한 회원 데이터로 정규화합니다.
   *
   * @param array $data 폼 데이터
   * @param array $member 기존 회원 데이터
   *
   * @return array
   */
  private function normalize(array $data, array $member): array
  {
    $updateData = [];
    $memberId = (int) ($member['id'] ?? 0);

    $passwordNew = (string) ($data['password_new'] ?? '');
    $passwordConfirm = (string) ($data['password_confirm'] ?? '');
    if ($passwordNew !== '' || $passwordConfirm !== '') {
      if ($passwordNew === '') {
        return $this->fail('새 비밀번호를 입력해 주세요.', 'password_new');
      }

      if (mb_strlen($passwordNew) < 8) {
        return $this->fail('새 비밀번호는 8자 이상 입력해 주세요.', 'password_new');
      }

      if ($passwordConfirm === '') {
        return $this->fail('비밀번호 확인을 입력해 주세요.', 'password_confirm');
      }

      if ($passwordNew !== $passwordConfirm) {
        return $this->fail('비밀번호 확인이 새 비밀번호와 일치하지 않습니다.', 'password_confirm');
      }

      $updateData['password'] = password_hash($passwordNew, PASSWORD_BCRYPT);
    }

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      return $this->fail('대표자명을 입력해 주세요.', 'name');
    }
    $updateData['name'] = $name;

    $mobileResult = $this->normalizeRequiredPhone($data, 'mobile', '휴대폰');
    if (($mobileResult['success'] ?? false) !== true) {
      return $mobileResult;
    }
    $updateData['mobile'] = $mobileResult['value'];

    $email = trim((string) ($data['email'] ?? ''));
    if ($email === '') {
      return $this->fail('이메일을 입력해 주세요.', 'email');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      return $this->fail('이메일 형식이 올바르지 않습니다.', 'email');
    }

    if ($this->repo?->isEmailDuplicatedExceptId($email, $memberId) === true) {
      return $this->fail('이미 사용 중인 이메일입니다. 다른 이메일을 입력해 주세요.', 'email');
    }
    $updateData['email'] = $email;

    foreach (['is_email_agreed', 'is_sms_agreed'] as $field) {
      $value = (string) ($data[$field] ?? '');
      if (!in_array($value, self::BOOLEAN_VALUES, true)) {
        return $this->fail('수신동의 값이 올바르지 않습니다. 다시 선택해 주세요.', $field);
      }
      $updateData[$field] = (int) $value;
    }

    $role = (string) ($data['role'] ?? '');
    if (!in_array($role, self::ROLES, true)) {
      return $this->fail('회원 유형을 올바르게 선택해 주세요.', 'role');
    }
    $updateData['role'] = $role;

    $approvalStatus = (string) ($data['approval_status'] ?? '');
    if ($role !== 'ADMIN' && !in_array($approvalStatus, self::APPROVAL_STATUSES, true)) {
      return $this->fail('승인 상태를 올바르게 선택해 주세요.', 'approval_status');
    }

    $status = (string) ($data['status'] ?? '');
    if (!in_array($status, self::STATUSES, true)) {
      return $this->fail('계정 상태를 올바르게 선택해 주세요.', 'status');
    }
    $updateData['status'] = $status;

    $roleResult = $this->normalizeRoleSpecificData($role, $data, $name, $member);
    if (($roleResult['success'] ?? false) !== true) {
      return $roleResult;
    }

    $updateData = array_merge($updateData, $roleResult['data']);
    $updateData['approval_status'] = $role === 'ADMIN' ? null : $approvalStatus;

    return ['success' => true, 'data' => $updateData];
  }

  /**
   * 회원 유형별 수집 정책에 맞게 데이터를 정규화합니다.
   *
   * @param string $role 회원 유형
   * @param array $data 폼 데이터
   * @param string $name 대표자명
   * @param array $member 기존 회원 데이터
   *
   * @return array
   */
  private function normalizeRoleSpecificData(string $role, array $data, string $name, array $member): array
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
      return $this->fail('상호명을 입력해 주세요.', 'company_name');
    }

    $businessNumberResult = $this->normalizeBusinessNumber($data);
    if (($businessNumberResult['success'] ?? false) !== true) {
      return $businessNumberResult;
    }

    $businessLicenseFile = trim((string) ($data['business_license_file'] ?? ''));
    if ($businessLicenseFile === '') {
      return $this->fail('사업자등록증 이미지를 등록해 주세요.', 'business_license_file');
    }

    if ($this->isValidUploadFile($businessLicenseFile, 'business-license', (string) ($member['business_license_file'] ?? '')) !== true) {
      return $this->fail('사업자등록증 이미지를 다시 업로드해 주세요.', 'business_license_file');
    }

    $businessType = trim((string) ($data['business_type'] ?? ''));
    if ($businessType === '') {
      return $this->fail('업태를 입력해 주세요.', 'business_type');
    }

    $businessItem = trim((string) ($data['business_item'] ?? ''));
    if ($businessItem === '') {
      return $this->fail('종목을 입력해 주세요.', 'business_item');
    }

    $companyPhoneResult = $this->normalizeRequiredPhone($data, 'company_phone', '회사 전화');
    if (($companyPhoneResult['success'] ?? false) !== true) {
      return $companyPhoneResult;
    }

    $faxResult = $this->normalizeOptionalPhone($data, 'fax', '팩스');
    if (($faxResult['success'] ?? false) !== true) {
      return $faxResult;
    }

    $zipCode = trim((string) ($data['zip_code'] ?? ''));
    if ($zipCode === '' || preg_match('/^\d{5}$/', $zipCode) !== 1) {
      return $this->fail('사업장 주소의 우편번호를 검색해 선택해 주세요.', 'zip_code');
    }

    $address = trim((string) ($data['address'] ?? ''));
    if ($address === '') {
      return $this->fail('사업장 주소를 검색해 선택해 주세요.', 'address');
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
      return $this->fail('은행명을 선택해 주세요.', 'bank_name');
    }

    if ($this->container->get(BankRepository::class)->activeBankExists($bankName) !== true) {
      return $this->fail('은행명을 목록에서 다시 선택해 주세요.', 'bank_name');
    }
    $accountNumber = trim((string) ($data['account_number'] ?? ''));
    if ($accountNumber === '') {
      return $this->fail('계좌번호를 입력해 주세요.', 'account_number');
    }

    if (preg_match('/^[0-9-]+$/', $accountNumber) !== 1) {
      return $this->fail('계좌번호는 숫자와 하이픈만 입력해 주세요.', 'account_number');
    }

    $accountHolder = trim((string) ($data['account_holder'] ?? ''));
    if ($accountHolder === '') {
      return $this->fail('예금주를 입력해 주세요.', 'account_holder');
    }

    $bankBookFile = trim((string) ($data['bank_book_file'] ?? ''));
    if ($bankBookFile === '') {
      return $this->fail('통장사본 이미지를 등록해 주세요.', 'bank_book_file');
    }

    if ($this->isValidUploadFile($bankBookFile, 'bank-book', (string) ($member['bank_book_file'] ?? '')) !== true) {
      return $this->fail('통장사본 이미지를 다시 업로드해 주세요.', 'bank_book_file');
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
   * @param array $data 폼 데이터
   * @param string $prefix 필드 접두사
   * @param string $label 화면 표시명
   *
   * @return array
   */
  private function normalizeRequiredPhone(array $data, string $prefix, string $label): array
  {
    $parts = $this->getPhoneParts($data, $prefix);
    if (in_array('', $parts, true)) {
      return $this->fail($label . '을 모두 입력해 주세요.', $prefix . '_1');
    }

    if ($this->hasInvalidPhonePart($parts)) {
      return $this->fail($label . '은 숫자만 입력해 주세요.', $prefix . '_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 선택 전화번호를 하이픈 포함 문자열로 조립합니다.
   *
   * @param array $data 폼 데이터
   * @param string $prefix 필드 접두사
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
      return $this->fail($label . '을 입력하려면 모든 자리를 입력해 주세요.', $prefix . '_1');
    }

    if ($this->hasInvalidPhonePart($parts)) {
      return $this->fail($label . '는 숫자만 입력해 주세요.', $prefix . '_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 전화번호 파트 배열을 가져옵니다.
   *
   * @param array $data 폼 데이터
   * @param string $prefix 필드 접두사
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
   * @param array $data 폼 데이터
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
      return $this->fail('사업자 등록번호를 모두 입력해 주세요.', 'business_number_1');
    }

    if (
      preg_match('/^\d{3}$/', $parts[0]) !== 1
      || preg_match('/^\d{2}$/', $parts[1]) !== 1
      || preg_match('/^\d{5}$/', $parts[2]) !== 1
    ) {
      return $this->fail('사업자 등록번호는 3자리-2자리-5자리 숫자로 입력해 주세요.', 'business_number_1');
    }

    return ['success' => true, 'value' => implode('-', $parts)];
  }

  /**
   * 변경된 필드만 감사 로그 데이터로 구성합니다.
   *
   * @param array $oldData 기존 회원 데이터
   * @param array $newData 새 회원 데이터
   *
   * @return array
   */
  private function buildChangedData(array $oldData, array $newData): array
  {
    $changes = [];
    foreach ($newData as $field => $newValue) {
      if ($field === 'password') {
        continue;
      }

      $oldValue = $oldData[$field] ?? null;
      if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
        $changes[$field] = [
          'old' => $oldValue,
          'new' => $newValue,
        ];
      }
    }

    return $changes;
  }

  /**
   * 교체 또는 삭제된 업로드 파일의 사용 상태를 동기화합니다.
   *
   * @param array $oldData 기존 회원 데이터
   * @param array $newData 새 회원 데이터
   *
   * @return void
   */
  private function syncFileUsage(array $oldData, array $newData): void
  {
    $uploadRepo = $this->container->get(UploadRepository::class);
    foreach (['business_license_file', 'bank_book_file'] as $field) {
      if (!array_key_exists($field, $newData)) {
        continue;
      }

      $oldFile = (string) ($oldData[$field] ?? '');
      $newFile = (string) ($newData[$field] ?? '');
      if ($oldFile === $newFile) {
        continue;
      }

      if ($newFile !== '') {
        $uploadRepo->markAsUsed($newFile);
      }

      if ($oldFile !== '') {
        $uploadRepo->markAsUnused($oldFile);
      }
    }
  }

  /**
   * 새로 지정된 파일이 업로드 장부에 등록된 파일인지 확인합니다.
   *
   * @param string $filePath 파일 경로
   * @param string $category 업로드 분류
   * @param string $currentFilePath 기존 파일 경로
   *
   * @return bool
   */
  private function isValidUploadFile(string $filePath, string $category, string $currentFilePath = ''): bool
  {
    if ($filePath === '') {
      return false;
    }

    if ($filePath === $currentFilePath) {
      return true;
    }

    return $this->container
      ->get(UploadRepository::class)
      ->existsByPathAndCategory($filePath, $category);
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
