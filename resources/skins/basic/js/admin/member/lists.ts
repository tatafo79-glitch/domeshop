const searchForm = document.querySelector<HTMLFormElement>('#memberSearchForm');
const downloadForm = document.querySelector<HTMLFormElement>('#memberExcelDownloadForm');
const downloadFilterNames = [
  'role',
  'approval_status',
  'status',
  'keyword_type',
  'keyword',
  'date_type',
  'date_start',
  'date_end',
  'amount_type',
  'amount_min',
  'amount_max',
];

const getFormValue = (form: HTMLFormElement, name: string): string => {
  const field = form.elements.namedItem(name);

  if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
    return field.value;
  }

  return '';
};

const setHiddenValue = (form: HTMLFormElement, name: string, value: string): void => {
  let input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);

  if (!input) {
    input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    form.append(input);
  }

  input.value = value;
};

const syncDownloadFilters = (): void => {
  if (!searchForm || !downloadForm) {
    return;
  }

  downloadFilterNames.forEach((name: string): void => {
    setHiddenValue(downloadForm, name, getFormValue(searchForm, name));
  });
};

downloadForm?.addEventListener('submit', syncDownloadFilters);
