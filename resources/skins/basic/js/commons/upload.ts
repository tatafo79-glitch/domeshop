interface DomemallAxiosResponse<T> {
  data: T;
}

interface DomemallAxiosLike {
  post<T>(url: string, data?: BodyInit | FormData, config?: { headers?: Record<string, string> }): Promise<DomemallAxiosResponse<T>>;
}

interface DomemallImageUploadResponse {
  success: boolean;
  message?: string;
  field?: string;
  data?: {
    id: number;
    original_name: string;
    file_path: string;
    file_url: string;
    mime_type: string;
    file_size: number;
  };
}

export interface DomemallImageUploadResult {
  id: number;
  originalName: string;
  filePath: string;
  fileUrl: string;
  mimeType: string;
  fileSize: number;
}

export interface DomemallImageUploadOptions {
  file: File;
  category: string;
  url?: string;
  csrfRoot?: ParentNode;
}

declare global {
  interface Window {
    axios: DomemallAxiosLike;
    uiAlert?: (message: string, title?: string) => Promise<void>;
    DomemallImageUpload?: {
      upload: (options: DomemallImageUploadOptions) => Promise<DomemallImageUploadResult>;
      uploadFromInput: (input: HTMLInputElement, category: string) => Promise<DomemallImageUploadResult>;
    };
  }
}

const allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const maxImageSize = 5 * 1024 * 1024;

const getAdminDirectory = (): string => document.querySelector<HTMLMetaElement>('meta[name="admin-dir"]')?.content || 'dmmt';

const getUploadUrl = (): string => `/${getAdminDirectory()}/upload/image`;

const appendCsrfFields = (formData: FormData, root: ParentNode = document): Record<string, string> => {
  const nameInput = root.querySelector<HTMLInputElement>('input[name="csrf_name"]');
  const valueInput = root.querySelector<HTMLInputElement>('input[name="csrf_value"]');

  if (!nameInput || !valueInput) {
    return {};
  }

  formData.append(nameInput.name, nameInput.value);
  formData.append(valueInput.name, valueInput.value);

  return {
    [nameInput.name]: nameInput.value,
    [valueInput.name]: valueInput.value,
  };
};

const validateImageFile = (file: File, category: string): void => {
  if (!category || !/^[a-z0-9_-]{1,50}$/.test(category)) {
    throw new Error('이미지 업로드 분류가 올바르지 않습니다.');
  }

  if (!allowedImageTypes.includes(file.type)) {
    throw new Error('jpg, png, gif, webp 형식의 이미지만 업로드할 수 있습니다.');
  }

  if (file.size > maxImageSize) {
    throw new Error('이미지는 5MB 이하 파일만 업로드할 수 있습니다.');
  }
};

export const uploadImage = async (options: DomemallImageUploadOptions): Promise<DomemallImageUploadResult> => {
  validateImageFile(options.file, options.category);

  const formData = new FormData();
  formData.append('file', options.file);
  formData.append('category', options.category);
  const csrfHeaders = appendCsrfFields(formData, options.csrfRoot ?? document);

  const response = await window.axios.post<DomemallImageUploadResponse>(options.url || getUploadUrl(), formData, {
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...csrfHeaders,
    },
  });

  if (!response.data.success || !response.data.data) {
    throw new Error(response.data.message || '이미지 업로드에 실패했습니다.');
  }

  return {
    id: response.data.data.id,
    originalName: response.data.data.original_name,
    filePath: response.data.data.file_path,
    fileUrl: response.data.data.file_url,
    mimeType: response.data.data.mime_type,
    fileSize: response.data.data.file_size,
  };
};

export const uploadImageFromInput = async (input: HTMLInputElement, category: string): Promise<DomemallImageUploadResult> => {
  const file = input.files?.[0];

  if (!file) {
    throw new Error('업로드할 이미지 파일을 선택해 주세요.');
  }

  return uploadImage({ file, category, csrfRoot: input.form ?? document });
};

window.DomemallImageUpload = {
  upload: uploadImage,
  uploadFromInput: uploadImageFromInput,
};