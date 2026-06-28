export {};

interface AxiosLike {
  post<T = unknown>(url: string, data?: FormData, config?: { headers?: Record<string, string> }): Promise<{ data: T }>;
  isAxiosError<T = unknown>(error: unknown): error is { response?: { data?: T } };
}

declare global {
  interface Window {
    axios?: AxiosLike;
    uiAlert?: (message: string, title?: string) => Promise<void>;
    uiConfirm?: (message: string, title?: string) => Promise<boolean>;
  }
}

interface GoodsOriginItem {
  id: number;
  nm: string;
  cd0: number | null;
  cd1: number | null;
  pathnm0: string;
  pathnm1: string;
  level: number;
  sort: number;
  last: string;
}

interface GoodsOriginResponse {
  success: boolean;
  message?: string;
  field?: string;
  data?: {
    needs_cascade_confirm?: boolean;
    deleted_count?: number;
  };
}

type SubmitMode = 'create' | 'update';

const parseJsonData = (selector: string): unknown[] => {
  const dataNode = document.querySelector<HTMLScriptElement>(selector);
  if (!dataNode?.textContent) {
    return [];
  }

  try {
    const parsed = JSON.parse(dataNode.textContent) as unknown;
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const normalizeNullableNumber = (value: unknown): number | null => {
  if (value === null || value === '') {
    return null;
  }

  const numberValue = Number(value);
  return Number.isFinite(numberValue) ? numberValue : null;
};

const normalizeOrigin = (raw: unknown): GoodsOriginItem | null => {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const id = Number(row.id);
  const level = Number(row.level);
  if (!Number.isFinite(id) || id < 1 || !Number.isFinite(level) || level < 0 || level > 2) {
    return null;
  }

  return {
    id,
    nm: String(row.nm ?? ''),
    cd0: normalizeNullableNumber(row.cd0),
    cd1: normalizeNullableNumber(row.cd1),
    pathnm0: String(row.pathnm0 ?? ''),
    pathnm1: String(row.pathnm1 ?? ''),
    level,
    sort: Number(row.sort ?? 0),
    last: String(row.last ?? 'N'),
  };
};

const origins = parseJsonData('#goodsOriginSettingData')
  .map(normalizeOrigin)
  .filter((origin): origin is GoodsOriginItem => origin !== null)
  .sort((a: GoodsOriginItem, b: GoodsOriginItem): number => a.level - b.level || a.sort - b.sort || a.id - b.id);

let selectedOrigin: GoodsOriginItem | null = null;
let submitMode: SubmitMode = 'create';

const getSearchTerms = (keyword: string): string[] => keyword
  .trim()
  .split(/\s+/)
  .map((term: string): string => term.trim().toLocaleLowerCase())
  .filter(Boolean);

const getOriginLabel = (origin: GoodsOriginItem): string => {
  if (origin.level === 0) {
    return origin.nm;
  }
  if (origin.level === 1) {
    return [origin.pathnm0, origin.nm].filter(Boolean).join(' > ');
  }

  return [origin.pathnm0, origin.pathnm1, origin.nm].filter(Boolean).join(' > ');
};

const appendHighlightedText = (target: HTMLElement, text: string, terms: string[]): void => {
  if (terms.length === 0) {
    target.textContent = text;
    return;
  }

  const lowerText = text.toLocaleLowerCase();
  let cursor = 0;
  while (cursor < text.length) {
    const matched = terms
      .map((term: string): { term: string; index: number } => ({ term, index: lowerText.indexOf(term, cursor) }))
      .filter((item: { term: string; index: number }): boolean => item.index >= 0)
      .sort((a: { index: number }, b: { index: number }): number => a.index - b.index)[0];

    if (!matched) {
      target.append(document.createTextNode(text.slice(cursor)));
      return;
    }

    if (matched.index > cursor) {
      target.append(document.createTextNode(text.slice(cursor, matched.index)));
    }

    const mark = document.createElement('mark');
    mark.className = 'admin-goods-register-category-highlight';
    mark.textContent = text.slice(matched.index, matched.index + matched.term.length);
    target.append(mark);
    cursor = matched.index + matched.term.length;
  }
};

const getInput = (form: HTMLFormElement, name: string): HTMLInputElement | null => form.querySelector<HTMLInputElement>(`[name="${name}"]`);

const setInputValue = (form: HTMLFormElement, name: string, value: string): void => {
  const input = getInput(form, name);
  if (input) {
    input.value = value;
  }
};

const focusField = (form: HTMLFormElement, fieldName: string | undefined): void => {
  if (!fieldName) {
    return;
  }

  const field = Array.from(form.elements).find((control: Element): boolean => {
    return control instanceof HTMLInputElement ? control.name === fieldName || control.id === fieldName : false;
  });

  if (field instanceof HTMLElement) {
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.setTimeout((): void => field.focus(), 180);
  }
};

const roots = (): GoodsOriginItem[] => origins.filter((origin: GoodsOriginItem): boolean => origin.level === 0);
const seconds = (rootId: number): GoodsOriginItem[] => origins.filter((origin: GoodsOriginItem): boolean => origin.level === 1 && origin.cd0 === rootId);
const thirds = (rootId: number, parentId: number): GoodsOriginItem[] => origins.filter((origin: GoodsOriginItem): boolean => origin.level === 2 && origin.cd0 === rootId && origin.cd1 === parentId);

const getSelectedRootId = (): number | null => {
  if (!selectedOrigin) {
    return null;
  }

  return selectedOrigin.level === 0 ? selectedOrigin.id : selectedOrigin.cd0;
};

const getSelectedSecondId = (): number | null => {
  if (!selectedOrigin) {
    return null;
  }

  return selectedOrigin.level === 1 ? selectedOrigin.id : selectedOrigin.cd1;
};
const hasOriginChildren = (origin: GoodsOriginItem): boolean => {
  if (origin.level === 0) {
    return origins.some((item: GoodsOriginItem): boolean => item.cd0 === origin.id);
  }
  if (origin.level === 1) {
    return origins.some((item: GoodsOriginItem): boolean => item.cd1 === origin.id);
  }

  return false;
};

const syncFormFromSelection = (form: HTMLFormElement): void => {
  const updateButton = form.querySelector<HTMLButtonElement>('[data-goods-origin-update]');
  const createButton = form.querySelector<HTMLButtonElement>('[data-goods-origin-create]');
  const guide = document.querySelector<HTMLElement>('[data-goods-origin-form-guide]');
  const selectedText = document.querySelector<HTMLElement>('#selectedOriginText');
  const deleteButton = document.querySelector<HTMLButtonElement>('[data-goods-origin-delete]');

  if (!selectedOrigin) {
    setInputValue(form, 'id', '');
    setInputValue(form, 'level', '0');
    setInputValue(form, 'parent_depth1', '');
    setInputValue(form, 'parent_depth2', '');
    setInputValue(form, 'origin_name', '');
    setInputValue(form, 'sort', '0');
    if (selectedText) {
      selectedText.textContent = '원산지 선택 안함';
    }
    if (guide) {
      guide.textContent = '※ 선택 없음 상태에서 등록하면 1단 원산지로 등록됩니다.';
    }
    if (updateButton) {
      updateButton.disabled = true;
      updateButton.hidden = true;
    }
    if (deleteButton) {
      deleteButton.hidden = true;
      deleteButton.disabled = true;
    }
    if (createButton) {
      createButton.hidden = false;
    }
    return;
  }

  setInputValue(form, 'id', String(selectedOrigin.id));
  setInputValue(form, 'origin_name', selectedOrigin.nm);
  setInputValue(form, 'sort', String(selectedOrigin.sort));
  if (selectedText) {
    selectedText.textContent = getOriginLabel(selectedOrigin);
  }
  if (updateButton) {
    updateButton.disabled = false;
    updateButton.hidden = false;
  }
  if (deleteButton) {
    deleteButton.hidden = false;
    deleteButton.disabled = false;
  }

  if (selectedOrigin.level === 0) {
    setInputValue(form, 'level', '1');
    setInputValue(form, 'parent_depth1', String(selectedOrigin.id));
    setInputValue(form, 'parent_depth2', '');
    if (guide) {
      guide.textContent = '※ 수정은 선택한 1단 원산지를 변경하고, 등록은 선택한 1단 원산지의 하위 2단으로 추가합니다.';
    }
    if (createButton) {
      createButton.hidden = false;
    }
    return;
  }

  if (selectedOrigin.level === 1) {
    setInputValue(form, 'level', '2');
    setInputValue(form, 'parent_depth1', String(selectedOrigin.cd0 ?? ''));
    setInputValue(form, 'parent_depth2', String(selectedOrigin.id));
    if (guide) {
      guide.textContent = '※ 수정은 선택한 2단 원산지를 변경하고, 등록은 선택한 2단 원산지의 하위 3단으로 추가합니다.';
    }
    if (createButton) {
      createButton.hidden = false;
    }
    return;
  }

  setInputValue(form, 'level', '2');
  setInputValue(form, 'parent_depth1', String(selectedOrigin.cd0 ?? ''));
  setInputValue(form, 'parent_depth2', String(selectedOrigin.cd1 ?? ''));
  if (guide) {
    guide.textContent = '※ 3단 원산지는 수정만 가능합니다.';
  }
  if (createButton) {
    createButton.hidden = true;
  }
};

const isSelected = (origin: GoodsOriginItem): boolean => selectedOrigin?.id === origin.id;

const createOriginButton = (origin: GoodsOriginItem, form: HTMLFormElement): HTMLButtonElement => {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'admin-goods-register-category-item';
  if (origin.level === 2) {
    button.classList.add('is-leaf');
  }
  if (isSelected(origin)) {
    button.classList.add('is-active');
  }
  button.textContent = origin.nm;
  button.addEventListener('click', (): void => {
    selectedOrigin = origin;
    submitMode = 'update';
    syncFormFromSelection(form);
    renderOriginColumns(form);
  });

  return button;
};

const createEmptyNode = (message: string): HTMLDivElement => {
  const empty = document.createElement('div');
  empty.className = 'admin-goods-register-category-empty';
  empty.textContent = message;

  return empty;
};

const renderOriginColumns = (form: HTMLFormElement): void => {
  const depth1 = document.querySelector<HTMLElement>('#originList1');
  const depth2 = document.querySelector<HTMLElement>('#originList2');
  const depth3 = document.querySelector<HTMLElement>('#originList3');
  if (!depth1 || !depth2 || !depth3) {
    return;
  }

  depth1.replaceChildren();
  depth2.replaceChildren();
  depth3.replaceChildren();

  const noneButton = document.createElement('button');
  noneButton.type = 'button';
  noneButton.className = 'admin-goods-register-category-item admin-setting-goods-origin-none';
  if (!selectedOrigin) {
    noneButton.classList.add('is-active');
  }
  noneButton.textContent = '원산지 선택 안함';
  noneButton.addEventListener('click', (): void => {
    selectedOrigin = null;
    submitMode = 'create';
    syncFormFromSelection(form);
    renderOriginColumns(form);
  });
  depth1.append(noneButton);

  const rootRows = roots();
  if (rootRows.length === 0) {
    depth1.append(createEmptyNode('등록된 원산지 없음'));
  }
  rootRows.forEach((origin: GoodsOriginItem): void => depth1.append(createOriginButton(origin, form)));

  const rootId = getSelectedRootId();
  if (rootId === null) {
    depth2.append(createEmptyNode('1단 원산지를 선택해 주세요.'));
    depth3.append(createEmptyNode('2단 원산지를 선택해 주세요.'));
    return;
  }

  const secondRows = seconds(rootId);
  if (secondRows.length === 0) {
    depth2.append(createEmptyNode('하위 원산지 없음'));
  }
  secondRows.forEach((origin: GoodsOriginItem): void => depth2.append(createOriginButton(origin, form)));

  const secondId = getSelectedSecondId();
  if (secondId === null) {
    depth3.append(createEmptyNode('2단 원산지를 선택해 주세요.'));
    return;
  }

  const thirdRows = thirds(rootId, secondId);
  if (thirdRows.length === 0) {
    depth3.append(createEmptyNode('하위 원산지 없음'));
  }
  thirdRows.forEach((origin: GoodsOriginItem): void => depth3.append(createOriginButton(origin, form)));
};

const selectOriginBySearch = (origin: GoodsOriginItem, form: HTMLFormElement): void => {
  selectedOrigin = origin;
  submitMode = 'update';
  syncFormFromSelection(form);
  renderOriginColumns(form);
  document.querySelector<HTMLElement>('.admin-setting-goods-origin-picker')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const initSearch = (form: HTMLFormElement): void => {
  const input = document.querySelector<HTMLInputElement>('#originSearchInput');
  const resultLayer = document.querySelector<HTMLElement>('#originSearchResultLayer');
  const wrap = document.querySelector<HTMLElement>('.admin-setting-goods-origin-search');
  if (!input || !resultLayer) {
    return;
  }

  const close = (): void => {
    resultLayer.hidden = true;
    resultLayer.replaceChildren();
  };

  const renderSearch = (): void => {
    const rawKeyword = input.value.trim();
    const terms = getSearchTerms(rawKeyword);
    resultLayer.replaceChildren();
    if (terms.length === 0) {
      close();
      return;
    }

    const matches = origins.filter((origin: GoodsOriginItem): boolean => {
      const haystack = getOriginLabel(origin).toLocaleLowerCase();
      return terms.every((term: string): boolean => haystack.includes(term));
    }).slice(0, 50);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'admin-goods-register-category-empty-message';
      empty.textContent = `"${rawKeyword}"(으)로 검색된 원산지가 없습니다.`;
      resultLayer.append(empty);
      resultLayer.hidden = false;
      return;
    }

    matches.forEach((origin: GoodsOriginItem): void => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-goods-register-category-result';
      appendHighlightedText(button, getOriginLabel(origin), terms);
      button.addEventListener('click', (): void => {
        selectOriginBySearch(origin, form);
        input.value = '';
        close();
      });
      resultLayer.append(button);
    });
    resultLayer.hidden = false;
  };

  input.addEventListener('input', renderSearch);
  input.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      close();
    }
  });
  document.addEventListener('click', (event: MouseEvent): void => {
    if (wrap?.contains(event.target as Node)) {
      return;
    }
    close();
  });
};

const validateForm = (form: HTMLFormElement): { message: string; field: string } | null => {
  if (submitMode === 'update' && !selectedOrigin) {
    return { message: '수정할 원산지를 선택해 주세요.', field: 'origin_name' };
  }
  if (submitMode === 'create' && selectedOrigin?.level === 2) {
    return { message: '3단 원산지 아래에는 원산지를 등록할 수 없습니다.', field: 'origin_name' };
  }

  const name = getInput(form, 'origin_name')?.value.trim() ?? '';
  if (name === '' || name.length > 50) {
    return { message: '원산지명은 50자 이하로 입력해 주세요.', field: 'origin_name' };
  }

  const sort = getInput(form, 'sort')?.value.trim() ?? '';
  if (!/^\d+$/.test(sort) || Number(sort) > 999999) {
    return { message: '정렬 순서는 0~999999 사이의 숫자로 입력해 주세요.', field: 'sort' };
  }

  return null;
};

const createSubmitData = (form: HTMLFormElement): FormData => {
  const formData = new FormData(form);

  if (submitMode === 'update' && selectedOrigin) {
    formData.set('level', String(selectedOrigin.level));
    formData.set('parent_depth1', selectedOrigin.cd0 === null ? '' : String(selectedOrigin.cd0));
    formData.set('parent_depth2', selectedOrigin.cd1 === null ? '' : String(selectedOrigin.cd1));
    return formData;
  }

  if (!selectedOrigin) {
    formData.set('level', '0');
    formData.set('parent_depth1', '');
    formData.set('parent_depth2', '');
    return formData;
  }

  if (selectedOrigin.level === 0) {
    formData.set('level', '1');
    formData.set('parent_depth1', String(selectedOrigin.id));
    formData.set('parent_depth2', '');
    return formData;
  }

  formData.set('level', '2');
  formData.set('parent_depth1', String(selectedOrigin.cd0 ?? ''));
  formData.set('parent_depth2', String(selectedOrigin.id));
  return formData;
};

const getSubmitUrl = (form: HTMLFormElement): string => {
  const createAction = form.dataset.createAction ?? form.action;
  if (submitMode === 'update' && selectedOrigin) {
    return `${createAction}/${selectedOrigin.id}`;
  }

  return createAction;
};

const createDeleteData = (form: HTMLFormElement, cascadeConfirm = false): FormData => {
  const formData = new FormData();
  form.querySelectorAll<HTMLInputElement>('input[type="hidden"][name]').forEach((input: HTMLInputElement): void => {
    if (!['id', 'level', 'parent_depth1', 'parent_depth2'].includes(input.name)) {
      formData.set(input.name, input.value);
    }
  });
  if (cascadeConfirm) {
    formData.set('cascade_confirm', '1');
  }

  return formData;
};

const getDeleteUrl = (form: HTMLFormElement, origin: GoodsOriginItem): string => {
  const createAction = form.dataset.createAction ?? form.action;

  return `${createAction}/${origin.id}/delete`;
};

const deleteSelectedOrigin = async (form: HTMLFormElement): Promise<void> => {
  if (!selectedOrigin) {
    await window.uiAlert?.('삭제할 원산지를 선택해 주세요.');
    return;
  }
  if (!window.axios) {
    await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
    return;
  }

  const message = hasOriginChildren(selectedOrigin)
    ? '해당 원산지를 삭제하시겠습니까?\n삭제시 하위 원산지도 함께 삭제 됩니다.'
    : '해당 원산지를 삭제하시겠습니까?';
  const confirmed = await window.uiConfirm?.(message, '확인');
  if (confirmed !== true) {
    return;
  }

  const deleteButton = document.querySelector<HTMLButtonElement>('[data-goods-origin-delete]');
  if (deleteButton) {
    deleteButton.disabled = true;
  }

  try {
    const response = await window.axios.post<GoodsOriginResponse>(getDeleteUrl(form, selectedOrigin), createDeleteData(form, hasOriginChildren(selectedOrigin)), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    await window.uiAlert?.(response.data.message ?? '원산지가 삭제되었습니다.');
    window.location.reload();
  } catch (error: unknown) {
    if (window.axios.isAxiosError<GoodsOriginResponse>(error)) {
      const payload = error.response?.data;
      if (payload?.data?.needs_cascade_confirm && selectedOrigin) {
        const confirmed = await window.uiConfirm?.(payload.message ?? '해당 원산지를 삭제하시겠습니까?\n삭제시 하위 원산지도 함께 삭제 됩니다.', '확인');
        if (confirmed === true) {
          const response = await window.axios.post<GoodsOriginResponse>(getDeleteUrl(form, selectedOrigin), createDeleteData(form, true), {
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          });
          await window.uiAlert?.(response.data.message ?? '원산지가 삭제되었습니다.');
          window.location.reload();
        }
        return;
      }
      await window.uiAlert?.(payload?.message ?? '원산지를 삭제하지 못했습니다.');
      return;
    }
    await window.uiAlert?.('원산지를 삭제하지 못했습니다.');
  } finally {
    if (deleteButton) {
      deleteButton.disabled = false;
    }
  }
};

const resetOriginForm = (form: HTMLFormElement): void => {
  selectedOrigin = null;
  submitMode = 'create';
  syncFormFromSelection(form);
  renderOriginColumns(form);
  document.querySelector<HTMLInputElement>('#originSearchInput')!.value = '';
};


const initOriginUploadForm = (): void => {
  const form = document.querySelector<HTMLFormElement>('#goodsOriginUploadForm');
  const fileInput = document.querySelector<HTMLInputElement>('#goodsOriginUploadFile');
  const fileName = document.querySelector<HTMLElement>('[data-goods-origin-upload-name]');
  const submitButton = document.querySelector<HTMLButtonElement>('[data-goods-origin-upload-submit]');
  if (!form || !fileInput || !fileName || !submitButton) {
    return;
  }

  fileInput.addEventListener('change', (): void => {
    fileName.textContent = fileInput.files?.[0]?.name ?? 'CSV 파일을 선택해 주세요.';
  });

  form.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();

    if (!window.axios) {
      await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    const file = fileInput.files?.[0] ?? null;
    if (!file) {
      await window.uiAlert?.('업로드할 CSV 파일을 선택해 주세요.');
      fileInput.focus();
      return;
    }
    if (!/\.csv$/i.test(file.name)) {
      await window.uiAlert?.('CSV 파일만 업로드할 수 있습니다.');
      fileInput.focus();
      return;
    }

    submitButton.disabled = true;
    try {
      const response = await window.axios.post<GoodsOriginResponse>(form.action, new FormData(form), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      await window.uiAlert?.(response.data.message ?? '원산지 업로드가 완료되었습니다.');
      window.location.reload();
    } catch (error: unknown) {
      if (window.axios.isAxiosError<GoodsOriginResponse>(error)) {
        await window.uiAlert?.(error.response?.data?.message ?? '원산지 업로드에 실패했습니다.');
        return;
      }
      await window.uiAlert?.('원산지 업로드에 실패했습니다.');
    } finally {
      submitButton.disabled = false;
    }
  });
};
const initGoodsOriginSetting = (): void => {
  const form = document.querySelector<HTMLFormElement>('#goodsOriginForm');
  if (!form) {
    return;
  }

  syncFormFromSelection(form);
  renderOriginColumns(form);
  initSearch(form);
  initOriginUploadForm();

  form.querySelectorAll<HTMLButtonElement>('[data-goods-origin-action]').forEach((button: HTMLButtonElement): void => {
    button.addEventListener('click', (): void => {
      submitMode = button.dataset.goodsOriginAction === 'update' ? 'update' : 'create';
    });
  });

  form.addEventListener('reset', (event: Event): void => {
    event.preventDefault();
    resetOriginForm(form);
  });

  document.querySelector<HTMLButtonElement>('[data-goods-origin-delete]')?.addEventListener('click', (): void => {
    void deleteSelectedOrigin(form);
  });

  form.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();
    const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
    submitMode = submitter?.dataset.goodsOriginAction === 'update' ? 'update' : 'create';

    if (!window.axios) {
      await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    const validation = validateForm(form);
    if (validation) {
      await window.uiAlert?.(validation.message);
      focusField(form, validation.field);
      return;
    }

    const submitButtons = Array.from(form.querySelectorAll<HTMLButtonElement>('button[type="submit"]'));
    submitButtons.forEach((button: HTMLButtonElement): void => {
      button.disabled = true;
    });

    try {
      const response = await window.axios.post<GoodsOriginResponse>(getSubmitUrl(form), createSubmitData(form), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      await window.uiAlert?.(response.data.message ?? '상품 원산지가 저장되었습니다.');
      window.location.reload();
    } catch (error: unknown) {
      if (window.axios.isAxiosError<GoodsOriginResponse>(error)) {
        const payload = error.response?.data;
        await window.uiAlert?.(payload?.message ?? '상품 원산지를 저장하지 못했습니다.');
        focusField(form, payload?.field);
        return;
      }
      await window.uiAlert?.('상품 원산지를 저장하지 못했습니다.');
    } finally {
      submitButtons.forEach((button: HTMLButtonElement): void => {
        button.disabled = false;
      });
      syncFormFromSelection(form);
    }
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initGoodsOriginSetting);
} else {
  initGoodsOriginSetting();
}
