interface MemberDetailResponse {
  success: boolean;
  message?: string;
  field?: string;
  redirect?: string;
  data?: {
    id?: number;
  };
}

interface ValidationResult {
  message: string;
  field: string;
}

interface DomemallImageUploadResult {
  id: number;
  originalName: string;
  filePath: string;
  fileUrl: string;
  mimeType: string;
  fileSize: number;
}

interface DomemallImageUploadOptions {
  file: File;
  category: string;
  csrfRoot?: ParentNode;
}

interface DomemallImageUploadApi {
  upload: (options: DomemallImageUploadOptions) => Promise<DomemallImageUploadResult>;
  uploadFromInput: (input: HTMLInputElement, category: string) => Promise<DomemallImageUploadResult>;
}

interface MemberUploadBinding {
  inputId: string;
  hiddenName: string;
  category: string;
}

declare global {
  interface Window {
    DomemallImageUpload?: DomemallImageUploadApi;
  }
}

const form = document.querySelector<HTMLFormElement>('#memberDetailForm');
const submitButtons = form
  ? Array.from(document.querySelectorAll<HTMLButtonElement>('button[form="memberDetailForm"], #memberDetailForm button[type="submit"]'))
  : [];
const isRegisterForm = form?.dataset.memberFormMode === 'register';

const roleAllowlist = ['ADMIN', 'SELLER', 'VENDOR'];
const approvalStatusAllowlist = ['APPROVED', 'PENDING', 'REJECTED'];
const statusAllowlist = ['ACTIVE', 'SUSPENDED', 'WITHDRAWN'];
const numericPattern = /^\d+$/;
const memberDetailActiveTabStorageKey = 'adminMemberDetailActiveTab';

const findField = (fieldName: string | undefined): HTMLElement | null => {
  if (!fieldName) {
    return null;
  }

  const focusFieldMap: Record<string, string> = {
    business_license_file: 'business_license_file_upload',
    bank_book_file: 'bank_book_file_upload',
  };
  const mappedField = focusFieldMap[fieldName] ?? fieldName;

  return form?.querySelector<HTMLElement>(`#${mappedField}, [name="${mappedField}"]`) ?? null;
};

const focusField = (fieldName: string | undefined): void => {
  const field = findField(fieldName);

  if (!field) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });

  if ('focus' in field) {
    window.setTimeout((): void => field.focus(), 180);
  }
};

const showMessage = async (message: string): Promise<void> => {
  await window.uiAlert?.(message);
};

const activateMemberDetailTab = (targetId: string): void => {
  const tabGroup = document.querySelector<HTMLElement>('[data-admin-member-detail-tabs]');

  if (!tabGroup) {
    return;
  }

  const tabButtons = tabGroup.querySelectorAll<HTMLButtonElement>('[data-admin-member-detail-tab]');
  const tabPanels = tabGroup.querySelectorAll<HTMLElement>('[data-admin-member-detail-tab-panel]');

  tabButtons.forEach((button: HTMLButtonElement): void => {
    const isActive = button.dataset.adminMemberDetailTab === targetId;
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  tabPanels.forEach((panel: HTMLElement): void => {
    const isActive = panel.id === targetId;
    panel.classList.toggle('is-active', isActive);
    panel.hidden = !isActive;
  });
};

const restoreMemberDetailTab = (): void => {
  const targetTab = window.sessionStorage.getItem(memberDetailActiveTabStorageKey);

  if (!targetTab) {
    return;
  }

  window.sessionStorage.removeItem(memberDetailActiveTabStorageKey);
  activateMemberDetailTab(targetTab);
};

const getCsrfHeaders = (): Record<string, string> => {
  if (!form) {
    return {};
  }

  const nameInput = form.querySelector<HTMLInputElement>('input[name="csrf_name"]');
  const valueInput = form.querySelector<HTMLInputElement>('input[name="csrf_value"]');

  if (!nameInput || !valueInput) {
    return {};
  }

  return {
    [nameInput.name]: nameInput.value,
    [valueInput.name]: valueInput.value,
  };
};

const getValue = (fieldName: string): string => {
  const field = form?.elements.namedItem(fieldName);

  if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
    return field.value.trim();
  }

  return '';
};

const getCheckedValue = (fieldName: string): string => form
  ?.querySelector<HTMLInputElement>(`input[name="${fieldName}"]:checked`)
  ?.value ?? '';

const getPhoneParts = (prefix: string): string[] => [
  getValue(`${prefix}_1`),
  getValue(`${prefix}_2`),
  getValue(`${prefix}_3`),
];

const hasInvalidNumberPart = (parts: string[]): boolean => parts.some((part: string): boolean => !numericPattern.test(part));

const validateRequiredPhone = (prefix: string, label: string): ValidationResult | null => {
  const parts = getPhoneParts(prefix);

  if (parts.includes('')) {
    return { message: `${label}을/를 전체 입력해 주세요.`, field: `${prefix}_1` };
  }

  if (hasInvalidNumberPart(parts)) {
    return { message: `${label}은/는 숫자만 입력해 주세요.`, field: `${prefix}_1` };
  }

  return null;
};

const validateOptionalPhone = (prefix: string, label: string): ValidationResult | null => {
  const parts = getPhoneParts(prefix);

  if (parts.every((part: string): boolean => part === '')) {
    return null;
  }

  if (parts.includes('')) {
    return { message: `${label}을/를 입력하려면 전체 항목을 입력해 주세요.`, field: `${prefix}_1` };
  }

  if (hasInvalidNumberPart(parts)) {
    return { message: `${label}은/는 숫자만 입력해 주세요.`, field: `${prefix}_1` };
  }

  return null;
};

const validateBusinessNumber = (): ValidationResult | null => {
  const parts = [
    getValue('business_number_1'),
    getValue('business_number_2'),
    getValue('business_number_3'),
  ];

  if (parts.includes('')) {
    return { message: '사업자등록번호를 전체 입력해 주세요.', field: 'business_number_1' };
  }

  if (!/^\d{3}$/.test(parts[0]) || !/^\d{2}$/.test(parts[1]) || !/^\d{5}$/.test(parts[2])) {
    return { message: '사업자등록번호는 3자리-2자리-5자리 형식으로 입력해 주세요.', field: 'business_number_1' };
  }

  return null;
};

const validateMemberDetailForm = (): ValidationResult | null => {
  if (isRegisterForm) {
    const userId = getValue('user_id');
    if (userId === '') {
      return { message: '아이디는 영문, 숫자, 밑줄만 사용해 4자 이상 50자 이하로 입력해 주세요.', field: 'user_id' };
    }

    if (!/^[A-Za-z0-9_]{4,50}$/.test(userId)) {
      return { message: '아이디는 영문, 숫자, 밑줄만 사용해 4자 이상 50자 이하로 입력해 주세요.', field: 'user_id' };
    }
  }

  const passwordNew = getValue('password_new');
  const passwordConfirm = getValue('password_confirm');

  if (isRegisterForm || passwordNew !== '' || passwordConfirm !== '') {
    if (passwordNew === '') {
      return { message: '비밀번호를 입력해 주세요.', field: 'password_new' };
    }

    if (passwordNew.length < 8) {
      return { message: '비밀번호는 8자 이상 입력해 주세요.', field: 'password_new' };
    }

    if (passwordConfirm === '') {
      return { message: '비밀번호 확인을 입력해 주세요.', field: 'password_confirm' };
    }

    if (passwordNew !== passwordConfirm) {
      return { message: '비밀번호와 비밀번호 확인이 일치하지 않습니다.', field: 'password_confirm' };
    }
  }

  if (getValue('name') === '') {
    return { message: '이름을 입력해 주세요.', field: 'name' };
  }

  const mobileResult = validateRequiredPhone('mobile', '휴대폰');
  if (mobileResult) {
    return mobileResult;
  }

  const email = getValue('email');
  if (email === '') {
    return { message: '이메일을 입력해 주세요.', field: 'email' };
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return { message: '이메일 형식이 올바르지 않습니다.', field: 'email' };
  }

  const role = getCheckedValue('role');
  if (!roleAllowlist.includes(role)) {
    return { message: '회원 유형을 올바르게 선택해 주세요.', field: 'role' };
  }

  const approvalStatus = getCheckedValue('approval_status');
  if (role !== 'ADMIN' && !approvalStatusAllowlist.includes(approvalStatus)) {
    return { message: '승인 상태를 올바르게 선택해 주세요.', field: 'approval_status' };
  }

  const status = getCheckedValue('status');
  if (!statusAllowlist.includes(status)) {
    return { message: '계정 상태를 올바르게 선택해 주세요.', field: 'status' };
  }

  if (role === 'ADMIN') {
    return null;
  }

  if (getValue('company_name') === '') {
    return { message: '상호명을 입력해 주세요.', field: 'company_name' };
  }

  const businessNumberResult = validateBusinessNumber();
  if (businessNumberResult) {
    return businessNumberResult;
  }

  if (getValue('business_license_file') === '') {
    return { message: '사업자등록증 사본을 업로드해 주세요.', field: 'business_license_file' };
  }

  if (getValue('business_type') === '') {
    return { message: '업태를 입력해 주세요.', field: 'business_type' };
  }

  if (getValue('business_item') === '') {
    return { message: '종목을 입력해 주세요.', field: 'business_item' };
  }

  const companyPhoneResult = validateRequiredPhone('company_phone', '업체 전화');
  if (companyPhoneResult) {
    return companyPhoneResult;
  }

  const faxResult = validateOptionalPhone('fax', '팩스');
  if (faxResult) {
    return faxResult;
  }

  const zipCode = getValue('zip_code');
  if (!/^\d{5}$/.test(zipCode)) {
    return { message: '사업장 우편번호는 5자리 숫자로 선택해 주세요.', field: 'zip_code' };
  }

  if (getValue('address') === '') {
    return { message: '사업장 기본주소를 선택해 주세요.', field: 'address' };
  }

  if (role === 'SELLER') {
    return null;
  }

  if (getValue('bank_name') === '') {
    return { message: '은행명을 입력해 주세요.', field: 'bank_name' };
  }

  const accountNumber = getValue('account_number');
  if (accountNumber === '') {
    return { message: '계좌번호를 입력해 주세요.', field: 'account_number' };
  }

  if (!/^[0-9-]+$/.test(accountNumber)) {
    return { message: '계좌번호는 숫자와 하이픈만 입력해 주세요.', field: 'account_number' };
  }

  if (getValue('account_holder') === '') {
    return { message: '예금주를 입력해 주세요.', field: 'account_holder' };
  }

  if (getValue('bank_book_file') === '') {
    return { message: '통장 사본을 업로드해 주세요.', field: 'bank_book_file' };
  }

  return null;
};

const setSubmitting = (isSubmitting: boolean): void => {
  submitButtons.forEach((button: HTMLButtonElement): void => {
    button.disabled = isSubmitting;
  });
};
const memberUploadBindings: MemberUploadBinding[] = [
  {
    inputId: 'business_license_file_upload',
    hiddenName: 'business_license_file',
    category: 'business-license',
  },
  {
    inputId: 'bank_book_file_upload',
    hiddenName: 'bank_book_file',
    category: 'bank-book',
  },
];

const setUploadFieldBusy = (input: HTMLInputElement, isBusy: boolean): void => {
  const uploadField = input.closest<HTMLElement>('.upload-field');
  const selectButton = uploadField?.querySelector<HTMLElement>('.upload-select-btn') ?? null;

  input.disabled = isBusy;
  selectButton?.classList.toggle('is-disabled', isBusy);
};

const updateUploadPreview = (input: HTMLInputElement, result: DomemallImageUploadResult, displayName?: string): void => {
  const uploadField = input.closest<HTMLElement>('.upload-field');
  const fileName = uploadField?.querySelector<HTMLElement>('.upload-file-name') ?? null;
  const preview = uploadField?.querySelector<HTMLElement>('.admin-member-detail-upload-preview') ?? null;
  const safeDisplayName = displayName || result.originalName || result.filePath || '이미지 업로드 완료';

  if (fileName) {
    fileName.textContent = safeDisplayName;
  }

  if (preview) {
    preview.innerHTML = '';
    const image = document.createElement('img');
    image.src = result.fileUrl;
    image.alt = safeDisplayName;
    image.className = 'admin-member-detail-upload-preview-image';
    image.dataset.imagePreview = result.fileUrl;
    image.dataset.imagePreviewTitle = safeDisplayName || '이미지 미리보기';
    preview.append(image);
  }
};

const initMemberImageUploads = (): void => {
  if (!form) {
    return;
  }

  memberUploadBindings.forEach((binding: MemberUploadBinding): void => {
    const input = form.querySelector<HTMLInputElement>(`#${binding.inputId}`);
    const hiddenInput = form.querySelector<HTMLInputElement>(`input[name="${binding.hiddenName}"]`);

    if (!input || !hiddenInput) {
      return;
    }

    input.addEventListener('change', async (): Promise<void> => {
      const file = input.files?.[0] ?? null;

      if (!file) {
        return;
      }

      if (!window.DomemallImageUpload) {
        await showMessage('이미지 업로드 모듈을 불러오지 못했습니다. 새로고침 후 다시 시도해 주세요.');
        input.value = '';
        return;
      }

      const uploadField = input.closest<HTMLElement>('.upload-field');
      const fileName = uploadField?.querySelector<HTMLElement>('.upload-file-name') ?? null;
      const previousText = fileName?.textContent ?? '';

      if (fileName) {
        fileName.textContent = '업로드 중...';
      }
      setUploadFieldBusy(input, true);

      try {
        const result = await window.DomemallImageUpload.upload({
          file,
          category: binding.category,
          csrfRoot: form,
        });
        hiddenInput.value = result.filePath;
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        updateUploadPreview(input, result, file.name);
      } catch (error: unknown) {
        input.value = '';
        if (fileName) {
          fileName.textContent = previousText || '등록된 파일 없음';
        }
        await showMessage(error instanceof Error ? error.message : '이미지 업로드에 실패했습니다.');
      } finally {
        setUploadFieldBusy(input, false);
      }
    });
  });
};
type MemberDetailFormControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement;

const roleInputs = form ? Array.from(form.querySelectorAll<HTMLInputElement>('input[name="role"]')) : [];
const roleCards = Array.from(document.querySelectorAll<HTMLElement>('[data-member-role-card]'));

const getSelectedRole = (): string => roleInputs.find((input: HTMLInputElement): boolean => input.checked)?.value ?? '';

const getVisibleRoles = (card: HTMLElement): string[] => (card.dataset.visibleRoles ?? '')
  .split(',')
  .map((role: string): string => role.trim())
  .filter((role: string): boolean => role !== '');

const setCardControlsDisabled = (card: HTMLElement, disabled: boolean): void => {
  const controls = card.querySelectorAll<MemberDetailFormControl>('input, select, textarea, button');

  controls.forEach((control: MemberDetailFormControl): void => {
    control.disabled = disabled;
  });
};

const syncRoleCards = (): void => {
  const selectedRole = getSelectedRole();

  roleCards.forEach((card: HTMLElement): void => {
    const isVisible = getVisibleRoles(card).includes(selectedRole);

    card.hidden = !isVisible;
    setCardControlsDisabled(card, !isVisible);
  });
};

roleInputs.forEach((input: HTMLInputElement): void => {
  input.addEventListener('change', syncRoleCards);
});

syncRoleCards();
initMemberImageUploads();
restoreMemberDetailTab();

form?.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
  event.preventDefault();

  if (!window.axios) {
    await showMessage('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
    return;
  }

  const validationResult = validateMemberDetailForm();
  if (validationResult) {
    await showMessage(validationResult.message);
    focusField(validationResult.field);
    return;
  }

  setSubmitting(true);

  try {
    const response = await window.axios.post<MemberDetailResponse>(form.action, new FormData(form), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...getCsrfHeaders(),
      },
    });

    const payload = response.data;
    await showMessage(payload.message ?? (isRegisterForm ? '회원이 등록되었습니다.' : '저장되었습니다.'));
    if (isRegisterForm && payload.redirect) {
      window.location.href = payload.redirect;
      return;
    }

    window.location.reload();
  } catch (error: unknown) {
    if (window.axios.isAxiosError<MemberDetailResponse>(error)) {
      const payload = error.response?.data;
      await showMessage(payload?.message ?? '회원 정보를 저장하지 못했습니다.');
      focusField(payload?.field);
      return;
    }

    await showMessage('회원 정보를 저장하지 못했습니다.');
  } finally {
    setSubmitting(false);
  }
});
const memoForms = Array.from(document.querySelectorAll<HTMLFormElement>('[data-member-memo-form]'));
const memoDeleteForms = Array.from(document.querySelectorAll<HTMLFormElement>('[data-member-memo-delete-form]'));

const setFormSubmitting = (targetForm: HTMLFormElement, isSubmitting: boolean): void => {
  targetForm.querySelectorAll<HTMLButtonElement>('button[type="submit"]').forEach((button: HTMLButtonElement): void => {
    button.disabled = isSubmitting;
  });
};

const focusMemoField = (targetForm: HTMLFormElement, fieldName: string | undefined): void => {
  if (!fieldName) {
    return;
  }

  const field = targetForm.querySelector<HTMLElement>(`#${fieldName}, [name="${fieldName}"]`);

  if (!field) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });

  if ('focus' in field) {
    window.setTimeout((): void => field.focus(), 180);
  }
};

const reloadToMemoTab = (): void => {
  window.sessionStorage.setItem(memberDetailActiveTabStorageKey, 'member-detail-tab-memo');
  window.location.reload();
};

const initMemberMemoForms = (): void => {
  memoForms.forEach((targetForm: HTMLFormElement): void => {
    targetForm.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
      event.preventDefault();

      if (!window.axios) {
        await showMessage('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
        return;
      }

      const memoContent = targetForm.querySelector<HTMLTextAreaElement>('[name="memo_content"]')?.value.trim() ?? '';
      if (memoContent === '') {
        await showMessage('메모 내용을 입력해 주세요.');
        focusMemoField(targetForm, 'memo_content');
        return;
      }

      setFormSubmitting(targetForm, true);

      try {
        const response = await window.axios.post<MemberDetailResponse>(targetForm.action, new FormData(targetForm), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const payload = response.data;
        await showMessage(payload.message ?? '메모가 등록되었습니다.');
        reloadToMemoTab();
      } catch (error: unknown) {
        if (window.axios.isAxiosError<MemberDetailResponse>(error)) {
          const payload = error.response?.data;
          await showMessage(payload?.message ?? '메모를 등록하지 못했습니다.');
          focusMemoField(targetForm, payload?.field);
          return;
        }

        await showMessage('메모를 등록하지 못했습니다.');
      } finally {
        setFormSubmitting(targetForm, false);
      }
    });
  });

  memoDeleteForms.forEach((targetForm: HTMLFormElement): void => {
    targetForm.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
      event.preventDefault();

      if (!window.axios) {
        await showMessage('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
        return;
      }

      const confirmed = await window.uiConfirm?.('메모를 삭제하시겠습니까?', 'Confirm');
      if (confirmed !== true) {
        return;
      }

      setFormSubmitting(targetForm, true);

      try {
        const response = await window.axios.post<MemberDetailResponse>(targetForm.action, new FormData(targetForm), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const payload = response.data;
        await showMessage(payload.message ?? '메모가 삭제되었습니다.');
        reloadToMemoTab();
      } catch (error: unknown) {
        if (window.axios.isAxiosError<MemberDetailResponse>(error)) {
          const payload = error.response?.data;
          await showMessage(payload?.message ?? '메모를 삭제하지 못했습니다.');
          return;
        }

        await showMessage('메모를 삭제하지 못했습니다.');
      } finally {
        setFormSubmitting(targetForm, false);
      }
    });
  });
};

initMemberMemoForms();
