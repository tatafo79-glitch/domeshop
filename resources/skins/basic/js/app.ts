import './commons/upload';

interface CompanyQuickMenuElements {
  root: HTMLElement;
  panel: HTMLElement;
  company: HTMLElement;
  user: HTMLElement;
  links: NodeListOf<HTMLAnchorElement>;
}

const getCompanyQuickMenuElements = (): CompanyQuickMenuElements | null => {
  const root = document.querySelector<HTMLElement>('[data-admin-company-quick-menu-panel]');
  const panel = root?.querySelector<HTMLElement>('.admin-company-quick-menu-panel') ?? null;
  const company = root?.querySelector<HTMLElement>('[data-admin-company-quick-menu-company]') ?? null;
  const user = root?.querySelector<HTMLElement>('[data-admin-company-quick-menu-user]') ?? null;
  const links = root?.querySelectorAll<HTMLAnchorElement>('[data-admin-company-quick-menu-link]') ?? null;

  if (!root || !panel || !company || !user || !links) {
    return null;
  }

  return { root, panel, company, user, links };
};

const initCompanyQuickMenu = (): void => {
  const elements = getCompanyQuickMenuElements();

  if (!elements) {
    return;
  }


  const adminDirectory = document.querySelector<HTMLMetaElement>('meta[name="admin-dir"]')?.content || 'dmmt';

  const buildLink = (type: string, memberId: string): string => {
    const base = `/${adminDirectory}`;
    const links: Record<string, string> = {
      crm: `${base}/member/crm/${memberId}`,
      cms: `${base}/member/detail/${memberId}`,
      scm: `${base}/scm/info?member_id=${memberId}`,
      notification: `${base}/notification/list?member_id=${memberId}`,
      deposit: `${base}/member/deposit/${memberId}`,
      point: `${base}/member/point/${memberId}`,
    };

    return links[type] || '#';
  };

  const isAdminRole = (role: string): boolean => role.toUpperCase() === 'ADMIN';

  const syncLinks = (memberId: string): void => {
    elements.links.forEach((link: HTMLAnchorElement): void => {
      const type = link.dataset.adminCompanyQuickMenuLink || '';
      const href = buildLink(type, memberId);
      link.href = href;
      link.dataset.adminCompanyQuickMenuHref = href;
    });
  };

  const close = (): void => {
    elements.root.classList.remove('is-open');
    document.body.classList.remove('admin-company-quick-menu-open');
    window.setTimeout((): void => {
      if (!elements.root.classList.contains('is-open')) {
        elements.root.hidden = true;
      }
    }, 180);
  };

  const open = (trigger: HTMLElement): void => {
    const companyName = trigger.dataset.companyName || '-';
    const userId = trigger.dataset.userId || '';
    const role = trigger.dataset.role || '';
    const memberId = trigger.dataset.memberId || '';

    if (isAdminRole(role)) {
      close();
      return;
    }

    syncLinks(memberId);
    elements.company.textContent = companyName;
    elements.user.textContent = [role, userId].filter(Boolean).join(' / ') || '-';
    elements.root.hidden = false;
    document.body.classList.add('admin-company-quick-menu-open');

    window.requestAnimationFrame((): void => {
      elements.root.classList.add('is-open');
      elements.panel.focus();
    });
  };

  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLElement>('[data-admin-company-quick-menu]');
    if (trigger) {
      event.preventDefault();
      open(trigger);
      return;
    }


    const quickMenuLink = target.closest<HTMLAnchorElement>('[data-admin-company-quick-menu-link]');
    if (quickMenuLink) {
      const href = quickMenuLink.dataset.adminCompanyQuickMenuHref || quickMenuLink.href;
      const modalTitle = quickMenuLink.dataset.adminCompanyQuickMenuModalTitle || '';

      if (modalTitle !== '') {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('open-iframe-modal', { detail: { src: href, title: modalTitle } }));
      }

      return;
    }

    if (target.closest('[data-admin-company-quick-menu-close]')) {
      event.preventDefault();
      close();
    }
  });

  document.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && !elements.root.hidden) {
      close();
    }
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCompanyQuickMenu);
} else {
  initCompanyQuickMenu();
}


interface AdminIframeModalDetail {
  src: string;
  title?: string;
}

const closeAdminIframeModal = (root: HTMLElement): void => {
  root.classList.remove('is-open');
  document.body.classList.remove('admin-iframe-modal-open');
  window.setTimeout((): void => root.remove(), 160);
};

const openAdminIframeModal = (detail: AdminIframeModalDetail): void => {
  if (detail.src === '') {
    return;
  }

  document.querySelectorAll<HTMLElement>('.admin-iframe-modal').forEach((modal: HTMLElement): void => modal.remove());

  const root = document.createElement('div');
  root.className = 'admin-iframe-modal';
  root.setAttribute('role', 'presentation');

  const panel = document.createElement('section');
  panel.className = 'admin-iframe-modal-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.tabIndex = -1;

  const header = document.createElement('div');
  header.className = 'admin-iframe-modal-header';

  const title = document.createElement('h2');
  title.className = 'admin-iframe-modal-title';
  title.textContent = detail.title || '관리 화면';

  const closeButton = document.createElement('button');
  closeButton.type = 'button';
  closeButton.className = 'admin-iframe-modal-close';
  closeButton.setAttribute('aria-label', 'frame 닫기');

  const closeIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  closeIcon.classList.add('admin-iframe-modal-close-icon');
  closeIcon.setAttribute('viewBox', '0 0 24 24');
  closeIcon.setAttribute('fill', 'none');
  closeIcon.setAttribute('stroke', 'currentColor');
  closeIcon.setAttribute('stroke-linecap', 'round');
  closeIcon.setAttribute('stroke-linejoin', 'round');
  closeIcon.setAttribute('aria-hidden', 'true');

  const closeIconPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  closeIconPath.setAttribute('d', 'M18 6 6 18M6 6l12 12');
  closeIcon.append(closeIconPath);
  closeButton.append(closeIcon);

  const frame = document.createElement('iframe');
  frame.className = 'admin-iframe-modal-frame';
  frame.src = detail.src;
  frame.title = title.textContent;

  header.append(title, closeButton);
  panel.append(header, frame);
  root.append(panel);
  document.body.append(root);
  document.body.classList.add('admin-iframe-modal-open');

  const close = (): void => closeAdminIframeModal(root);
  closeButton.addEventListener('click', close);
  root.addEventListener('click', (event: MouseEvent): void => {
    if (event.target === root) {
      close();
    }
  });
  root.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
  });

  window.requestAnimationFrame((): void => {
    root.classList.add('is-open');
    panel.focus();
  });
};

const initAdminIframeModal = (): void => {
  window.addEventListener('open-iframe-modal', (event: Event): void => {
    const customEvent = event as CustomEvent<AdminIframeModalDetail>;
    openAdminIframeModal({
      src: customEvent.detail?.src ?? '',
      title: customEvent.detail?.title ?? '',
    });
  });

  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLAnchorElement>('[data-admin-iframe-modal]');
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openAdminIframeModal({
      src: trigger.href,
      title: trigger.dataset.adminIframeModalTitle || trigger.textContent?.trim() || '관리 화면',
    });
  });

  window.addEventListener('message', (event: MessageEvent): void => {
    if (event.origin !== window.location.origin) {
      return;
    }

    const data = event.data as { type?: string };
    if (data.type === 'member-assets-updated') {
      document.body.dataset.memberAssetsUpdated = '1';
    }
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminIframeModal);
} else {
  initAdminIframeModal();
}
type KakaoPostcodeData = {
  zonecode?: string;
  roadAddress?: string;
  jibunAddress?: string;
};

type KakaoPostcodeOptions = {
  oncomplete: (data: KakaoPostcodeData) => void;
};

type KakaoPostcodeConstructor = new (options: KakaoPostcodeOptions) => { open: () => void };

interface DomemallPostcodeOpenOptions {
  postcodeSelector: string;
  addressSelector: string;
  detailAddressSelector?: string;
}

declare global {
  interface Window {
    kakao?: {
      Postcode?: KakaoPostcodeConstructor;
    };
    DomemallPostcode?: {
      open: (options: DomemallPostcodeOpenOptions) => void;
    };
  }
}

const KAKAO_POSTCODE_SCRIPT_SRC = 'https://t1.kakaocdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
let kakaoPostcodeScriptPromise: Promise<void> | null = null;

const loadKakaoPostcodeScript = (): Promise<void> => {
  if (window.kakao?.Postcode) {
    return Promise.resolve();
  }

  if (kakaoPostcodeScriptPromise) {
    return kakaoPostcodeScriptPromise;
  }

  kakaoPostcodeScriptPromise = new Promise<void>((resolve, reject): void => {
    const existingScript = document.querySelector<HTMLScriptElement>(`script[src="${KAKAO_POSTCODE_SCRIPT_SRC}"]`);

    if (existingScript) {
      existingScript.addEventListener('load', (): void => resolve(), { once: true });
      existingScript.addEventListener('error', (): void => reject(new Error('Kakao postcode script load failed.')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = KAKAO_POSTCODE_SCRIPT_SRC;
    script.async = true;
    script.onload = (): void => resolve();
    script.onerror = (): void => reject(new Error('Kakao postcode script load failed.'));
    document.head.appendChild(script);
  });

  return kakaoPostcodeScriptPromise;
};

const setInputValue = (selector: string | undefined, value: string): HTMLInputElement | null => {
  if (!selector) {
    return null;
  }

  const input = document.querySelector<HTMLInputElement>(selector);

  if (!input) {
    return null;
  }

  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));

  return input;
};

const openDomemallPostcode = async (options: DomemallPostcodeOpenOptions): Promise<void> => {
  await loadKakaoPostcodeScript();

  if (!window.kakao?.Postcode) {
    return;
  }

  new window.kakao.Postcode({
    oncomplete: (data: KakaoPostcodeData): void => {
      const zonecode = data.zonecode || '';
      const address = data.roadAddress || data.jibunAddress || '';

      setInputValue(options.postcodeSelector, zonecode);
      setInputValue(options.addressSelector, address);
      document.querySelector<HTMLInputElement>(options.detailAddressSelector || '')?.focus();
    },
  }).open();
};

const initDomemallPostcode = (): void => {
  window.DomemallPostcode = {
    open: (options: DomemallPostcodeOpenOptions): void => {
      void openDomemallPostcode(options);
    },
  };

  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLElement>('[data-admin-postcode]');

    if (!trigger) {
      return;
    }

    event.preventDefault();
    void openDomemallPostcode({
      postcodeSelector: trigger.dataset.postcodeTarget || '',
      addressSelector: trigger.dataset.addressTarget || '',
      detailAddressSelector: trigger.dataset.detailAddressTarget || '',
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDomemallPostcode);
} else {
  initDomemallPostcode();
}


type DomemallPopupFeatureMap = Record<string, string>;

interface DomemallPopupOpenOptions {
  url: string;
  target?: string;
  width?: number;
  height?: number;
  features?: string | DomemallPopupFeatureMap;
}

declare global {
  interface Window {
    DomemallPopup?: {
      open: (options: DomemallPopupOpenOptions) => Window | null;
    };
  }
}

const parsePopupFeatures = (features?: string | DomemallPopupFeatureMap): DomemallPopupFeatureMap => {
  if (!features) {
    return {};
  }

  if (typeof features !== 'string') {
    return { ...features };
  }

  return features.split(',').reduce<DomemallPopupFeatureMap>((featureMap, feature): DomemallPopupFeatureMap => {
    const [rawKey, rawValue] = feature.split('=');
    const key = rawKey?.trim();

    if (!key) {
      return featureMap;
    }

    featureMap[key] = rawValue?.trim() || 'yes';
    return featureMap;
  }, {});
};

const stringifyPopupFeatures = (features: DomemallPopupFeatureMap): string => Object.entries(features)
  .map(([key, value]: [string, string]): string => `${key}=${value}`)
  .join(',');

const getPopupNumber = (value: string | undefined, fallback: number): number => {
  const parsed = Number.parseInt(value || '', 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

const buildCenteredPopupFeatures = (
  features?: string | DomemallPopupFeatureMap,
  width?: number,
  height?: number,
): string => {
  const featureMap = parsePopupFeatures(features);
  const popupWidth = width || getPopupNumber(featureMap.width, 600);
  const popupHeight = height || getPopupNumber(featureMap.height, 700);
  const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
  const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
  const viewportWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight || screen.height;
  const left = Math.max(0, Math.round(dualScreenLeft + (viewportWidth - popupWidth) / 2));
  const top = Math.max(0, Math.round(dualScreenTop + (viewportHeight - popupHeight) / 2));

  featureMap.width = String(popupWidth);
  featureMap.height = String(popupHeight);
  featureMap.left = String(left);
  featureMap.top = String(top);
  featureMap.screenX = String(left);
  featureMap.screenY = String(top);

  return stringifyPopupFeatures(featureMap);
};

const initDomemallPopup = (): void => {
  const nativeWindowOpen = window.open.bind(window);

  window.DomemallPopup = {
    open: (options: DomemallPopupOpenOptions): Window | null => nativeWindowOpen(
      options.url,
      options.target || '_blank',
      buildCenteredPopupFeatures(options.features, options.width, options.height),
    ),
  };

  window.open = (url?: string | URL, target?: string, features?: string): Window | null => {
    if (!features) {
      return nativeWindowOpen(url, target, features);
    }

    if (!features.includes('width=') && !features.includes('height=')) {
      return nativeWindowOpen(url, target, features);
    }

    return nativeWindowOpen(url, target, buildCenteredPopupFeatures(features));
  };

  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLElement>('[data-admin-popup]');

    if (!trigger) {
      return;
    }

    const url = trigger.dataset.popupUrl || (trigger instanceof HTMLAnchorElement ? trigger.href : '');

    if (!url) {
      return;
    }

    event.preventDefault();
    window.DomemallPopup?.open({
      url,
      target: trigger.dataset.popupTarget || '_blank',
      width: getPopupNumber(trigger.dataset.popupWidth, 600),
      height: getPopupNumber(trigger.dataset.popupHeight, 700),
      features: trigger.dataset.popupFeatures || 'scrollbars=yes,resizable=yes',
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDomemallPopup);
} else {
  initDomemallPopup();
}


const initAdminHeaderShadow = (): void => {
  const header = document.querySelector<HTMLElement>('#mainHeader');
  const scrollArea = document.querySelector<HTMLElement>('#mainScrollArea');

  if (!header || !scrollArea) {
    return;
  }

  const syncHeaderShadow = (): void => {
    header.classList.toggle('is-scrolled', scrollArea.scrollTop > 0);
  };

  syncHeaderShadow();
  scrollArea.addEventListener('scroll', syncHeaderShadow, { passive: true });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminHeaderShadow);
} else {
  initAdminHeaderShadow();
}


const initAdminMemberDetailTabs = (): void => {
  const tabGroups = document.querySelectorAll<HTMLElement>('[data-admin-member-detail-tabs]');

  tabGroups.forEach((tabGroup: HTMLElement): void => {
    const tabButtons = tabGroup.querySelectorAll<HTMLButtonElement>('[data-admin-member-detail-tab]');
    const tabPanels = tabGroup.querySelectorAll<HTMLElement>('[data-admin-member-detail-tab-panel]');

    if (tabButtons.length === 0 || tabPanels.length === 0) {
      return;
    }

    const activateTab = (targetId: string): void => {
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

    tabButtons.forEach((button: HTMLButtonElement): void => {
      button.addEventListener('click', (): void => {
        activateTab(button.dataset.adminMemberDetailTab || '');
      });
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminMemberDetailTabs);
} else {
  initAdminMemberDetailTabs();
}
interface AdminUiDialogOptions {
  message: string;
  title?: string;
  confirmText?: string;
  cancelText?: string;
  showCancel?: boolean;
}

type AdminUiDialogResolver = (value: boolean) => void;

const closeAdminUiDialog = (root: HTMLElement, resolver: AdminUiDialogResolver, value: boolean): void => {
  root.classList.remove('is-open');
  window.setTimeout((): void => {
    root.remove();
    resolver(value);
  }, 120);
};

const openAdminUiDialog = (options: AdminUiDialogOptions): Promise<boolean> => new Promise<boolean>((resolve): void => {
  const root = document.createElement('div');
  root.className = 'admin-ui-dialog';
  root.setAttribute('role', 'presentation');

  const panel = document.createElement('section');
  panel.className = 'admin-ui-dialog-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.tabIndex = -1;

  const title = document.createElement('h2');
  title.className = 'admin-ui-dialog-title';
  title.textContent = options.title || 'Notice';

  const message = document.createElement('p');
  message.className = 'admin-ui-dialog-message';
  message.textContent = options.message;

  const actions = document.createElement('div');
  actions.className = 'admin-ui-dialog-actions';

  const confirmButton = document.createElement('button');
  confirmButton.type = 'button';
  confirmButton.className = 'admin-ui-dialog-button is-primary';
  confirmButton.textContent = options.confirmText || 'OK';

  const cancelButton = document.createElement('button');
  cancelButton.type = 'button';
  cancelButton.className = 'admin-ui-dialog-button is-secondary';
  cancelButton.textContent = options.cancelText || 'Cancel';

  if (options.showCancel === true) {
    actions.append(cancelButton);
  }
  actions.append(confirmButton);
  panel.append(title, message, actions);
  root.append(panel);
  document.body.append(root);

  const resolveDialog = (value: boolean): void => closeAdminUiDialog(root, resolve, value);

  confirmButton.addEventListener('click', (): void => resolveDialog(true));
  cancelButton.addEventListener('click', (): void => resolveDialog(false));
  root.addEventListener('click', (event: MouseEvent): void => {
    if (event.target === root) {
      resolveDialog(false);
    }
  });
  root.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      resolveDialog(false);
    }
  });

  window.requestAnimationFrame((): void => {
    root.classList.add('is-open');
    confirmButton.focus();
  });
});

window.uiAlert = (message: string, title?: string): Promise<void> => openAdminUiDialog({ message, title })
  .then((): void => undefined);

window.uiConfirm = (message: string, title?: string): Promise<boolean> => openAdminUiDialog({
  message,
  title: title || 'Confirm',
  showCancel: true,
});

const closeDomemallImagePreview = (root: HTMLElement): void => {
  root.classList.remove('is-open');
  window.setTimeout((): void => root.remove(), 120);
};


const openDesktopImagePreview = (imageUrl: string): boolean => {
  const popupWidth = Math.min(1200, Math.max(900, Math.round(window.screen.availWidth * 0.72)));
  const popupHeight = Math.min(900, Math.max(640, Math.round(window.screen.availHeight * 0.78)));
  const left = Math.max(0, Math.round((window.screen.availWidth - popupWidth) / 2));
  const top = Math.max(0, Math.round((window.screen.availHeight - popupHeight) / 2));
  const popup = window.open(
    imageUrl,
    'domemallImagePreview',
    `width=${popupWidth},height=${popupHeight},left=${left},top=${top},screenX=${left},screenY=${top},scrollbars=yes,resizable=yes`,
  );

  if (popup === null) {
    return false;
  }

  popup.focus();
  return true;
};
const openDomemallImagePreview = (imageUrl: string, title: string): void => {
  if (imageUrl === '') {
    return;
  }

  if (window.innerWidth >= 1024 && openDesktopImagePreview(imageUrl)) {
    return;
  }

  const root = document.createElement('div');
  root.className = 'domemall-image-preview';
  root.setAttribute('role', 'presentation');

  const panel = document.createElement('section');
  panel.className = 'domemall-image-preview-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.tabIndex = -1;

  const header = document.createElement('div');
  header.className = 'domemall-image-preview-header';

  const heading = document.createElement('h2');
  heading.className = 'domemall-image-preview-title';
  heading.textContent = title || '\uC774\uBBF8\uC9C0 \uBBF8\uB9AC\uBCF4\uAE30';

  const closeButton = document.createElement('button');
  closeButton.type = 'button';
  closeButton.className = 'domemall-image-preview-close';
  closeButton.setAttribute('aria-label', '\uC774\uBBF8\uC9C0 \uBBF8\uB9AC\uBCF4\uAE30 \uB2EB\uAE30');

  const closeIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  closeIcon.classList.add('domemall-image-preview-close-icon');
  closeIcon.setAttribute('viewBox', '0 0 24 24');
  closeIcon.setAttribute('fill', 'none');
  closeIcon.setAttribute('stroke', 'currentColor');
  closeIcon.setAttribute('stroke-linecap', 'round');
  closeIcon.setAttribute('stroke-linejoin', 'round');
  closeIcon.setAttribute('aria-hidden', 'true');

  const closeIconPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  closeIconPath.setAttribute('d', 'M18 6 6 18M6 6l12 12');
  closeIcon.append(closeIconPath);
  closeButton.append(closeIcon);

  const body = document.createElement('div');
  body.className = 'domemall-image-preview-body';

  const image = document.createElement('img');
  image.className = 'domemall-image-preview-image';
  image.src = imageUrl;
  image.alt = title || '\uC774\uBBF8\uC9C0 \uBBF8\uB9AC\uBCF4\uAE30';

  header.append(heading, closeButton);
  body.append(image);
  panel.append(header, body);
  root.append(panel);
  document.body.append(root);

  const close = (): void => closeDomemallImagePreview(root);

  closeButton.addEventListener('click', close);
  root.addEventListener('click', (event: MouseEvent): void => {
    if (event.target === root) {
      close();
    }
  });
  root.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
  });

  window.requestAnimationFrame((): void => {
    root.classList.add('is-open');
    panel.focus();
  });
};

const initDomemallImagePreview = (): void => {
  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLElement>('[data-image-preview]');

    if (!trigger) {
      return;
    }

    const imageUrl = trigger.dataset.imagePreview || (trigger instanceof HTMLImageElement ? trigger.src : '');
    const title = trigger.dataset.imagePreviewTitle || (trigger instanceof HTMLImageElement ? trigger.alt : '');

    event.preventDefault();
    openDomemallImagePreview(imageUrl, title);
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDomemallImagePreview);
} else {
  initDomemallImagePreview();
}
