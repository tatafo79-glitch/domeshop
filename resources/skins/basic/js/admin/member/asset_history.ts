const searchForm = document.querySelector<HTMLFormElement>('#memberAssetHistorySearchForm');
const downloadForm = document.querySelector<HTMLFormElement>('#memberAssetHistoryExcelDownloadForm');
const downloadFilterNames: string[] = [
  'role',
  'change_type',
  'date_start',
  'date_end',
  'keyword_type',
  'keyword',
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