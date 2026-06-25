interface LoginField {
  input: HTMLInputElement;
  error: HTMLElement;
  wrapper: HTMLElement;
}

interface LoginResponse {
  success: boolean;
  message?: string;
  field?: string;
  redirect?: string;
}

const form = document.querySelector<HTMLFormElement>('#adminLoginForm');
const submitButton = form?.querySelector<HTMLButtonElement>('.admin-member-login-submit') ?? null;

const createField = (id: string): LoginField | null => {
  const input = document.querySelector<HTMLInputElement>(`#${id}`);
  const error = document.querySelector<HTMLElement>(`#${id}_error`);
  const wrapper = document.querySelector<HTMLElement>(`[data-field="${id}"]`);

  if (!input || !error || !wrapper) {
    return null;
  }

  return { input, error, wrapper };
};

const fields = [createField('admin_id'), createField('password')].filter((field): field is LoginField => field !== null);

const setFieldState = (field: LoginField, isInvalid: boolean): void => {
  field.wrapper.classList.toggle('is-invalid', isInvalid);
  field.error.classList.toggle('is-visible', isInvalid);
  field.input.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
};

const setFieldMessage = (field: LoginField, message: string): void => {
  Array.from(field.error.childNodes).forEach((node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      node.remove();
    }
  });
  field.error.append(document.createTextNode(message));
};

const getField = (id: string | undefined): LoginField | undefined => fields.find((field) => field.input.id === id);

const showError = (fieldId: string | undefined, message: string): void => {
  const field = getField(fieldId) ?? fields[0];

  if (!field || !form) {
    return;
  }

  setFieldMessage(field, message);
  setFieldState(field, true);
  form.classList.remove('is-invalid');
  void form.offsetWidth;
  form.classList.add('is-invalid');
  field.input.focus();
};

fields.forEach((field) => {
  field.input.addEventListener('input', () => setFieldState(field, false));
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  let firstInvalid: HTMLInputElement | null = null;

  fields.forEach((field) => {
    const isInvalid = field.input.value.trim() === '';
    setFieldState(field, isInvalid);

    if (isInvalid && firstInvalid === null) {
      firstInvalid = field.input;
    }
  });

  if (firstInvalid !== null) {
    form.classList.remove('is-invalid');
    void form.offsetWidth;
    form.classList.add('is-invalid');
    firstInvalid.focus();

    return;
  }

  if (!window.axios) {
    form.submit();

    return;
  }

  submitButton?.setAttribute('disabled', 'disabled');

  try {
    const response = await window.axios.post<LoginResponse>(form.action, new FormData(form), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const payload = response.data;

    if (payload.success && payload.redirect) {
      window.location.href = payload.redirect;

      return;
    }

    showError(payload.field, payload.message ?? '로그인 처리 중 오류가 발생했습니다.');
  } catch (error: unknown) {
    if (window.axios.isAxiosError<LoginResponse>(error)) {
      showError(error.response?.data?.field, error.response?.data?.message ?? '로그인 처리 중 오류가 발생했습니다.');

      return;
    }

    showError('admin_id', '로그인 처리 중 오류가 발생했습니다.');
  } finally {
    submitButton?.removeAttribute('disabled');
  }
});