import '@commons/upload';

interface GoodsRegisterResponse {
  success: boolean;
  message?: string;
  field?: string;
  redirect?: string;
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

interface DomemallImageUploadApi {
  upload: (options: { file: File; category: string; csrfRoot?: ParentNode }) => Promise<DomemallImageUploadResult>;
}

interface CategoryItem {
  id: number;
  parent_id: number | null;
  name: string;
  path: string;
  depth: number;
  is_leaf: string;
  sort_order?: number;
}

interface CategorySelection {
  item: CategoryItem;
  label: string;
}

interface VendorItem {
  id: number;
  vendor_code: string;
  company_name: string;
  user_id: string;
  name: string;
}

interface OriginItem {
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

interface UploadedGoodsImage {
  filePath: string;
  fileUrl: string;
  name: string;
}

declare global {
  interface Window {
    DomemallImageUpload?: DomemallImageUploadApi;
  }
}

type GoodsFormControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement;
type TogglePanelSelector = '[data-option-title-row]' | '[data-text-option-row]';

const form = document.querySelector<HTMLFormElement>('#goodsRegisterForm');
const mainScrollArea = document.querySelector<HTMLElement>('#mainScrollArea');
const submitButtons = form
  ? Array.from(document.querySelectorAll<HTMLButtonElement>('button[form="goodsRegisterForm"], #goodsRegisterForm button[type="submit"]'))
  : [];
const numericPattern = /^\d+$/;
const pricePolicyAllowlist = ['FREE', 'COMPLY'];
const shippingTypeAllowlist = ['FREE', 'PAID', 'QUANTITY', 'COD'];
const yesNoAllowlist = ['Y', 'N'];
const soldoutAllowlist = ['0', '1'];
const goodsTypeAllowlist = ['NORMAL', 'HEALTH', 'MEDICAL'];
const goodsStatusAllowlist = ['NEW', 'USED', 'REFURB'];
const taxTypeAllowlist = ['TAX', 'FREE'];
const maxGoodsImageCount = 10;

const showMessage = async (message: string): Promise<void> => {
  await window.uiAlert?.(message);
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

const getFlagValue = (fieldName: string): string => {
  const checkedControl = form?.querySelector<HTMLInputElement>(`input[name="${fieldName}"]:checked`);
  if (checkedControl) {
    return checkedControl.value;
  }

  const hiddenInput = form?.querySelector<HTMLInputElement>(`input[type="hidden"][name="${fieldName}"]`);

  return hiddenInput?.value ?? '';
};

const focusField = (fieldName: string): void => {
  const field = form?.querySelector<HTMLElement>(`#${fieldName}, [name="${fieldName}"]`);

  if (!field) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
  window.setTimeout((): void => field.focus(), 180);
};

const validateNumber = (fieldName: string, label: string, min = 0): ValidationResult | null => {
  const value = getValue(fieldName);

  if (value === '' || !numericPattern.test(value)) {
    return { message: `${label}은/는 0 이상의 숫자로 입력해 주세요.`, field: fieldName };
  }

  if (Number(value) < min) {
    return { message: `${label}은/는 ${min} 이상 입력해 주세요.`, field: fieldName };
  }

  return null;
};


const validateGoodsRegisterForm = (): ValidationResult | null => {


  if (getValue('category_id') === '') {
    return { message: '카테고리를 선택해 주세요.', field: 'categorySearchInput' };
  }

  if (getValue('name') === '') {
    return { message: '상품명을 입력해 주세요.', field: 'name' };
  }

  if (getValue('origin') === '') {
    return { message: '원산지를 선택해 주세요.', field: 'originDepth1' };
  }

  if (getValue('manufacturer') === '') {
    return { message: '제조사를 입력해 주세요.', field: 'manufacturer' };
  }

  if (getCheckedValue('goods_type') !== '' && !goodsTypeAllowlist.includes(getCheckedValue('goods_type'))) {
    return { message: '상품타입을 올바르게 선택해 주세요.', field: 'goods_type' };
  }

  if (!goodsStatusAllowlist.includes(getCheckedValue('goods_status'))) {
    return { message: '상품상태를 올바르게 선택해 주세요.', field: 'goods_status' };
  }

  if (!yesNoAllowlist.includes(getCheckedValue('adult_only'))) {
    return { message: '성인전용 여부를 올바르게 선택해 주세요.', field: 'adult_only' };
  }

  const keywordValue = getValue('search_keywords');
  const keywordCount = keywordValue.split(',').map((keyword: string): string => keyword.trim()).filter(Boolean).length;
  if (keywordCount < 5) {
    return { message: '상품키워드는 5개 이상 입력해 주세요.', field: 'keywordTagInput' };
  }

  if (!taxTypeAllowlist.includes(getCheckedValue('tax_type'))) {
    return { message: '과세여부를 올바르게 선택해 주세요.', field: 'tax_type' };
  }

  const supplyPriceResult = validateNumber('supply_price', '공급가');
  if (supplyPriceResult) {
    return supplyPriceResult;
  }

  const sellPriceResult = validateNumber('sell_price', '판매가');
  if (sellPriceResult) {
    return sellPriceResult;
  }

  if (Number(getValue('sell_price')) < Number(getValue('supply_price'))) {
    return { message: '판매가는 공급가보다 작을 수 없습니다.', field: 'sell_price' };
  }

  if (!yesNoAllowlist.includes(getFlagValue('sell_price_fixed'))) {
    return { message: '판매가 고정 여부를 올바르게 선택해 주세요.', field: 'sell_price_fixed' };
  }

  const pricePolicy = getCheckedValue('price_policy');
  if (!pricePolicyAllowlist.includes(pricePolicy)) {
    return { message: '가격 정책을 올바르게 선택해 주세요.', field: 'price_policy' };
  }

  if (pricePolicy === 'COMPLY') {
    const compliancePriceResult = validateNumber('compliance_price', '준수가격', 1);
    if (compliancePriceResult) {
      return compliancePriceResult;
    }
  }

  const stockResult = validateNumber('stock', '재고 수량');
  if (stockResult) {
    return stockResult;
  }

  if (!yesNoAllowlist.includes(getFlagValue('stock_link'))) {
    return { message: '재고 연동 여부를 올바르게 선택해 주세요.', field: 'stock_link' };
  }

  if (!soldoutAllowlist.includes(getCheckedValue('soldout'))) {
    return { message: '품절 여부를 올바르게 선택해 주세요.', field: 'soldout' };
  }

  for (const field of ['is_display', 'is_exportable', 'has_option', 'has_text_option']) {
    if (!yesNoAllowlist.includes(getFlagValue(field))) {
      return { message: '운영 상태 값을 올바르게 선택해 주세요.', field };
    }
  }

  if (getFlagValue('has_option') === 'Y' && getValue('option_title1') === '') {
    return { message: '옵션을 사용하려면 옵션 분류명을 입력해 주세요.', field: 'option_title1' };
  }

  if (getFlagValue('has_option') === 'Y' && getValue('option_title2') === '') {
    return { message: '옵션을 사용하려면 옵션 항목명을 입력해 주세요.', field: 'option_title2' };
  }

  if (getFlagValue('has_option') === 'Y' && !optionTbody?.querySelector('tr:not(#emptyOptionRow)')) {
    return { message: '옵션 조합을 적용해 SKU를 생성해 주세요.', field: 'option_title2' };
  }

  if (getFlagValue('has_text_option') === 'Y' && !document.querySelector('#textOptionTbody tr:not(#emptyTextOptionRow)')) {
    return { message: '텍스트 입력 옵션을 추가해 주세요.', field: 'addTextOptionBtn' };
  }

  const shippingType = getCheckedValue('shipping_type');
  if (!shippingTypeAllowlist.includes(shippingType)) {
    return { message: '배송비 정책을 올바르게 선택해 주세요.', field: 'shipping_type' };
  }

  if (shippingType !== 'FREE' && shippingType !== 'COD') {
    const shippingFeeResult = validateNumber('shipping_fee', '기본 배송비');
    if (shippingFeeResult) {
      return shippingFeeResult;
    }
  }

  if (shippingType === 'QUANTITY') {
    const shippingQtyLimitResult = validateNumber('shipping_qty_limit', '합포장 기준 수량', 1);
    if (shippingQtyLimitResult) {
      return shippingQtyLimitResult;
    }
  }

  const hasExtraShipping = getFlagValue('has_extra_shipping');
  if (!yesNoAllowlist.includes(hasExtraShipping)) {
    return { message: '추가 배송비 사용 여부를 올바르게 선택해 주세요.', field: 'has_extra_shipping' };
  }

  for (const field of ['extra_shipping_jeju', 'extra_shipping_island', 'return_shipping_fee', 'exchange_shipping_fee']) {
    const result = validateNumber(field, field === 'extra_shipping_jeju' ? '제주 추가 배송비' : field === 'extra_shipping_island' ? '도서산간 추가 배송비' : field === 'return_shipping_fee' ? '반품 배송비' : '교환 배송비');
    if (result) {
      return result;
    }
  }

  if (getValue('thumbnail_url') === '') {
    return { message: '대표 이미지를 업로드해 주세요.', field: 'imageUploadInput' };
  }

  return null;
};

const setSubmitting = (isSubmitting: boolean): void => {
  submitButtons.forEach((button: HTMLButtonElement): void => {
    button.disabled = isSubmitting;
  });
};

const setControlsDisabled = (root: HTMLElement, disabled: boolean): void => {
  root.querySelectorAll<GoodsFormControl>('input, select, textarea, button').forEach((control: GoodsFormControl): void => {
    control.disabled = disabled;
  });
};
const setTogglePanelState = (row: HTMLElement, isOpen: boolean): void => {
  if (!row.classList.contains('admin-goods-register-option-body')) {
    return;
  }

  row.classList.toggle('is-hidden', !isOpen);
  row.hidden = !isOpen;
  row.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

  const header = row.previousElementSibling;
  if (header instanceof HTMLElement && header.classList.contains('admin-goods-register-toggle-header')) {
    header.classList.toggle('is-collapsed', !isOpen);
  }

  setControlsDisabled(row, !isOpen);
};

const syncTogglePanelGroup = (selector: TogglePanelSelector, fieldName: string): void => {
  const isOpen = getFlagValue(fieldName) === 'Y';

  form?.querySelectorAll<HTMLInputElement>(`input[name="${fieldName}"]`).forEach((input: HTMLInputElement): void => {
    input.setAttribute('aria-expanded', input.checked && isOpen ? 'true' : 'false');
  });

  form?.querySelectorAll<HTMLElement>(selector).forEach((row: HTMLElement): void => {
    setTogglePanelState(row, isOpen);
  });
};

const syncOptionTogglePanels = (): void => {
  const previousScrollTop = mainScrollArea?.scrollTop ?? 0;

  syncTogglePanelGroup('[data-option-title-row]', 'has_option');
  syncTogglePanelGroup('[data-text-option-row]', 'has_text_option');

  if (mainScrollArea) {
    mainScrollArea.scrollTop = previousScrollTop;
    window.requestAnimationFrame((): void => {
      mainScrollArea.scrollTop = previousScrollTop;
    });
  }
};


const optionTitle1Input = document.querySelector<HTMLInputElement>('#optionTitle1');
const optionTitle2Input = document.querySelector<HTMLInputElement>('#optionTitle2');
const optionTitle3Input = document.querySelector<HTMLInputElement>('#optionTitle3');
const optionItem1Label = document.querySelector<HTMLElement>('#optionItem1Label');
const optionItem2Wrapper = document.querySelector<HTMLElement>('#optionItem2Wrapper');
const optionTbody = document.querySelector<HTMLTableSectionElement>('#optionTbody');
const addOptionButton = document.querySelector<HTMLButtonElement>('#addOptionBtn');
const appendOptionButton = document.querySelector<HTMLButtonElement>('#appendOptionBtn');
let optionIndex = 0;

const splitOptionItems = (value: string): string[] => Array.from(new Set(value
  .split(',')
  .map((item: string): string => item.trim())
  .filter(Boolean)));

const getOptionDepthNames = (): string[] => splitOptionItems(optionTitle1Input?.value ?? '');

const updateOptionDepthUi = (): void => {
  const isTwoDepth = getOptionDepthNames().length >= 2;
  document.querySelector<HTMLElement>('#optionTitleWrapper')?.classList.toggle('is-two-depth', isTwoDepth);
  optionItem2Wrapper?.classList.toggle('is-hidden', !isTwoDepth);
  if (optionTitle3Input && !isTwoDepth) {
    optionTitle3Input.value = '';
  }
  if (optionItem1Label) {
    optionItem1Label.textContent = isTwoDepth ? '옵션 항목명 1' : '옵션 항목명';
  }
};

const createOptionCell = (modifier = ''): HTMLTableCellElement => {
  const cell = document.createElement('td');
  cell.className = `admin-goods-register-option-cell${modifier ? ` ${modifier}` : ''}`;

  return cell;
};

const createOptionInput = (name: string, value: string, alignRight = false, disabled = false, compact = false): HTMLInputElement => {
  const input = document.createElement('input');
  input.type = 'text';
  input.name = name;
  input.value = value;
  input.className = (alignRight ? 'admin-goods-register-option-input is-number' : 'admin-goods-register-option-input') + (compact ? ' is-compact' : '');
  if (alignRight) {
    input.inputMode = 'numeric';
  }
  input.disabled = disabled;

  return input;
};

const createOptionUnitInput = (name: string, value: string, unit: string, compact = false, modifier = ''): HTMLElement => {
  const wrap = document.createElement('span');
  wrap.className = `admin-goods-register-option-unit-input${modifier ? ` ${modifier}` : ''}`;
  const input = createOptionInput(name, value, true, false, compact);
  const unitText = document.createElement('span');
  unitText.className = 'admin-goods-register-unit';
  unitText.textContent = unit;
  wrap.append(input, unitText);

  return wrap;
};

const createOptionSelect = (name: string, options: Array<{ value: string; label: string }>): HTMLSelectElement => {
  const select = document.createElement('select');
  select.name = name;
  select.className = 'admin-goods-register-option-select';
  options.forEach((option: { value: string; label: string }): void => {
    const item = document.createElement('option');
    item.value = option.value;
    item.textContent = option.label;
    select.append(item);
  });

  return select;
};

const renderEmptyOptionRow = (): void => {
  if (!optionTbody || optionTbody.querySelector('tr:not(#emptyOptionRow)')) {
    return;
  }

  optionTbody.replaceChildren();
  const row = document.createElement('tr');
  row.id = 'emptyOptionRow';
  const cell = document.createElement('td');
  cell.colSpan = 8;
  cell.className = 'admin-goods-register-empty-cell admin-goods-register-option-empty-cell';
  cell.textContent = '옵션 분류명 및 옵션 항목을 먼저 적용해 주세요.';
  row.append(cell);
  optionTbody.append(row);
};

const appendOptionRow = (optionValue1: string, optionValue2: string, isSingleDepth: boolean): void => {
  if (!optionTbody) {
    return;
  }

  optionTbody.querySelector('#emptyOptionRow')?.remove();

  const row = document.createElement('tr');
  row.className = 'admin-goods-register-option-row';
  const currentIndex = optionIndex;
  optionIndex += 1;

  const value1Cell = createOptionCell();
  value1Cell.append(createOptionInput(`options[${currentIndex}][option_val1]`, optionValue1));
  row.append(value1Cell);

  const value2Cell = createOptionCell();
  value2Cell.append(createOptionInput(`options[${currentIndex}][option_val2]`, optionValue2, false, isSingleDepth));
  row.append(value2Cell);

  [
    { fieldName: 'option_supply_price', unit: '원' },
    { fieldName: 'option_sell_price', unit: '원' },
    { fieldName: 'option_compliance_price', unit: '원' },
    { fieldName: 'stock', unit: '개' },
  ].forEach((field: { fieldName: string; unit: string }): void => {
    const cell = createOptionCell('is-option-number');
    cell.append(createOptionUnitInput(`options[${currentIndex}][${field.fieldName}]`, '0', field.unit, false, 'is-option-number'));
    row.append(cell);
  });

  const stateCell = createOptionCell();
  const stateWrap = document.createElement('div');
  stateWrap.className = 'admin-goods-register-option-state-controls';
  stateWrap.append(
    createOptionSelect(`options[${currentIndex}][soldout]`, [
      { value: '0', label: '판매중' },
      { value: '1', label: '품절' },
    ]),
    createOptionSelect(`options[${currentIndex}][is_display]`, [
      { value: 'Y', label: '노출' },
      { value: 'N', label: '숨김' },
    ])
  );
  stateCell.append(stateWrap);
  row.append(stateCell);

  const removeCell = createOptionCell();
  const removeButton = document.createElement('button');
  removeButton.type = 'button';
  removeButton.className = 'admin-goods-register-option-remove-button';
  removeButton.textContent = '삭제';
  removeButton.addEventListener('click', (): void => {
    row.remove();
    renderEmptyOptionRow();
  });
  removeCell.append(removeButton);
  row.append(removeCell);

  optionTbody.append(row);
};

const buildOptionCombinations = (groups: string[][]): string[][] => {
  if (groups.length === 0) {
    return [];
  }

  return groups.reduce(
    (combinations: string[][], group: string[]): string[][] => combinations.flatMap(
      (combination: string[]): string[][] => group.map((item: string): string[] => [...combination, item])
    ),
    [[]]
  );
};

const applyOptionCombinations = async (): Promise<void> => {
  if (!optionTbody || !optionTitle1Input || !optionTitle2Input) {
    return;
  }

  const depthNames = getOptionDepthNames();
  if (depthNames.length === 0) {
    await showMessage('옵션 분류명을 입력해 주세요. 예: 색상 또는 색상,사이즈');
    optionTitle1Input.focus();
    return;
  }

  if (depthNames.length > 2) {
    await showMessage('옵션 분류명은 최대 2개까지 입력해 주세요. 예: 색상,사이즈');
    optionTitle1Input.focus();
    return;
  }

  const firstItems = splitOptionItems(optionTitle2Input.value);
  if (firstItems.length === 0) {
    await showMessage('첫 번째 옵션 항목명을 입력해 주세요. 예: 빨강,파랑');
    optionTitle2Input.focus();
    return;
  }

  const groups = [firstItems];
  if (depthNames.length === 2) {
    const secondItems = splitOptionItems(optionTitle3Input?.value ?? '');
    if (secondItems.length === 0) {
      await showMessage('두 번째 옵션 항목명을 입력해 주세요. 예: S,M,L');
      optionTitle3Input?.focus();
      return;
    }
    groups.push(secondItems);
  }

  optionTbody.replaceChildren();
  optionIndex = 0;
  const combinations = buildOptionCombinations(groups);
  combinations.forEach((combination: string[]): void => {
    appendOptionRow(combination[0] ?? '', combination[1] ?? '', depthNames.length === 1);
  });
  renderEmptyOptionRow();
};

const initOptionBuilder = (): void => {
  optionTitle1Input?.addEventListener('blur', updateOptionDepthUi);
  optionTitle1Input?.addEventListener('input', updateOptionDepthUi);

  [optionTitle1Input, optionTitle2Input, optionTitle3Input].forEach((input: HTMLInputElement | null): void => {
    input?.addEventListener('keydown', (event: KeyboardEvent): void => {
      if (event.key === 'Enter') {
        event.preventDefault();
        void applyOptionCombinations();
      }
    });
  });

  addOptionButton?.addEventListener('click', (): void => {
    void applyOptionCombinations();
  });

  appendOptionButton?.addEventListener('click', async (): Promise<void> => {
    if (!optionTbody || optionTbody.querySelector('#emptyOptionRow')) {
      await showMessage('옵션 분류명 및 옵션 항목을 먼저 적용해 주세요.');
      return;
    }

    appendOptionRow('', '', getOptionDepthNames().length === 1);
  });

  renderEmptyOptionRow();
  updateOptionDepthUi();
};
const normalizeCategory = (raw: unknown): CategoryItem | null => {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const id = Number(row.id);
  const parentValue = row.parent_id ?? null;
  const parentId = parentValue === null || parentValue === '' ? null : Number(parentValue);
  const depth = Number(row.depth);

  if (!Number.isFinite(id) || !Number.isFinite(depth)) {
    return null;
  }

  return {
    id,
    parent_id: parentId !== null && Number.isFinite(parentId) ? parentId : null,
    name: String(row.name ?? ''),
    path: String(row.path ?? ''),
    depth,
    is_leaf: String(row.is_leaf ?? 'N'),
    sort_order: Number(row.sort_order ?? 0),
  };
};

const readCategories = (): CategoryItem[] => {
  const dataNode = document.querySelector<HTMLScriptElement>('#goodsCategoryData');
  if (!dataNode?.textContent) {
    return [];
  }

  try {
    const parsed = JSON.parse(dataNode.textContent) as unknown[];
    return Array.isArray(parsed)
      ? parsed.map(normalizeCategory).filter((item): item is CategoryItem => item !== null)
      : [];
  } catch {
    return [];
  }
};

const categories = readCategories();
const categoryMap = new Map<number, CategoryItem>(categories.map((item: CategoryItem): [number, CategoryItem] => [item.id, item]));
const selectedCategoryByDepth = new Map<number, number>();

const getCategoryChildren = (parentId: number | null, depth: number): CategoryItem[] => categories
  .filter((item: CategoryItem): boolean => item.depth === depth && item.parent_id === parentId)
  .sort((a: CategoryItem, b: CategoryItem): number => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id);

const getCategoryTrail = (item: CategoryItem): CategoryItem[] => {
  const ids = item.path.match(/\d+/g)?.map(Number) ?? [item.id];
  return ids.map((id: number): CategoryItem | null => categoryMap.get(id) ?? null).filter((row): row is CategoryItem => row !== null);
};

const getCategoryLabel = (item: CategoryItem): string => getCategoryTrail(item)
  .map((row: CategoryItem): string => row.name)
  .join(' > ');

const isSelectableCategory = (item: CategoryItem): boolean => item.is_leaf === 'Y' || getCategoryChildren(item.id, item.depth + 1).length === 0;

const setSelectedCategory = (item: CategoryItem | null): void => {
  const selectedId = document.querySelector<HTMLInputElement>('#selectedCategoryId');
  const selectedPath = document.querySelector<HTMLInputElement>('#category_path');
  const selectedText = document.querySelector<HTMLElement>('#selectedCategoryText');

  if (!selectedId || !selectedPath || !selectedText) {
    return;
  }

  if (!item) {
    selectedId.value = '';
    selectedPath.value = '';
    selectedText.textContent = '없음';
    return;
  }

  selectedId.value = String(item.id);
  selectedPath.value = item.path;
  selectedText.textContent = getCategoryLabel(item);
};

const renderCategoryColumn = (depth: number, parentId: number | null): void => {
  const column = document.querySelector<HTMLElement>(`#catList${depth}`);
  if (!column) {
    return;
  }

  column.innerHTML = '';
  const rows = getCategoryChildren(parentId, depth);

  if (rows.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'admin-goods-register-category-empty';
    empty.textContent = depth === 1 ? '등록된 카테고리 없음' : '하위 카테고리 없음';
    column.append(empty);
    return;
  }

  rows.forEach((item: CategoryItem): void => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'admin-goods-register-category-item';
    button.classList.toggle('is-active', selectedCategoryByDepth.get(depth) === item.id);
    button.classList.toggle('is-leaf', isSelectableCategory(item));
    button.textContent = item.name;
    button.addEventListener('click', (): void => selectCategoryBranch(item));
    column.append(button);
  });
};

const clearCategoryColumnsAfter = (depth: number): void => {
  for (let currentDepth = depth + 1; currentDepth <= 4; currentDepth += 1) {
    selectedCategoryByDepth.delete(currentDepth);
    const column = document.querySelector<HTMLElement>(`#catList${currentDepth}`);
    if (column) {
      column.innerHTML = '';
    }
  }
};

const renderCategoryColumns = (): void => {
  renderCategoryColumn(1, null);
  for (let depth = 2; depth <= 4; depth += 1) {
    const parentId = selectedCategoryByDepth.get(depth - 1) ?? null;
    if (parentId === null) {
      clearCategoryColumnsAfter(depth - 1);
      break;
    }
    renderCategoryColumn(depth, parentId);
  }
};

const selectCategoryBranch = (item: CategoryItem): void => {
  selectedCategoryByDepth.set(item.depth, item.id);
  for (let depth = item.depth + 1; depth <= 4; depth += 1) {
    selectedCategoryByDepth.delete(depth);
  }

  if (isSelectableCategory(item)) {
    setSelectedCategory(item);
  } else {
    setSelectedCategory(null);
  }

  renderCategoryColumns();
};

const selectCategoryByPath = (item: CategoryItem): void => {
  getCategoryTrail(item).forEach((row: CategoryItem): void => {
    selectedCategoryByDepth.set(row.depth, row.id);
  });
  setSelectedCategory(isSelectableCategory(item) ? item : null);
  renderCategoryColumns();
};

const getSearchTerms = (keyword: string): string[] => Array.from(new Set(keyword
  .trim()
  .split(/\s+/)
  .map((term: string): string => term.trim())
  .filter(Boolean)))
  .sort((a: string, b: string): number => b.length - a.length);

const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const appendHighlightedText = (root: HTMLElement, text: string, terms: string[]): void => {
  if (terms.length === 0) {
    root.textContent = text;
    return;
  }

  const pattern = new RegExp(`(${terms.map(escapeRegExp).join('|')})`, 'gi');
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = pattern.exec(text)) !== null) {
    if (match.index > lastIndex) {
      root.append(document.createTextNode(text.slice(lastIndex, match.index)));
    }

    const mark = document.createElement('span');
    mark.className = 'admin-goods-register-category-highlight';
    mark.textContent = match[0];
    root.append(mark);
    lastIndex = pattern.lastIndex;
  }

  if (lastIndex < text.length) {
    root.append(document.createTextNode(text.slice(lastIndex)));
  }
};
const findCategoryMatches = (keyword: string): CategorySelection[] => {
  const terms = getSearchTerms(keyword).map((term: string): string => term.toLocaleLowerCase());
  if (terms.length === 0) {
    return [];
  }

  return categories
    .map((item: CategoryItem): CategorySelection => ({ item, label: getCategoryLabel(item) }))
    .filter((selection: CategorySelection): boolean => {
      const haystack = selection.label.toLocaleLowerCase();
      return terms.every((term: string): boolean => haystack.includes(term));
    })
    .slice(0, 30);
};

const closeCategorySearchResults = (): void => {
  const resultLayer = document.querySelector<HTMLElement>('#categorySearchResultLayer');
  if (!resultLayer) {
    return;
  }

  resultLayer.hidden = true;
  resultLayer.innerHTML = '';
};

const initCategorySearchDismiss = (): void => {
  const searchWrap = document.querySelector<HTMLElement>('.admin-goods-register-category-search');
  const searchInput = document.querySelector<HTMLInputElement>('#categorySearchInput');

  document.addEventListener('click', (event: MouseEvent): void => {
    if (searchWrap?.contains(event.target as Node)) {
      return;
    }

    closeCategorySearchResults();
  });

  searchInput?.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      closeCategorySearchResults();
    }
  });
};
const syncCategorySearch = (): void => {
  const searchInput = document.querySelector<HTMLInputElement>('#categorySearchInput');
  const resultLayer = document.querySelector<HTMLElement>('#categorySearchResultLayer');

  if (!searchInput || !resultLayer) {
    return;
  }

  const rawKeyword = searchInput.value.trim();
  const highlightTerms = getSearchTerms(rawKeyword);
  const matches = findCategoryMatches(rawKeyword);
  resultLayer.innerHTML = '';

  if (matches.length === 0) {
    resultLayer.hidden = searchInput.value.trim() === '';
    if (!resultLayer.hidden) {
      const empty = document.createElement('div');
      empty.className = 'admin-goods-register-category-empty-message';

      const title = document.createElement('p');
      title.className = 'admin-goods-register-category-empty-title';
      const keyword = document.createElement('span');
      keyword.className = 'admin-goods-register-category-empty-keyword';
      keyword.textContent = `"${rawKeyword}"`;
      title.append(keyword, document.createTextNode('(으)로 검색된 카테고리가 없습니다.'));

      const guide = document.createElement('ul');
      guide.className = 'admin-goods-register-category-empty-guide';
      ['- 단어 철자 입력이 정확한지 확인해 보세요.', '- 한글/영어 단어를 바꿔서 입력해 보세요.', '- 하단 카테고리 리스트에서 카테고리를 찾아 보세요.'].forEach((message: string): void => {
        const item = document.createElement('li');
        item.textContent = message;
        guide.append(item);
      });

      empty.append(title, guide);
      resultLayer.append(empty);
    }
    return;
  }

  matches.forEach((selection: CategorySelection): void => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'admin-goods-register-category-result';
    appendHighlightedText(button, selection.label, highlightTerms);
    button.addEventListener('click', (): void => {
      selectCategoryByPath(selection.item);
      searchInput.value = '';
      resultLayer.hidden = true;
      resultLayer.innerHTML = '';
    });
    resultLayer.append(button);
  });

  resultLayer.hidden = false;
};

const getAdminDir = (): string => document.querySelector<HTMLMetaElement>('meta[name="admin-dir"]')?.content || window.location.pathname.split('/')[1] || 'dmmt';

const initMarginCalculator = (): void => {
  const button = document.querySelector<HTMLButtonElement>('#openMarginCalcBtn');
  if (!button) {
    return;
  }

  button.addEventListener('click', (): void => {
    const sellPrice = document.querySelector<HTMLInputElement>('#sell_price')?.value.replace(/[^0-9]/g, '') ?? '';
    const supplyPrice = document.querySelector<HTMLInputElement>('#supply_price')?.value.replace(/[^0-9]/g, '') ?? '';
    const shippingFeeRow = document.querySelector<HTMLElement>('[data-shipping-fee-row]');
    const shippingFeeInput = document.querySelector<HTMLInputElement>('#shipping_fee');
    const shippingFee = shippingFeeRow?.hidden ? '' : shippingFeeInput?.value.replace(/[^0-9]/g, '') ?? '';
    const params = new URLSearchParams({ sell_price: sellPrice, supply_price: supplyPrice, shipping_fee: shippingFee });
    const url = `/${getAdminDir()}/goods/margin-calc?${params.toString()}`;

    window.dispatchEvent(new CustomEvent('open-iframe-modal', {
      detail: { src: url, title: '마진 계산기', widthClass: 'lg:w-[900px]' },
    }));
  });
};
const initCategoryPicker = (): void => {
  renderCategoryColumns();
  setSelectedCategory(null);
};

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

const normalizeVendor = (raw: unknown): VendorItem | null => {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const id = Number(row.id);
  const vendorCode = String(row.vendor_code ?? '').trim();
  if (!Number.isFinite(id) || vendorCode === '') {
    return null;
  }

  return {
    id,
    vendor_code: vendorCode,
    company_name: String(row.company_name ?? ''),
    user_id: String(row.user_id ?? ''),
    name: String(row.name ?? ''),
  };
};

const vendors = parseJsonData('#goodsVendorData')
  .map(normalizeVendor)
  .filter((item): item is VendorItem => item !== null);

const initVendorSearch = (): void => {
  const input = document.querySelector<HTMLInputElement>('#vendor_code');
  const resultLayer = document.querySelector<HTMLElement>('#vendorSearchResultLayer');
  const wrap = document.querySelector<HTMLElement>('.admin-goods-register-vendor-search');

  if (!input || !resultLayer) {
    return;
  }

  const close = (): void => {
    resultLayer.hidden = true;
    resultLayer.replaceChildren();
  };

  const render = (): void => {
    const terms = getSearchTerms(input.value).map((term: string): string => term.toLocaleLowerCase());
    resultLayer.replaceChildren();

    if (terms.length === 0) {
      close();
      return;
    }

    const matches = vendors.filter((vendor: VendorItem): boolean => {
      const haystack = `${vendor.vendor_code} ${vendor.company_name} ${vendor.user_id} ${vendor.name}`.toLocaleLowerCase();
      return terms.every((term: string): boolean => haystack.includes(term));
    }).slice(0, 20);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'admin-goods-register-category-result-empty';
      empty.textContent = '검색된 공급사가 없습니다.';
      resultLayer.append(empty);
      resultLayer.hidden = false;
      return;
    }

    matches.forEach((vendor: VendorItem): void => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-goods-register-category-result';
      button.textContent = `${vendor.company_name || vendor.name || '공급사'} (${vendor.vendor_code})`;
      button.addEventListener('click', (): void => {
        input.value = vendor.vendor_code;
        close();
      });
      resultLayer.append(button);
    });
    resultLayer.hidden = false;
  };

  input.addEventListener('input', render);
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

const normalizeOrigin = (raw: unknown): OriginItem | null => {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const row = raw as Record<string, unknown>;
  const id = Number(row.id);
  const level = Number(row.level);
  if (!Number.isFinite(id) || !Number.isFinite(level)) {
    return null;
  }

  const toNullableNumber = (value: unknown): number | null => {
    if (value === null || value === undefined || value === '') {
      return null;
    }
    const numberValue = Number(value);
    return Number.isFinite(numberValue) ? numberValue : null;
  };

  return {
    id,
    nm: String(row.nm ?? ''),
    cd0: toNullableNumber(row.cd0),
    cd1: toNullableNumber(row.cd1),
    pathnm0: String(row.pathnm0 ?? ''),
    pathnm1: String(row.pathnm1 ?? ''),
    level,
    sort: Number(row.sort ?? 0),
    last: String(row.last ?? 'N'),
  };
};

const origins = parseJsonData('#goodsOriginData')
  .map(normalizeOrigin)
  .filter((item): item is OriginItem => item !== null)
  .sort((a: OriginItem, b: OriginItem): number => a.level - b.level || a.sort - b.sort || a.id - b.id);

const appendOriginOptions = (select: HTMLSelectElement, rows: OriginItem[], placeholder: string): void => {
  select.replaceChildren();
  const empty = document.createElement('option');
  empty.value = '';
  empty.textContent = placeholder;
  select.append(empty);
  rows.forEach((origin: OriginItem): void => {
    const option = document.createElement('option');
    option.value = String(origin.id);
    option.textContent = origin.nm;
    select.append(option);
  });
};

const initOriginSelects = (): void => {
  const depth1 = document.querySelector<HTMLSelectElement>('#originDepth1');
  const depth2 = document.querySelector<HTMLSelectElement>('#originDepth2');
  const depth3 = document.querySelector<HTMLSelectElement>('#originDepth3');
  const hidden = document.querySelector<HTMLInputElement>('#selectedOriginStr');
  const wrapper = document.querySelector<HTMLElement>('.admin-goods-register-origin-wrapper');
  if (!depth1 || !depth2 || !depth3 || !hidden || !wrapper) {
    return;
  }

  const normalizeOriginRootName = (name: string): string => {
    const trimmedName = name.trim();
    if (trimmedName.includes('국내')) {
      return '국내';
    }
    if (trimmedName.includes('해외')) {
      return '해외';
    }
    return trimmedName;
  };

  const getOriginRootType = (origin: OriginItem | null): 'domestic' | 'overseas' | 'other' | '' => {
    const label = normalizeOriginRootName(origin?.nm ?? '');
    if (label.includes('국내')) {
      return 'domestic';
    }
    if (label.includes('해외')) {
      return 'overseas';
    }
    if (label !== '') {
      return 'other';
    }
    return '';
  };

  const getSelectedOrigin = (select: HTMLSelectElement): OriginItem | null => origins.find((origin: OriginItem): boolean => String(origin.id) === select.value) ?? null;
  const updateVisibility = (): void => {
    const rootType = getOriginRootType(getSelectedOrigin(depth1));
    const showDepth2 = rootType === 'domestic' || rootType === 'overseas';
    const showDepth3 = showDepth2 && depth2.value !== '';
    depth2.hidden = !showDepth2;
    depth3.hidden = !showDepth3;
    wrapper.classList.toggle('is-initial', !showDepth2);
    wrapper.classList.toggle('is-two-step', showDepth2 && !showDepth3);
    wrapper.classList.toggle('is-three-step', showDepth3);
  };

  const syncHidden = (): void => {
    const selectedDepth1 = getSelectedOrigin(depth1);
    const selectedDepth2 = getSelectedOrigin(depth2);
    const selectedDepth3 = getSelectedOrigin(depth3);
    const rootType = getOriginRootType(selectedDepth1);
    const rootLabel = normalizeOriginRootName(selectedDepth1?.nm ?? '');

    if (rootType === '') {
      hidden.value = '';
      return;
    }

    if (rootType === 'domestic') {
      hidden.value = selectedDepth2 ? (selectedDepth3 ? `${rootLabel}|${selectedDepth2.nm}|${selectedDepth3.nm}` : '') : rootLabel;
      return;
    }

    if (rootType === 'overseas') {
      hidden.value = selectedDepth2 && selectedDepth3 ? `${rootLabel}|${selectedDepth2.nm}|${selectedDepth3.nm}` : '';
      return;
    }

    hidden.value = rootLabel;
  };

  const appendRootOptions = (): void => {
    depth1.replaceChildren();
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '원산지 선택';
    depth1.append(empty);
    const rootRows = origins.filter((origin: OriginItem): boolean => origin.level === 0);
    const fallbackRootMap = new Map<number, OriginItem>();
    if (rootRows.length === 0) {
      origins.forEach((origin: OriginItem): void => {
        if (origin.cd0 === null || origin.pathnm0.trim() === '') {
          return;
        }
        if (fallbackRootMap.has(origin.cd0)) {
          return;
        }
        fallbackRootMap.set(origin.cd0, {
          id: origin.cd0,
          nm: origin.pathnm0,
          cd0: null,
          cd1: null,
          pathnm0: '',
          pathnm1: '',
          level: 0,
          sort: origin.sort,
          last: 'N',
        });
      });
    }
    const firstDepthRows = rootRows.length > 0 ? rootRows : Array.from(fallbackRootMap.values());
    firstDepthRows.forEach((origin: OriginItem): void => {
      const option = document.createElement('option');
      option.value = String(origin.id);
      option.textContent = normalizeOriginRootName(origin.nm);
      depth1.append(option);
    });
  };

  const resetSelect = (select: HTMLSelectElement, placeholder: string): void => {
    select.replaceChildren();
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.append(empty);
  };

  const renderDepth2 = (): void => {
    const selected = getSelectedOrigin(depth1);
    const rootType = getOriginRootType(selected);
    resetSelect(depth2, rootType === 'overseas' ? '대륙선택' : '지역선택');
    resetSelect(depth3, rootType === 'overseas' ? '나라선택' : '시군구선택');
    if (!selected || rootType === 'other') {
      return;
    }
    origins.filter((origin: OriginItem): boolean => origin.level === 1 && origin.cd0 === selected.id).forEach((origin: OriginItem): void => {
      const option = document.createElement('option');
      option.value = String(origin.id);
      option.textContent = origin.nm;
      depth2.append(option);
    });
  };

  const renderDepth3 = (): void => {
    const selectedDepth1 = getSelectedOrigin(depth1);
    const selectedDepth2 = getSelectedOrigin(depth2);
    const rootType = getOriginRootType(selectedDepth1);
    resetSelect(depth3, rootType === 'overseas' ? '나라선택' : '시군구선택');
    if (!selectedDepth1 || !selectedDepth2) {
      return;
    }
    origins.filter((origin: OriginItem): boolean => origin.level === 2 && origin.cd0 === selectedDepth1.id && origin.cd1 === selectedDepth2.id).forEach((origin: OriginItem): void => {
      const option = document.createElement('option');
      option.value = String(origin.id);
      option.textContent = origin.nm;
      depth3.append(option);
    });
  };

  appendRootOptions();
  resetSelect(depth2, '지역선택');
  resetSelect(depth3, '시군구선택');
  updateVisibility();
  syncHidden();

  depth1.addEventListener('change', (): void => {
    renderDepth2();
    updateVisibility();
    syncHidden();
  });

  depth2.addEventListener('change', (): void => {
    renderDepth3();
    updateVisibility();
    syncHidden();
  });

  depth3.addEventListener('change', (): void => {
    updateVisibility();
    syncHidden();
  });
};
const keywordTags: string[] = [];
const keywordPattern = /^[0-9A-Za-z가-힣]+$/;

const createSvgIcon = (pathData: string, className: string): SVGSVGElement => {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  svg.classList.add(className);
  const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('d', pathData);
  svg.append(path);

  return svg;
};

const syncKeywordTags = (): void => {
  const hidden = document.querySelector<HTMLInputElement>('#keywordsInput');
  const container = document.querySelector<HTMLElement>('#keywordTagsContainer');
  if (!hidden || !container) {
    return;
  }

  hidden.value = keywordTags.join(',');
  container.replaceChildren();
  keywordTags.forEach((keyword: string): void => {
    const tag = document.createElement('span');
    tag.className = 'admin-goods-register-keyword-tag';
    const text = document.createElement('span');
    text.textContent = keyword;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'admin-goods-register-keyword-remove-button';
    remove.setAttribute('aria-label', `${keyword} 삭제`);
    remove.append(createSvgIcon('M18 6 6 18M6 6l12 12', 'admin-goods-register-keyword-remove-icon'));
    remove.addEventListener('click', (): void => {
      keywordTags.splice(keywordTags.indexOf(keyword), 1);
      syncKeywordTags();
    });
    tag.append(text, remove);
    container.append(tag);
  });
};

const initKeywordTags = (): void => {
  const input = document.querySelector<HTMLInputElement>('#keywordTagInput');
  const addButton = document.querySelector<HTMLButtonElement>('#keywordAddBtn');
  if (!input || !addButton) {
    return;
  }

  const addKeywords = async (): Promise<void> => {
    const rawValues = input.value.split(',').map((value: string): string => value.trim()).filter(Boolean);
    if (rawValues.length === 0) {
      await showMessage('키워드를 입력해 주세요.');
      input.focus();
      return;
    }

    const invalidKeyword = rawValues.find((value: string): boolean => !keywordPattern.test(value));
    if (invalidKeyword) {
      await showMessage('키워드는 한글, 영문, 숫자만 입력해 주세요. 공백과 특수문자는 사용할 수 없습니다.');
      input.focus();
      return;
    }

    rawValues.forEach((value: string): void => {
      if (!keywordTags.includes(value)) {
        keywordTags.push(value);
      }
    });
    input.value = '';
    syncKeywordTags();
  };

  addButton.addEventListener('click', (): void => {
    void addKeywords();
  });
  input.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === ' ') {
      event.preventDefault();
      return;
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      void addKeywords();
    }
  });
};
const uploadedGoodsImages: UploadedGoodsImage[] = [];
let uploadingGoodsImageCount = 0;

const syncImageHiddenFields = (): void => {
  const thumbnailInput = document.querySelector<HTMLInputElement>('input[name="thumbnail_url"]');
  const imageInputs = Array.from(document.querySelectorAll<HTMLInputElement>('#imageHiddenFields input[name="goods_images[]"]'));
  if (thumbnailInput) {
    thumbnailInput.value = uploadedGoodsImages[0]?.filePath ?? '';
  }
  imageInputs.forEach((input: HTMLInputElement, index: number): void => {
    input.value = uploadedGoodsImages[index + 1]?.filePath ?? '';
  });
};

const moveUploadedGoodsImage = (fromIndex: number, toIndex: number): void => {
  if (toIndex < 0 || toIndex >= uploadedGoodsImages.length) {
    return;
  }
  const [item] = uploadedGoodsImages.splice(fromIndex, 1);
  if (!item) {
    return;
  }
  uploadedGoodsImages.splice(toIndex, 0, item);
  syncImageHiddenFields();
  renderImagePreviews();
};

const createImageActionButton = (label: string, iconPath: string, className: string, onClick: () => void): HTMLButtonElement => {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = className;
  button.setAttribute('aria-label', label);
  button.append(createSvgIcon(iconPath, 'admin-goods-register-image-action-icon'));
  button.addEventListener('click', onClick);

  return button;
};

const createUploadingImagePreviewItem = (): HTMLElement => {
  const item = document.createElement('div');
  item.className = 'admin-goods-register-image-preview-item is-uploading';
  const inner = document.createElement('div');
  inner.className = 'admin-goods-register-image-uploading-inner';
  const spinner = document.createElement('span');
  spinner.className = 'admin-goods-register-image-uploading-spinner';
  spinner.setAttribute('aria-hidden', 'true');
  const label = document.createElement('span');
  label.textContent = '업로드 중...';
  inner.append(spinner, label);
  item.append(inner);

  return item;
};

const renderImagePreviews = (): void => {
  const container = document.querySelector<HTMLElement>('#imagePreviewContainer');
  if (!container) {
    return;
  }

  container.replaceChildren();
  uploadedGoodsImages.forEach((image: UploadedGoodsImage, index: number): void => {
    const item = document.createElement('div');
    item.className = 'admin-goods-register-image-preview-item';
    const preview = document.createElement('img');
    preview.src = image.fileUrl;
    preview.alt = image.name;
    const badge = document.createElement('span');
    badge.className = 'admin-goods-register-image-preview-badge';
    badge.textContent = index === 0 ? '대표' : `추가 ${index}`;

    const actions = document.createElement('div');
    actions.className = 'admin-goods-register-image-preview-actions';
    const remove = createImageActionButton('이미지 삭제', 'M18 6 6 18M6 6l12 12', 'admin-goods-register-image-remove-button', (): void => {
      uploadedGoodsImages.splice(index, 1);
      syncImageHiddenFields();
      renderImagePreviews();
    });
    actions.append(remove);

    const previousButton = createImageActionButton('앞으로 이동', 'M15 18l-6-6 6-6', 'admin-goods-register-image-sort-button is-prev', (): void => moveUploadedGoodsImage(index, index - 1));
    const nextButton = createImageActionButton('뒤로 이동', 'M9 18l6-6-6-6', 'admin-goods-register-image-sort-button is-next', (): void => moveUploadedGoodsImage(index, index + 1));
    previousButton.disabled = index === 0;
    nextButton.disabled = index === uploadedGoodsImages.length - 1;

    item.append(preview, badge, actions, previousButton, nextButton);
    container.append(item);
  });

  for (let index = 0; index < uploadingGoodsImageCount; index += 1) {
    container.append(createUploadingImagePreviewItem());
  }
};
const uploadGoodsImages = async (files: FileList | File[]): Promise<void> => {
  if (!form || !window.DomemallImageUpload) {
    await showMessage('이미지 업로드 모듈을 불러오지 못했습니다. 새로고침 후 다시 시도해 주세요.');
    return;
  }

  const incomingFiles = Array.from(files);
  const availableImageSlots = Math.max(0, maxGoodsImageCount - uploadedGoodsImages.length);

  if (incomingFiles.length > availableImageSlots) {
    await showMessage('이미지는 최대 10장까지 등록할 수 있습니다.');
  }

  const selectedFiles = incomingFiles.slice(0, availableImageSlots);
  if (selectedFiles.length === 0) {
    return;
  }

  uploadingGoodsImageCount = selectedFiles.length;
  renderImagePreviews();
  try {
    for (const file of selectedFiles) {
      const result = await window.DomemallImageUpload.upload({ file, category: 'goods', csrfRoot: form });
      uploadedGoodsImages.push({ filePath: result.filePath, fileUrl: result.fileUrl, name: file.name || result.originalName });
      uploadingGoodsImageCount = Math.max(0, uploadingGoodsImageCount - 1);
      syncImageHiddenFields();
      renderImagePreviews();
    }
  } catch (error: unknown) {
    uploadingGoodsImageCount = 0;
    await showMessage(error instanceof Error ? error.message : '이미지 업로드에 실패했습니다.');
  }

  uploadingGoodsImageCount = 0;
  syncImageHiddenFields();
  renderImagePreviews();
};

const initGoodsImageDropzone = (): void => {
  const input = document.querySelector<HTMLInputElement>('#imageUploadInput');
  const dropzone = document.querySelector<HTMLElement>('.admin-goods-register-image-dropzone');
  if (!input || !dropzone) {
    return;
  }

  input.addEventListener('change', async (): Promise<void> => {
    if (input.files) {
      await uploadGoodsImages(input.files);
      input.value = '';
    }
  });

  ['dragenter', 'dragover'].forEach((eventName: string): void => {
    dropzone.addEventListener(eventName, (event: DragEvent): void => {
      event.preventDefault();
      dropzone.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach((eventName: string): void => {
    dropzone.addEventListener(eventName, (event: DragEvent): void => {
      event.preventDefault();
      dropzone.classList.remove('is-dragover');
    });
  });
  dropzone.addEventListener('drop', (event: DragEvent): void => {
    if (event.dataTransfer?.files) {
      void uploadGoodsImages(event.dataTransfer.files);
    }
  });
};

const renderEmptyTextOptionRow = (): void => {
  const tbody = document.querySelector<HTMLTableSectionElement>('#textOptionTbody');
  if (!tbody || tbody.querySelector('tr:not(#emptyTextOptionRow)')) {
    return;
  }
  const row = document.createElement('tr');
  row.id = 'emptyTextOptionRow';
  const cell = document.createElement('td');
  cell.colSpan = 5;
  cell.className = 'admin-goods-register-empty-cell admin-goods-register-option-empty-cell';
  cell.textContent = '입력옵션을 추가해 주세요.';
  row.append(cell);
  tbody.replaceChildren(row);
};

let textOptionIndex = 0;
const appendTextOptionRow = (): void => {
  const tbody = document.querySelector<HTMLTableSectionElement>('#textOptionTbody');
  if (!tbody) {
    return;
  }
  tbody.querySelector('#emptyTextOptionRow')?.remove();
  const row = document.createElement('tr');
  const index = textOptionIndex;
  textOptionIndex += 1;

  const titleCell = createOptionCell();
  titleCell.append(createOptionInput(`text_options[${index}][title]`, ''));
  row.append(titleCell);

  const requiredCell = createOptionCell('is-text-option-select');
  requiredCell.append(createOptionSelect(`text_options[${index}][is_required]`, [{ value: 'N', label: '선택' }, { value: 'Y', label: '필수' }]));
  row.append(requiredCell);

  const lengthCell = createOptionCell('is-text-option-length');
  lengthCell.append(createOptionInput(`text_options[${index}][max_length]`, '50', true, false, true));
  row.append(lengthCell);

  const displayCell = createOptionCell('is-text-option-select');
  displayCell.append(createOptionSelect(`text_options[${index}][is_display]`, [{ value: 'Y', label: '노출' }, { value: 'N', label: '숨김' }]));
  row.append(displayCell);

  const removeCell = createOptionCell('is-text-option-remove');
  const removeButton = document.createElement('button');
  removeButton.type = 'button';
  removeButton.className = 'admin-goods-register-option-remove-button';
  removeButton.textContent = '삭제';
  removeButton.addEventListener('click', (): void => {
    row.remove();
    renderEmptyTextOptionRow();
  });
  removeCell.append(removeButton);
  row.append(removeCell);
  tbody.append(row);
};

const initTextOptions = (): void => {
  document.querySelector<HTMLButtonElement>('#addTextOptionBtn')?.addEventListener('click', appendTextOptionRow);
  renderEmptyTextOptionRow();
};

const syncStockLink = (): void => {
  const wrapper = document.querySelector<HTMLElement>('#stockWrapper');
  if (!wrapper) {
    return;
  }
  wrapper.hidden = getCheckedValue('stock_link') !== 'Y';
};

const initStockLink = (): void => {
  form?.querySelectorAll<HTMLInputElement>('input[name="stock_link"]').forEach((input: HTMLInputElement): void => {
    input.addEventListener('change', (): void => {
      syncStockLink();
      syncAllRadioIndicators();
    });
  });
  syncStockLink();
};

const syncOptionTitleInputState = (input: HTMLInputElement): void => {
  input.classList.toggle('is-empty', input.value.trim() === '' && document.activeElement !== input);
};

const initOptionTitleInputState = (): void => {
  [optionTitle1Input, optionTitle2Input, optionTitle3Input].forEach((input: HTMLInputElement | null): void => {
    input?.classList.add('is-empty');
    input?.addEventListener('focus', (): void => syncOptionTitleInputState(input));
    input?.addEventListener('input', (): void => syncOptionTitleInputState(input));
    input?.addEventListener('blur', (): void => syncOptionTitleInputState(input));
  });
};
const syncConditionalFields = (): void => {
  const pricePolicy = getCheckedValue('price_policy');
  document.querySelectorAll<HTMLElement>('[data-price-policy-field]').forEach((row: HTMLElement): void => {
    const isVisible = row.dataset.pricePolicyField === pricePolicy;
    row.hidden = !isVisible;
    setControlsDisabled(row, !isVisible);
  });

  const shippingType = getCheckedValue('shipping_type');
  document.querySelectorAll<HTMLElement>('[data-shipping-fee-row]').forEach((row: HTMLElement): void => {
    const isVisible = shippingType !== 'FREE' && shippingType !== 'COD';
    row.hidden = !isVisible;
    setControlsDisabled(row, !isVisible);
  });
  document.querySelectorAll<HTMLElement>('[data-quantity-shipping-row]').forEach((row: HTMLElement): void => {
    const isVisible = shippingType === 'QUANTITY';
    row.hidden = !isVisible;
    setControlsDisabled(row, !isVisible);
  });

  const hasExtraShipping = getFlagValue('has_extra_shipping') === 'Y';
  document.querySelectorAll<HTMLElement>('[data-extra-shipping-row]').forEach((row: HTMLElement): void => {
    row.hidden = !hasExtraShipping;
    setControlsDisabled(row, !hasExtraShipping);
  });


};

const setUploadFieldBusy = (input: HTMLInputElement, isBusy: boolean): void => {
  const uploadField = input.closest<HTMLElement>('.upload-field');
  const selectButton = uploadField?.querySelector<HTMLElement>('.upload-select-btn') ?? null;

  input.disabled = isBusy;
  selectButton?.classList.toggle('is-disabled', isBusy);
};

const updateUploadPreview = (input: HTMLInputElement, result: DomemallImageUploadResult, displayName?: string): void => {
  const uploadField = input.closest<HTMLElement>('.upload-field');
  const fileName = uploadField?.querySelector<HTMLElement>('.upload-file-name') ?? null;
  const preview = uploadField?.querySelector<HTMLElement>('.admin-goods-register-upload-preview') ?? null;
  const safeDisplayName = displayName || result.originalName || result.filePath || '이미지 업로드 완료';

  if (fileName) {
    fileName.textContent = safeDisplayName;
  }

  if (preview) {
    preview.innerHTML = '';
    const image = document.createElement('img');
    image.src = result.fileUrl;
    image.alt = safeDisplayName;
    image.className = 'admin-goods-register-upload-preview-image';
    image.dataset.imagePreview = result.fileUrl;
    image.dataset.imagePreviewTitle = safeDisplayName;
    preview.append(image);
  }
};

const initGoodsImageUploads = (): void => {
  if (!form) {
    return;
  }

  form.querySelectorAll<HTMLInputElement>('[data-goods-image-upload]').forEach((input: HTMLInputElement): void => {
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

      const hiddenName = input.dataset.hiddenName ?? '';
      const hiddenInput = input.closest<HTMLElement>('.upload-field')?.querySelector<HTMLInputElement>(`input[type="hidden"][name="${hiddenName}"]`) ?? null;
      const fileName = input.closest<HTMLElement>('.upload-field')?.querySelector<HTMLElement>('.upload-file-name') ?? null;
      const previousText = fileName?.textContent ?? '';

      if (!hiddenInput) {
        await showMessage('이미지 저장 필드를 찾지 못했습니다.');
        input.value = '';
        return;
      }

      if (fileName) {
        fileName.textContent = '업로드 중...';
      }
      setUploadFieldBusy(input, true);

      try {
        const result = await window.DomemallImageUpload.upload({
          file,
          category: input.dataset.category ?? 'goods',
          csrfRoot: form,
        });
        hiddenInput.value = result.filePath;
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

const syncRadioIndicator = (group: HTMLElement): void => {
  const checkedInput = group.querySelector<HTMLInputElement>('input[type="radio"]:checked');
  const checkedLabel = checkedInput?.closest<HTMLElement>('.admin-goods-register-radio') ?? null;

  if (!checkedLabel) {
    group.style.setProperty('--radio-indicator-width', '0px');
    return;
  }

  group.style.setProperty('--radio-indicator-x', `${checkedLabel.offsetLeft}px`);
  group.style.setProperty('--radio-indicator-width', `${checkedLabel.offsetWidth}px`);
};

const syncAllRadioIndicators = (): void => {
  document.querySelectorAll<HTMLElement>('.admin-goods-register-radio-group').forEach((group: HTMLElement): void => {
    syncRadioIndicator(group);
  });
};

const initRadioIndicators = (): void => {
  document.querySelectorAll<HTMLElement>('.admin-goods-register-radio-group').forEach((group: HTMLElement): void => {
    group.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input: HTMLInputElement): void => {
      input.addEventListener('change', (): void => syncRadioIndicator(group));
    });
  });

  syncAllRadioIndicators();
  window.requestAnimationFrame(syncAllRadioIndicators);
  window.setTimeout(syncAllRadioIndicators, 120);
  window.addEventListener('resize', syncAllRadioIndicators);
};
form?.querySelectorAll<HTMLInputElement>('input[name="price_policy"], input[name="shipping_type"], input[name="has_extra_shipping"]').forEach((input: HTMLInputElement): void => {
  input.addEventListener('change', (): void => {
    syncConditionalFields();
    syncAllRadioIndicators();
  });
});

form?.querySelectorAll<HTMLInputElement>('input[name="has_option"], input[name="has_text_option"]').forEach((input: HTMLInputElement): void => {
  input.addEventListener('change', (): void => {
    syncOptionTogglePanels();
    input.blur();
  });
});
document.querySelector<HTMLInputElement>('#categorySearchInput')?.addEventListener('input', syncCategorySearch);

initCategoryPicker();
initCategorySearchDismiss();
initVendorSearch();
initOriginSelects();
initKeywordTags();
initGoodsImageDropzone();
initTextOptions();
initStockLink();
initOptionTitleInputState();
syncConditionalFields();
syncOptionTogglePanels();
initRadioIndicators();
initOptionBuilder();
initGoodsImageUploads();

form?.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
  event.preventDefault();

  if (!window.axios) {
    await showMessage('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
    return;
  }

  const validationResult = validateGoodsRegisterForm();
  if (validationResult) {
    await showMessage(validationResult.message);
    focusField(validationResult.field);
    return;
  }

  setSubmitting(true);

  try {
    const response = await window.axios.post<GoodsRegisterResponse>(form.action, new FormData(form), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const payload = response.data;
    await showMessage(payload.message ?? '상품이 등록되었습니다.');
    if (payload.redirect) {
      window.location.href = payload.redirect;
      return;
    }

    window.location.reload();
  } catch (error: unknown) {
    if (window.axios.isAxiosError<GoodsRegisterResponse>(error)) {
      const payload = error.response?.data;
      await showMessage(payload?.message ?? '상품을 등록하지 못했습니다.');
      if (payload?.field) {
        focusField(payload.field);
      }
      return;
    }

    await showMessage('상품을 등록하지 못했습니다.');
  } finally {
    setSubmitting(false);
  }
});



















