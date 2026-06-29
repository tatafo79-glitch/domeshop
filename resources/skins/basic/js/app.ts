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
  size?: 'default' | 'margin-calc';
}

interface AdminMarginApplyMessage {
  type?: string;
  sellPrice?: number;
  supplyPrice?: number;
  shippingFee?: number;
  targetSelector?: string;
  supplyTargetSelector?: string;
  shippingTargetSelector?: string;
  actualShippingTargetSelector?: string;
  actualShippingFee?: number;
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
  if (detail.size === 'margin-calc') {
    root.classList.add('is-margin-calc');
  }
  root.setAttribute('role', 'presentation');

  const panel = document.createElement('section');
  panel.className = 'admin-iframe-modal-panel';
  if (detail.size === 'margin-calc') {
    panel.classList.add('is-margin-calc');
  }
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
      size: customEvent.detail?.size ?? 'default',
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

    const data = event.data as AdminMarginApplyMessage;
    if (data.type === 'member-assets-updated') {
      document.body.dataset.memberAssetsUpdated = '1';
    }

    if (data.type === 'admin-margin-calc-apply') {
      applyAdminMarginCalculatorResult(data);
    }

    if (data.type === 'admin-iframe-modal-close') {
      document.querySelectorAll<HTMLElement>('.admin-iframe-modal').forEach((modal: HTMLElement): void => {
        closeAdminIframeModal(modal);
      });
    }
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminIframeModal);
} else {
  initAdminIframeModal();
}

const getAdminDirectory = (): string => document.querySelector<HTMLMetaElement>('meta[name="admin-dir"]')?.content
  || window.location.pathname.split('/').filter(Boolean)[0]
  || 'dmmt';

const getNumericInputValue = (selector: string): string => document.querySelector<HTMLInputElement>(selector)
  ?.value
  .replace(/[^0-9]/g, '') ?? '';

const isHiddenBySelector = (selector: string): boolean => {
  const element = document.querySelector<HTMLElement>(selector);

  return element?.hidden === true;
};

const openAdminMarginCalculator = (trigger: HTMLElement): void => {
  const supplyTarget = trigger.dataset.marginSupplyTarget || (document.querySelector<HTMLInputElement>('#supply_price') ? '#supply_price' : '#supplyPriceInput');
  const sellTarget = trigger.dataset.marginSellTarget || (document.querySelector<HTMLInputElement>('#sell_price') ? '#sell_price' : '#sellPriceInput');
  const shippingTarget = trigger.dataset.marginShippingTarget || (document.querySelector<HTMLInputElement>('#shipping_fee') ? '#shipping_fee' : '#shippingFeeInput');
  const actualShippingTarget = trigger.dataset.marginActualShippingTarget || (document.querySelector<HTMLInputElement>('#actual_shipping_fee') ? '#actual_shipping_fee' : shippingTarget);
  const shippingRow = trigger.dataset.marginShippingRow || '';
  const shippingFee = shippingRow !== '' && isHiddenBySelector(shippingRow) ? '' : getNumericInputValue(shippingTarget);
  const actualShippingFee = shippingRow !== '' && isHiddenBySelector(shippingRow) ? '' : getNumericInputValue(actualShippingTarget);
  const params = new URLSearchParams({
    supply_price: getNumericInputValue(supplyTarget),
    sell_price: getNumericInputValue(sellTarget),
    shipping_fee: shippingFee,
    actual_shipping_fee: actualShippingFee,
    target: sellTarget,
    supply_target: supplyTarget,
    shipping_target: shippingTarget,
    actual_shipping_target: actualShippingTarget,
  });
  const source = trigger.dataset.marginSource || '';
  if (source !== '') {
    params.set('source', source);
  }

  openAdminIframeModal({
    src: `/${getAdminDirectory()}/goods/margin-calc?${params.toString()}`,
    title: trigger.dataset.marginTitle || '마진 계산기',
    size: 'margin-calc',
  });
};

const isAllowedMarginTargetSelector = (selector: string): boolean => {
  const isIdSelector = /^#[A-Za-z][A-Za-z0-9_-]*$/i.test(selector);
  const isNameSelector = /^\[name="[A-Za-z0-9_-]+"\]$/i.test(selector);

  return isIdSelector || isNameSelector;
};

const setMarginTargetValue = (selector: string, value: number | undefined): void => {
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || !isAllowedMarginTargetSelector(selector)) {
    return;
  }

  const target = document.querySelector<HTMLInputElement>(selector);
  if (!target) {
    return;
  }

  target.value = String(Math.round(value));
  target.dispatchEvent(new Event('input', { bubbles: true }));
  target.dispatchEvent(new Event('change', { bubbles: true }));
};

const applyAdminMarginCalculatorResult = (data: AdminMarginApplyMessage): void => {
  setMarginTargetValue(data.targetSelector || '#sell_price', data.sellPrice);
  setMarginTargetValue(data.supplyTargetSelector || '#supply_price', data.supplyPrice);
  setMarginTargetValue(data.shippingTargetSelector || '#shipping_fee', data.shippingFee);
  if (typeof data.actualShippingFee === 'number') {
    setMarginTargetValue(data.actualShippingTargetSelector || '#actual_shipping_fee', data.actualShippingFee);
  }

  document.querySelectorAll<HTMLElement>('.admin-iframe-modal').forEach((modal: HTMLElement): void => {
    closeAdminIframeModal(modal);
  });
};

const initAdminMarginCalculator = (): void => {
  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const trigger = target.closest<HTMLElement>('[data-admin-margin-calculator], #openMarginCalcBtn');
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openAdminMarginCalculator(trigger);
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminMarginCalculator);
} else {
  initAdminMarginCalculator();
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
  title.textContent = options.title || '알림';

  const message = document.createElement('p');
  message.className = 'admin-ui-dialog-message';
  message.textContent = options.message;

  const actions = document.createElement('div');
  actions.className = 'admin-ui-dialog-actions';

  const confirmButton = document.createElement('button');
  confirmButton.type = 'button';
  confirmButton.className = 'admin-ui-dialog-button is-primary';
  confirmButton.textContent = options.confirmText || '확인';

  const cancelButton = document.createElement('button');
  cancelButton.type = 'button';
  cancelButton.className = 'admin-ui-dialog-button is-secondary';
  cancelButton.textContent = options.cancelText || '취소';

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
  title: title || '확인',
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

const syncAdminGoodsRadioIndicator = (group: HTMLElement): void => {
  const checkedInput = group.querySelector<HTMLInputElement>('input[type="radio"]:checked');
  const checkedLabel = checkedInput?.closest<HTMLElement>('.admin-goods-register-radio') ?? null;

  if (!checkedLabel) {
    group.style.setProperty('--radio-indicator-width', '0px');
    return;
  }

  group.style.setProperty('--radio-indicator-x', `${checkedLabel.offsetLeft}px`);
  group.style.setProperty('--radio-indicator-width', `${checkedLabel.offsetWidth}px`);
};

const syncAllAdminGoodsRadioIndicators = (): void => {
  document.querySelectorAll<HTMLElement>('.admin-goods-register-radio-group').forEach((group: HTMLElement): void => {
    syncAdminGoodsRadioIndicator(group);
  });
};

const initAdminGoodsRadioIndicators = (): void => {
  const groups = document.querySelectorAll<HTMLElement>('.admin-goods-register-radio-group');
  if (groups.length === 0) {
    return;
  }

  groups.forEach((group: HTMLElement): void => {
    group.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input: HTMLInputElement): void => {
      input.addEventListener('change', (): void => syncAdminGoodsRadioIndicator(group));
    });
  });

  syncAllAdminGoodsRadioIndicators();
  window.requestAnimationFrame(syncAllAdminGoodsRadioIndicators);
  window.setTimeout(syncAllAdminGoodsRadioIndicators, 120);
  window.addEventListener('resize', syncAllAdminGoodsRadioIndicators);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminGoodsRadioIndicators);
} else {
  initAdminGoodsRadioIndicators();
}

const syncAdminGoodsRegisterSettingShippingRows = (): void => {
  const shippingType = document.querySelector<HTMLInputElement>('input[name="default_shipping_type"]:checked')?.value ?? '';
  document.querySelectorAll<HTMLElement>('[data-setting-quantity-shipping-row]').forEach((row: HTMLElement): void => {
    const isVisible = shippingType === 'QUANTITY';
    row.hidden = !isVisible;
    row.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement>('input, select, textarea, button').forEach((control: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement): void => {
      control.disabled = !isVisible;
    });
  });
};

const initAdminGoodsRegisterSettingShippingRows = (): void => {
  const inputs = document.querySelectorAll<HTMLInputElement>('input[name="default_shipping_type"]');
  if (inputs.length === 0) {
    return;
  }

  inputs.forEach((input: HTMLInputElement): void => {
    input.addEventListener('change', syncAdminGoodsRegisterSettingShippingRows);
  });

  syncAdminGoodsRegisterSettingShippingRows();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminGoodsRegisterSettingShippingRows);
} else {
  initAdminGoodsRegisterSettingShippingRows();
}

interface AdminSettingSaveResponse {
  success: boolean;
  message?: string;
  field?: string;
}

const stripAdminSettingNumber = (value: string): string => value.replace(/,/g, '').trim();

const ADMIN_GOODS_REGISTER_COMMA_NUMBER_FIELDS = ['shipping_fee', 'actual_shipping_fee', 'extra_shipping_jeju', 'extra_shipping_island', 'return_shipping_fee', 'exchange_shipping_fee'] as const;

type AdminGoodsRegisterCommaNumberField = typeof ADMIN_GOODS_REGISTER_COMMA_NUMBER_FIELDS[number];

const focusAdminSettingField = (form: HTMLFormElement, fieldName: string): void => {
  const field = Array.from(form.elements).find((control: Element): boolean => {
    return control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement
      ? control.name === fieldName || control.id === fieldName
      : false;
  });

  if (!(field instanceof HTMLElement)) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
  window.setTimeout((): void => field.focus(), 180);
};

const formatAdminSettingCommaNumber = (value: string): string => {
  const normalized = stripAdminSettingNumber(value).replace(/[^0-9]/g, '');

  if (normalized === '') {
    return '';
  }

  return Number.parseInt(normalized, 10).toLocaleString('en-US');
};

const syncAdminSettingCommaNumberField = (input: HTMLInputElement, isFormatted: boolean): void => {
  const nextValue = isFormatted ? formatAdminSettingCommaNumber(input.value) : stripAdminSettingNumber(input.value).replace(/[^0-9]/g, '');

  if (input.value !== nextValue) {
    input.value = nextValue;
  }
};

const bindAdminSettingCommaNumberField = (input: HTMLInputElement): void => {
  if (input.dataset.adminSettingCommaBound === '1') {
    syncAdminSettingCommaNumberField(input, true);
    return;
  }

  input.dataset.adminSettingCommaBound = '1';
  input.addEventListener('focus', (): void => syncAdminSettingCommaNumberField(input, false));
  input.addEventListener('input', (): void => syncAdminSettingCommaNumberField(input, false));
  input.addEventListener('blur', (): void => syncAdminSettingCommaNumberField(input, true));
  syncAdminSettingCommaNumberField(input, true);
};


const syncAdminGoodsImageSettingFields = (form: HTMLFormElement): void => {
  ADMIN_GOODS_REGISTER_COMMA_NUMBER_FIELDS.forEach((fieldName: AdminGoodsRegisterCommaNumberField): void => {
    const input = form.querySelector<HTMLInputElement>(`#${fieldName}`);

    if (!input) {
      return;
    }

    bindAdminSettingCommaNumberField(input);
  });
};

const initAdminGoodsImageDimensionFields = (_form: HTMLFormElement): void => {
};

const createAdminSettingFormData = (form: HTMLFormElement): FormData => {
  const formData = new FormData(form);

  form.querySelectorAll<HTMLInputElement>('input[inputmode="numeric"][name], input[inputmode="decimal"][name]').forEach((input: HTMLInputElement): void => {
    if (!input.disabled) {
      formData.set(input.name, stripAdminSettingNumber(input.value));
    }
  });


  const shippingType = form.querySelector<HTMLInputElement>('input[name="default_shipping_type"]:checked')?.value ?? '';
  if (shippingType !== 'QUANTITY') {
    formData.set('shipping_qty_limit', '0');
  }

  return formData;
};

const initAdminGoodsRegisterSettingForm = (): void => {
  const form = document.querySelector<HTMLFormElement>('#goodsRegisterSettingForm');
  if (!form) {
    return;
  }

  initAdminGoodsImageDimensionFields(form);
  syncAdminGoodsImageSettingFields(form);

  const submitButtons = Array.from(form.querySelectorAll<HTMLButtonElement>('button[type="submit"]'));
  const setSubmitting = (isSubmitting: boolean): void => {
    submitButtons.forEach((button: HTMLButtonElement): void => {
      button.disabled = isSubmitting;
    });
  };

  form.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();

    if (!window.axios) {
      await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    setSubmitting(true);
    try {
      const response = await window.axios.post<AdminSettingSaveResponse>(form.action, createAdminSettingFormData(form), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      await window.uiAlert?.(response.data.message ?? '상품 등록설정이 저장되었습니다.');
    } catch (error: unknown) {
      if (window.axios.isAxiosError<AdminSettingSaveResponse>(error)) {
        const payload = error.response?.data;
        await window.uiAlert?.(payload?.message ?? '상품 등록설정을 저장하지 못했습니다.');
        if (payload?.field) {
          focusAdminSettingField(form, payload.field);
        }
        return;
      }

      await window.uiAlert?.('상품 등록설정을 저장하지 못했습니다.');
    } finally {
      setSubmitting(false);
    }
  });

  form.addEventListener('reset', (): void => {
    window.setTimeout((): void => {
      syncAdminGoodsRegisterSettingShippingRows();
      syncAdminGoodsImageSettingFields(form);
      syncAllAdminGoodsRadioIndicators();
    }, 0);
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminGoodsRegisterSettingForm);
} else {
  initAdminGoodsRegisterSettingForm();
}
const ADMIN_MOBILE_COLLAPSIBLE_QUERY = '(max-width: 767px)';

const setAdminMobileCollapsibleCardExpanded = (card: HTMLElement, isExpanded: boolean): void => {
  card.classList.toggle('is-collapsed', !isExpanded);
  const trigger = card.querySelector<HTMLButtonElement>('[data-admin-mobile-collapsible-trigger]');
  if (trigger) {
    trigger.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
  }
};

const expandAdminMobileCollapsibleCard = (element: HTMLElement): void => {
  const card = element.closest<HTMLElement>('[data-admin-mobile-collapsible-card]');
  if (card) {
    setAdminMobileCollapsibleCardExpanded(card, true);
  }
};

const initAdminMobileCollapsibleCards = (): void => {
  const cards = Array.from(document.querySelectorAll<HTMLElement>('[data-admin-mobile-collapsible-card]'));
  if (cards.length === 0) {
    return;
  }

  const mediaQuery = window.matchMedia(ADMIN_MOBILE_COLLAPSIBLE_QUERY);
  const isAlwaysCollapsible = (card: HTMLElement): boolean => card.dataset.adminCollapsibleMode === 'all';
  const canToggle = (card: HTMLElement): boolean => isAlwaysCollapsible(card) || mediaQuery.matches;

  cards.forEach((card: HTMLElement): void => {
    const trigger = card.querySelector<HTMLButtonElement>('[data-admin-mobile-collapsible-trigger]');
    if (!trigger) {
      return;
    }

    trigger.addEventListener('click', (event: MouseEvent): void => {
      if (!canToggle(card)) {
        return;
      }

      event.preventDefault();
      setAdminMobileCollapsibleCardExpanded(card, card.classList.contains('is-collapsed'));
    });
  });

  const syncDesktopState = (): void => {
    if (mediaQuery.matches) {
      return;
    }

    cards
      .filter((card: HTMLElement): boolean => !isAlwaysCollapsible(card))
      .forEach((card: HTMLElement): void => setAdminMobileCollapsibleCardExpanded(card, true));
  };

  mediaQuery.addEventListener('change', syncDesktopState);
  syncDesktopState();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminMobileCollapsibleCards);
} else {
  initAdminMobileCollapsibleCards();
}
interface AdminPlatformFeeResponse {
  success: boolean;
  message?: string;
  field?: string;
}

interface AdminPlatformFeeEditData {
  id: string;
  platformName: string;
  platformCode: string;
  platformFeeRate: string;
  shippingFeeRate: string;
  instantDiscountRate: string;
  additionalDiscountRate: string;
  additionalFixedDiscount: string;
  sortOrder: string;
  isDefault: string;
  memo: string;
}

const stripAdminPlatformFeeNumber = (value: string): string => value.replace(/,/g, '').trim();

const focusAdminPlatformFeeField = (form: HTMLFormElement, fieldName: string | undefined): void => {
  if (!fieldName) {
    return;
  }

  const field = Array.from(form.elements).find((control: Element): boolean => {
    return control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement
      ? control.name === fieldName || control.id === fieldName
      : false;
  });

  if (!(field instanceof HTMLElement)) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
  window.setTimeout((): void => field.focus(), 180);
};

const getAdminPlatformFeeInput = (form: HTMLFormElement, name: string): HTMLInputElement | null => {
  return form.querySelector<HTMLInputElement>(`[name="${name}"]`);
};

const getAdminPlatformFeeTextarea = (form: HTMLFormElement, name: string): HTMLTextAreaElement | null => {
  return form.querySelector<HTMLTextAreaElement>(`[name="${name}"]`);
};

const createAdminPlatformFeeFormData = (form: HTMLFormElement): FormData => {
  const formData = new FormData(form);

  form.querySelectorAll<HTMLInputElement>('input[inputmode="numeric"][name], input[inputmode="decimal"][name]').forEach((input: HTMLInputElement): void => {
    formData.set(input.name, stripAdminPlatformFeeNumber(input.value));
  });

  if (!form.querySelector<HTMLInputElement>('[name="is_default"]')?.checked) {
    formData.set('is_default', 'N');
  }

  return formData;
};

const createAdminPlatformFeeDeleteFormData = (form: HTMLFormElement): FormData => {
  const formData = new FormData();
  form.querySelectorAll<HTMLInputElement>('input[type="hidden"][name]').forEach((input: HTMLInputElement): void => {
    if (input.name !== 'id') {
      formData.set(input.name, input.value);
    }
  });

  return formData;
};

const validateAdminPlatformFeeForm = (form: HTMLFormElement): { message: string; field: string } | null => {
  const platformName = getAdminPlatformFeeInput(form, 'platform_name')?.value.trim() ?? '';
  if (platformName === '' || platformName.length > 50) {
    return { message: '플랫폼명을 50자 이내로 입력해 주세요.', field: 'platform_name' };
  }

  const platformCode = getAdminPlatformFeeInput(form, 'platform_code')?.value.trim() ?? '';
  if (!/^[a-z0-9_-]{2,50}$/.test(platformCode)) {
    return { message: '플랫폼 코드는 영문 소문자, 숫자, 하이픈, 언더바 2~50자로 입력해 주세요.', field: 'platform_code' };
  }

  const rateFields: Array<{ name: string; label: string }> = [
    { name: 'platform_fee_rate', label: '플랫폼 수수료' },
    { name: 'shipping_fee_rate', label: '배송비 수수료' },
    { name: 'instant_discount_rate', label: '즉시할인' },
    { name: 'additional_discount_rate', label: '부가할인' },
  ];

  for (const field of rateFields) {
    const value = stripAdminPlatformFeeNumber(getAdminPlatformFeeInput(form, field.name)?.value ?? '');
    const numberValue = Number(value);
    if (value === '' || Number.isNaN(numberValue) || numberValue < 0 || numberValue > 100) {
      return { message: `${field.label}는 0~100 사이의 숫자로 입력해 주세요.`, field: field.name };
    }
  }

  const fixedDiscount = stripAdminPlatformFeeNumber(getAdminPlatformFeeInput(form, 'additional_fixed_discount')?.value ?? '');
  if (!/^\d+$/.test(fixedDiscount)) {
    return { message: '부가정액할인은 0 이상의 숫자로 입력해 주세요.', field: 'additional_fixed_discount' };
  }

  const sortOrder = stripAdminPlatformFeeNumber(getAdminPlatformFeeInput(form, 'sort_order')?.value ?? '');
  if (!/^\d+$/.test(sortOrder)) {
    return { message: '정렬 순서는 0 이상의 숫자로 입력해 주세요.', field: 'sort_order' };
  }

  const memo = getAdminPlatformFeeTextarea(form, 'memo')?.value.trim() ?? '';
  if (memo.length > 255) {
    return { message: '메모는 255자 이내로 입력해 주세요.', field: 'memo' };
  }

  return null;
};

const setAdminPlatformFeeCreateMode = (form: HTMLFormElement): void => {
  const createAction = form.dataset.createAction ?? form.action;
  form.action = createAction;

  const textValues: Record<string, string> = {
    id: '',
    platform_name: '',
    platform_code: '',
    platform_fee_rate: '0',
    shipping_fee_rate: '0',
    instant_discount_rate: '0',
    additional_discount_rate: '0',
    additional_fixed_discount: '0',
    sort_order: '0',
  };

  Object.entries(textValues).forEach(([name, value]: [string, string]): void => {
    const input = getAdminPlatformFeeInput(form, name);
    if (input) {
      input.value = value;
      input.defaultValue = value;
    }
  });

  const isDefault = getAdminPlatformFeeInput(form, 'is_default');
  if (isDefault) {
    isDefault.checked = false;
    isDefault.defaultChecked = false;
  }

  const memo = getAdminPlatformFeeTextarea(form, 'memo');
  if (memo) {
    memo.value = '';
    memo.defaultValue = '';
  }

  const title = document.querySelector<HTMLElement>('[data-platform-fee-form-title]');
  if (title) {
    title.textContent = '플랫폼 등록';
  }

  const submit = form.querySelector<HTMLButtonElement>('[data-platform-fee-submit]');
  if (submit) {
    submit.textContent = '등록';
  }
};

const setAdminPlatformFeeEditMode = (form: HTMLFormElement, data: AdminPlatformFeeEditData): void => {
  const createAction = form.dataset.createAction ?? form.action;
  form.action = `${createAction}/${data.id}`;

  const values: Record<string, string> = {
    id: data.id,
    platform_name: data.platformName,
    platform_code: data.platformCode,
    platform_fee_rate: data.platformFeeRate,
    shipping_fee_rate: data.shippingFeeRate,
    instant_discount_rate: data.instantDiscountRate,
    additional_discount_rate: data.additionalDiscountRate,
    additional_fixed_discount: data.additionalFixedDiscount,
    sort_order: data.sortOrder,
  };

  Object.entries(values).forEach(([name, value]: [string, string]): void => {
    const input = getAdminPlatformFeeInput(form, name);
    if (input) {
      input.value = value;
    }
  });

  const isDefault = getAdminPlatformFeeInput(form, 'is_default');
  if (isDefault) {
    isDefault.checked = data.isDefault === 'Y';
  }

  const memo = getAdminPlatformFeeTextarea(form, 'memo');
  if (memo) {
    memo.value = data.memo;
  }

  const title = document.querySelector<HTMLElement>('[data-platform-fee-form-title]');
  if (title) {
    title.textContent = '플랫폼 수정';
  }

  const submit = form.querySelector<HTMLButtonElement>('[data-platform-fee-submit]');
  if (submit) {
    submit.textContent = '수정';
  }

  expandAdminMobileCollapsibleCard(form);
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  window.setTimeout((): void => getAdminPlatformFeeInput(form, 'platform_name')?.focus(), 180);
};

const initAdminPlatformFeeSetting = (): void => {
  const form = document.querySelector<HTMLFormElement>('#platformFeeSettingForm');
  if (!form) {
    return;
  }

  const submitButtons = Array.from(form.querySelectorAll<HTMLButtonElement>('button[type="submit"]'));
  const setSubmitting = (isSubmitting: boolean): void => {
    submitButtons.forEach((button: HTMLButtonElement): void => {
      button.disabled = isSubmitting;
    });
  };

  form.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();

    if (!window.axios) {
      await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    const validation = validateAdminPlatformFeeForm(form);
    if (validation) {
      await window.uiAlert?.(validation.message);
      focusAdminPlatformFeeField(form, validation.field);
      return;
    }

    setSubmitting(true);
    try {
      const response = await window.axios.post<AdminPlatformFeeResponse>(form.action, createAdminPlatformFeeFormData(form), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      await window.uiAlert?.(response.data.message ?? '플랫폼 수수료가 저장되었습니다.');
      window.location.reload();
    } catch (error: unknown) {
      if (window.axios.isAxiosError<AdminPlatformFeeResponse>(error)) {
        const payload = error.response?.data;
        await window.uiAlert?.(payload?.message ?? '플랫폼 수수료를 저장하지 못했습니다.');
        focusAdminPlatformFeeField(form, payload?.field);
        return;
      }

      await window.uiAlert?.('플랫폼 수수료를 저장하지 못했습니다.');
    } finally {
      setSubmitting(false);
    }
  });

  form.addEventListener('reset', (event: Event): void => {
    event.preventDefault();
    setAdminPlatformFeeCreateMode(form);
  });

  document.querySelectorAll<HTMLButtonElement>('[data-platform-fee-edit]').forEach((button: HTMLButtonElement): void => {
    button.addEventListener('click', (): void => {
      setAdminPlatformFeeEditMode(form, {
        id: button.dataset.id ?? '',
        platformName: button.dataset.platformName ?? '',
        platformCode: button.dataset.platformCode ?? '',
        platformFeeRate: button.dataset.platformFeeRate ?? '0',
        shippingFeeRate: button.dataset.shippingFeeRate ?? '0',
        instantDiscountRate: button.dataset.instantDiscountRate ?? '0',
        additionalDiscountRate: button.dataset.additionalDiscountRate ?? '0',
        additionalFixedDiscount: button.dataset.additionalFixedDiscount ?? '0',
        sortOrder: button.dataset.sortOrder ?? '0',
        isDefault: button.dataset.isDefault ?? 'N',
        memo: button.dataset.memo ?? '',
      });
    });
  });

  document.querySelectorAll<HTMLButtonElement>('[data-platform-fee-delete]').forEach((button: HTMLButtonElement): void => {
    button.addEventListener('click', async (): Promise<void> => {
      if (!window.axios) {
        await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
        return;
      }

      const confirmed = await window.uiConfirm?.('플랫폼 수수료를 삭제하시겠습니까?', '확인');
      if (confirmed !== true) {
        return;
      }

      const deleteUrl = button.dataset.deleteUrl ?? '';
      if (deleteUrl === '') {
        await window.uiAlert?.('삭제 요청 주소가 올바르지 않습니다. 목록에서 다시 선택해 주세요.');
        return;
      }

      button.disabled = true;
      try {
        const response = await window.axios.post<AdminPlatformFeeResponse>(deleteUrl, createAdminPlatformFeeDeleteFormData(form), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        await window.uiAlert?.(response.data.message ?? '플랫폼 수수료가 삭제되었습니다.');
        window.location.reload();
      } catch (error: unknown) {
        if (window.axios.isAxiosError<AdminPlatformFeeResponse>(error)) {
          await window.uiAlert?.(error.response?.data?.message ?? '플랫폼 수수료를 삭제하지 못했습니다.');
          return;
        }

        await window.uiAlert?.('플랫폼 수수료를 삭제하지 못했습니다.');
      } finally {
        button.disabled = false;
      }
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminPlatformFeeSetting);
} else {
  initAdminPlatformFeeSetting();
}

interface AdminProhibitedWordResponse {
  success: boolean;
  message?: string;
  field?: string;
}

type AdminProhibitedWordWordField = HTMLInputElement | HTMLTextAreaElement;

const ADMIN_PROHIBITED_WORD_BATCH_LIMIT = 1000;

interface AdminProhibitedWordEditData {
  id: string;
  wordType: string;
  word: string;
  targetScope: string;
  matchType: string;
  isActive: string;
  memo: string;
}

const getAdminProhibitedWordInput = (form: HTMLFormElement, name: string): HTMLInputElement | null => {
  return form.querySelector<HTMLInputElement>(`[name="${name}"]`);
};

const getAdminProhibitedWordSelect = (form: HTMLFormElement, name: string): HTMLSelectElement | null => {
  return form.querySelector<HTMLSelectElement>(`[name="${name}"]`);
};

const getAdminProhibitedWordTextarea = (form: HTMLFormElement, name: string): HTMLTextAreaElement | null => {
  return form.querySelector<HTMLTextAreaElement>(`[name="${name}"]`);
};

const getAdminProhibitedWordWordField = (form: HTMLFormElement): AdminProhibitedWordWordField | null => {
  return form.querySelector<AdminProhibitedWordWordField>('[name="word"]');
};

const splitAdminProhibitedWords = (wordText: string): string[] => {
  return wordText
    .split(/[,，\r\n]+/)
    .map((word: string): string => word.trim())
    .filter((word: string): boolean => word !== '');
};

const focusAdminProhibitedWordField = (form: HTMLFormElement, fieldName: string | undefined): void => {
  if (!fieldName) {
    return;
  }

  const field = Array.from(form.elements).find((control: Element): boolean => {
    return control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement
      ? control.name === fieldName || control.id === fieldName
      : false;
  });

  if (!(field instanceof HTMLElement)) {
    return;
  }

  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
  window.setTimeout((): void => field.focus(), 180);
};

const createAdminProhibitedWordFormData = (form: HTMLFormElement): FormData => {
  const formData = new FormData(form);
  if (!getAdminProhibitedWordInput(form, 'is_active')?.checked) {
    formData.set('is_active', 'N');
  }

  return formData;
};

const createAdminProhibitedWordDeleteFormData = (form: HTMLFormElement): FormData => {
  const formData = new FormData();
  form.querySelectorAll<HTMLInputElement>('input[type="hidden"][name]').forEach((input: HTMLInputElement): void => {
    if (input.name !== 'id') {
      formData.set(input.name, input.value);
    }
  });

  return formData;
};

const validateAdminProhibitedWordForm = (form: HTMLFormElement): { message: string; field: string } | null => {
  const wordType = getAdminProhibitedWordSelect(form, 'word_type')?.value ?? '';
  if (!['PROHIBITED', 'ADULT'].includes(wordType)) {
    return { message: '단어 유형을 올바르게 선택해 주세요.', field: 'word_type' };
  }

  const wordText = getAdminProhibitedWordWordField(form)?.value.trim() ?? '';
  const words = splitAdminProhibitedWords(wordText);
  if (wordText === '') {
    return { message: '단어를 입력해 주세요.', field: 'word' };
  }
  if (words.length < 1) {
    return { message: '검사 가능한 단어를 입력해 주세요.', field: 'word' };
  }
  if (words.length > ADMIN_PROHIBITED_WORD_BATCH_LIMIT) {
    return { message: `한 번에 등록할 단어는 ${ADMIN_PROHIBITED_WORD_BATCH_LIMIT}개 이하로 입력해 주세요.`, field: 'word' };
  }
  if (words.some((word: string): boolean => word.length > 100)) {
    return { message: '각 단어는 100자 이하로 입력해 주세요.', field: 'word' };
  }

  const targetScope = getAdminProhibitedWordSelect(form, 'target_scope')?.value ?? '';
  if (!['NAME', 'MANUFACTURER', 'KEYWORD', 'NAME_KEYWORD', 'BOTH'].includes(targetScope)) {
    return { message: '검사 대상을 올바르게 선택해 주세요.', field: 'target_scope' };
  }

  const matchType = getAdminProhibitedWordSelect(form, 'match_type')?.value ?? '';
  if (!['CONTAINS', 'EXACT', 'WORD'].includes(matchType)) {
    return { message: '매칭 방식을 올바르게 선택해 주세요.', field: 'match_type' };
  }


  const memo = getAdminProhibitedWordTextarea(form, 'memo')?.value.trim() ?? '';
  if (memo.length > 255) {
    return { message: '메모는 255자 이하로 입력해 주세요.', field: 'memo' };
  }

  return null;
};

const setAdminProhibitedWordCreateMode = (form: HTMLFormElement): void => {
  const createAction = form.dataset.createAction ?? form.action;
  form.action = createAction;

  const id = getAdminProhibitedWordInput(form, 'id');
  if (id) {
    id.value = '';
  }

  const word = getAdminProhibitedWordWordField(form);
  if (word) {
    word.value = '';
  }


  const isActive = getAdminProhibitedWordInput(form, 'is_active');
  if (isActive) {
    isActive.checked = true;
  }

  const memo = getAdminProhibitedWordTextarea(form, 'memo');
  if (memo) {
    memo.value = '';
  }

  const wordType = getAdminProhibitedWordSelect(form, 'word_type');
  if (wordType) {
    wordType.value = 'PROHIBITED';
  }

  const targetScope = getAdminProhibitedWordSelect(form, 'target_scope');
  if (targetScope) {
    targetScope.value = 'BOTH';
  }

  const matchType = getAdminProhibitedWordSelect(form, 'match_type');
  if (matchType) {
    matchType.value = 'CONTAINS';
  }

  const title = document.querySelector<HTMLElement>('[data-prohibited-word-form-title]');
  if (title) {
    title.textContent = '금지단어 등록';
  }

  const submit = form.querySelector<HTMLButtonElement>('[data-prohibited-word-submit]');
  if (submit) {
    submit.textContent = '등록';
  }
};

const setAdminProhibitedWordEditMode = (form: HTMLFormElement, data: AdminProhibitedWordEditData): void => {
  const createAction = form.dataset.createAction ?? form.action;
  form.action = `${createAction}/${data.id}`;

  getAdminProhibitedWordInput(form, 'id')!.value = data.id;
  getAdminProhibitedWordWordField(form)!.value = data.word;
  getAdminProhibitedWordSelect(form, 'word_type')!.value = data.wordType;
  getAdminProhibitedWordSelect(form, 'target_scope')!.value = data.targetScope;
  getAdminProhibitedWordSelect(form, 'match_type')!.value = data.matchType;
  getAdminProhibitedWordInput(form, 'is_active')!.checked = data.isActive === 'Y';
  getAdminProhibitedWordTextarea(form, 'memo')!.value = data.memo;

  const title = document.querySelector<HTMLElement>('[data-prohibited-word-form-title]');
  if (title) {
    title.textContent = '금지단어 수정';
  }

  const submit = form.querySelector<HTMLButtonElement>('[data-prohibited-word-submit]');
  if (submit) {
    submit.textContent = '수정';
  }

  expandAdminMobileCollapsibleCard(form);
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  window.setTimeout((): void => getAdminProhibitedWordWordField(form)?.focus(), 180);
};

const initAdminProhibitedWordSetting = (): void => {
  const form = document.querySelector<HTMLFormElement>('#prohibitedWordForm');
  if (!form) {
    return;
  }

  const submitButtons = Array.from(form.querySelectorAll<HTMLButtonElement>('button[type="submit"]'));
  const setSubmitting = (isSubmitting: boolean): void => {
    submitButtons.forEach((button: HTMLButtonElement): void => {
      button.disabled = isSubmitting;
    });
  };

  form.addEventListener('submit', async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();

    if (!window.axios) {
      await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
      return;
    }

    const validation = validateAdminProhibitedWordForm(form);
    if (validation) {
      await window.uiAlert?.(validation.message);
      focusAdminProhibitedWordField(form, validation.field);
      return;
    }

    const wordText = getAdminProhibitedWordWordField(form)?.value.trim() ?? '';
    if ((getAdminProhibitedWordInput(form, 'id')?.value ?? '') !== '' && splitAdminProhibitedWords(wordText).length > 1) {
      setAdminProhibitedWordCreateMode(form);
      const wordField = getAdminProhibitedWordWordField(form);
      if (wordField) {
        wordField.value = wordText;
      }
    }

    setSubmitting(true);
    try {
      const response = await window.axios.post<AdminProhibitedWordResponse>(form.action, createAdminProhibitedWordFormData(form), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      await window.uiAlert?.(response.data.message ?? '금지단어가 저장되었습니다.');
      window.location.reload();
    } catch (error: unknown) {
      if (window.axios.isAxiosError<AdminProhibitedWordResponse>(error)) {
        const payload = error.response?.data;
        await window.uiAlert?.(payload?.message ?? '금지단어를 저장하지 못했습니다.');
        focusAdminProhibitedWordField(form, payload?.field);
        return;
      }

      await window.uiAlert?.('금지단어를 저장하지 못했습니다.');
    } finally {
      setSubmitting(false);
    }
  });

  form.addEventListener('reset', (event: Event): void => {
    event.preventDefault();
    setAdminProhibitedWordCreateMode(form);
  });

  document.querySelectorAll<HTMLButtonElement>('[data-prohibited-word-edit]').forEach((button: HTMLButtonElement): void => {
    button.addEventListener('click', (): void => {
      setAdminProhibitedWordEditMode(form, {
        id: button.dataset.id ?? '',
        wordType: button.dataset.wordType ?? 'PROHIBITED',
        word: button.dataset.word ?? '',
        targetScope: button.dataset.targetScope ?? 'BOTH',
        matchType: button.dataset.matchType ?? 'CONTAINS',
        isActive: button.dataset.isActive ?? 'Y',
        memo: button.dataset.memo ?? '',
      });
    });
  });

  document.querySelectorAll<HTMLButtonElement>('[data-prohibited-word-delete]').forEach((button: HTMLButtonElement): void => {
    button.addEventListener('click', async (): Promise<void> => {
      if (!window.axios) {
        await window.uiAlert?.('요청을 처리할 수 없습니다. 새로고침 후 다시 시도해 주세요.');
        return;
      }

      const confirmed = await window.uiConfirm?.('금지단어를 삭제하시겠습니까?', '확인');
      if (confirmed !== true) {
        return;
      }

      const deleteUrl = button.dataset.deleteUrl ?? '';
      if (deleteUrl === '') {
        await window.uiAlert?.('삭제 요청 주소가 올바르지 않습니다. 목록에서 다시 선택해 주세요.');
        return;
      }

      button.disabled = true;
      try {
        const response = await window.axios.post<AdminProhibitedWordResponse>(deleteUrl, createAdminProhibitedWordDeleteFormData(form), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        await window.uiAlert?.(response.data.message ?? '금지단어가 삭제되었습니다.');
        window.location.reload();
      } catch (error: unknown) {
        if (window.axios.isAxiosError<AdminProhibitedWordResponse>(error)) {
          await window.uiAlert?.(error.response?.data?.message ?? '금지단어를 삭제하지 못했습니다.');
          return;
        }

        await window.uiAlert?.('금지단어를 삭제하지 못했습니다.');
      } finally {
        button.disabled = false;
      }
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminProhibitedWordSetting);
} else {
  initAdminProhibitedWordSetting();
}
