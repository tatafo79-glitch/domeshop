interface MemberAssetResponse {
  success: boolean;
  message?: string;
  field?: string;
  data?: {
    balance_after?: number;
    formatted_balance_after?: string;
  };
}

interface ValidationResult {
  message: string;
  field: string;
}

declare global {
  interface Window {
    axios: typeof import('axios').default;
    uiAlert?: (message: string, title?: string) => Promise<void>;
    uiConfirm?: (message: string, title?: string) => Promise<boolean>;
  }
}

const form = document.querySelector<HTMLFormElement>('[data-member-asset-form]');
const submitButton = form?.querySelector<HTMLButtonElement>('button[type="submit"]') ?? null;
const numericPattern = /^\d+$/;

const showMessage = async (message: string): Promise<void> => {
  if (window.uiAlert) {
    await window.uiAlert(message);
    return;
  }

  window.alert(message);
};

const getValue = (fieldName: string): string => {
  const field = form?.elements.namedItem(fieldName);

  if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
    return field.value.trim();
  }

  return '';
};

const focusField = (fieldName: string | undefined): void => {
  if (!fieldName) {
    return;
  }

  const field = form?.querySelector<HTMLElement>(`#${fieldName}, [name="${fieldName}"]`) ?? null;
  if (!field) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
  window.setTimeout((): void => field.focus(), 160);
};

const validateForm = (): ValidationResult | null => {
  const actionType = form?.querySelector<HTMLInputElement>('input[name="action_type"]:checked')?.value ?? '';
  if (!['plus', 'minus'].includes(actionType)) {
    return { message: '처리 구분을 선택해 주세요.', field: 'action_type' };
  }

  const amount = getValue('amount');
  if (amount === '' || !numericPattern.test(amount) || Number.parseInt(amount, 10) <= 0) {
    return { message: '금액은 1 이상의 숫자로 입력해 주세요.', field: 'asset_amount' };
  }


  const reason = getValue('reason');
  if (reason === '') {
    return { message: '변동 사유를 입력해 주세요.', field: 'asset_reason' };
  }

  if (reason.length > 255) {
    return { message: '변동 사유는 255자 이하로 입력해 주세요.', field: 'asset_reason' };
  }

  return null;
};

const setSubmitting = (isSubmitting: boolean): void => {
  if (submitButton) {
    submitButton.disabled = isSubmitting;
  }
};

form?.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
  event.preventDefault();

  if (!window.axios) {
    await showMessage('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
    return;
  }

  const validationResult = validateForm();
  if (validationResult) {
    await showMessage(validationResult.message);
    focusField(validationResult.field);
    return;
  }

  setSubmitting(true);

  try {
    const response = await window.axios.post<MemberAssetResponse>(form.action, new FormData(form), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    await showMessage(response.data.message ?? '처리가 완료되었습니다.');
    window.parent.postMessage({ type: 'member-assets-updated' }, window.location.origin);
    window.location.href = form.action;
  } catch (error: unknown) {
    if (window.axios.isAxiosError<MemberAssetResponse>(error)) {
      const payload = error.response?.data;
      await showMessage(payload?.message ?? '처리하지 못했습니다.');
      focusField(payload?.field);
      return;
    }

    await showMessage('처리하지 못했습니다.');
  } finally {
    setSubmitting(false);
  }
});

export {};
