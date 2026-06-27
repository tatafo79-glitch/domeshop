interface MarginApplyMessage {
  type: 'admin-margin-calc-apply';
  sellPrice: number;
  supplyPrice: number;
  shippingFee: number;
  targetSelector: string;
  supplyTargetSelector: string;
  shippingTargetSelector: string;
}


const targetSelector = new URLSearchParams(window.location.search).get('target') || '#sell_price';
const supplyTargetSelector = new URLSearchParams(window.location.search).get('supply_target') || '#supply_price';
const shippingTargetSelector = new URLSearchParams(window.location.search).get('shipping_target') || '#shipping_fee';

const getInput = (id: string): HTMLInputElement | null => document.getElementById(id) as HTMLInputElement | null;
const getSelect = (id: string): HTMLSelectElement | null => document.getElementById(id) as HTMLSelectElement | null;
const getNode = (id: string): HTMLElement | null => document.getElementById(id);

const sellPriceInput = getInput('mcSellPrice');
const recvShippingInput = getInput('mcRecvShipping');
const supplyPriceInput = getInput('mcSupplyPrice');
const sentShippingInput = getInput('mcSentShipping');
const marketFeeInput = getInput('mcMarketFee');
const shippingFeeRateInput = getInput('mcShippingFee');
const addDiscountRateInput = getInput('mcAddDiscountRate');
const addFlatDiscountInput = getInput('mcAddFlatDiscount');
const instantDiscountRateInput = getInput('mcInstantDiscountRate');
const displayPriceInput = getInput('mcDisplayPriceInput');
const settlementNode = getNode('mcSettlement');
const targetProfitInput = getInput('mcProfit');
const targetMarginRateInput = getInput('mcMarginRate');
const resetButton = document.getElementById('mcResetBtn') as HTMLButtonElement | null;
const applyButton = document.getElementById('mcApplyBtn') as HTMLButtonElement | null;
const platformSelect = getSelect('mcPlatformSelect');

const moneyInputs: HTMLInputElement[] = [
  sellPriceInput,
  recvShippingInput,
  supplyPriceInput,
  sentShippingInput,
  addFlatDiscountInput,
].filter((input: HTMLInputElement | null): input is HTMLInputElement => input !== null);

const percentInputs: HTMLInputElement[] = [
  marketFeeInput,
  shippingFeeRateInput,
  addDiscountRateInput,
  instantDiscountRateInput,
].filter((input: HTMLInputElement | null): input is HTMLInputElement => input !== null);

const formatNumber = (value: number): string => Math.round(value).toLocaleString('ko-KR');

const parseMoneyValue = (value: string): number => Number.parseInt(value.replace(/[^0-9]/g, '') || '0', 10);

const parseSignedMoneyValue = (value: string): number => {
  let normalized = value.replace(/[^0-9-]/g, '');
  if (normalized.indexOf('-') > 0) {
    normalized = normalized.replace(/-/g, '');
  }
  if (normalized === '' || normalized === '-') {
    return 0;
  }

  return Number.parseInt(normalized, 10) || 0;
};

const parsePercentValue = (value: string): number => Number.parseFloat(value.replace(/[^0-9.]/g, '') || '0') || 0;

const formatMoneyInput = (input: HTMLInputElement): void => {
  const value = parseMoneyValue(input.value);
  input.value = value === 0 && input.value === '' ? '' : formatNumber(value);
};

const formatSignedMoneyInput = (input: HTMLInputElement): void => {
  let normalized = input.value.replace(/[^0-9-]/g, '');
  if (normalized.indexOf('-') > 0) {
    normalized = normalized.replace(/-/g, '');
  }
  if (normalized === '' || normalized === '-') {
    input.value = normalized;
    return;
  }

  const value = Number.parseInt(normalized, 10) || 0;
  input.value = `${value < 0 ? '-' : ''}${formatNumber(Math.abs(value))}`;
};

const formatPercentInput = (input: HTMLInputElement): void => {
  const parts = input.value.replace(/[^0-9.]/g, '').split('.');
  const integerPart = parts[0] || '';
  const decimalPart = parts.length > 1 ? parts.slice(1).join('').slice(0, 2) : '';
  input.value = parts.length > 1 ? `${integerPart}.${decimalPart}` : integerPart;
};

const getMoney = (input: HTMLInputElement | null): number => parseMoneyValue(input?.value ?? '');
const getPercent = (input: HTMLInputElement | null): number => parsePercentValue(input?.value ?? '');

const calculateMargin = (): void => {
  const sellPrice = getMoney(sellPriceInput);
  const recvShipping = getMoney(recvShippingInput);
  const supplyPrice = getMoney(supplyPriceInput);
  const sentShipping = getMoney(sentShippingInput);
  const marketFeeRate = getPercent(marketFeeInput);
  const shippingFeeRate = getPercent(shippingFeeRateInput);
  const addDiscountRate = getPercent(addDiscountRateInput);
  const addFlatDiscount = getMoney(addFlatDiscountInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);

  const discountAmount = Math.floor(sellPrice * (instantDiscountRate / 100))
    + Math.floor(sellPrice * (addDiscountRate / 100))
    + addFlatDiscount;
  const discountedSellPrice = Math.max(0, sellPrice - discountAmount);
  const displayPrice = Math.max(0, sellPrice - Math.floor(sellPrice * (instantDiscountRate / 100)));
  const totalReceived = discountedSellPrice + recvShipping;
  const totalCost = supplyPrice + sentShipping;
  const feeAmount = Math.floor(discountedSellPrice * (marketFeeRate / 100))
    + Math.floor(recvShipping * (shippingFeeRate / 100));
  const settlement = totalReceived - feeAmount;
  const profit = settlement - totalCost;
  const marginRate = discountedSellPrice > 0 ? (profit / discountedSellPrice) * 100 : 0;

  if (displayPriceInput && document.activeElement !== displayPriceInput) {
    displayPriceInput.value = formatNumber(displayPrice);
  }
  if (settlementNode) {
    settlementNode.textContent = formatNumber(settlement);
  }
  if (targetProfitInput && document.activeElement !== targetProfitInput) {
    targetProfitInput.value = formatNumber(profit);
  }
  if (targetMarginRateInput && document.activeElement !== targetMarginRateInput) {
    targetMarginRateInput.value = marginRate.toFixed(1);
  }
};

const calculateSellPriceByTargetProfit = (targetProfit: number): void => {
  const recvShipping = getMoney(recvShippingInput);
  const supplyPrice = getMoney(supplyPriceInput);
  const sentShipping = getMoney(sentShippingInput);
  const marketFeeRate = getPercent(marketFeeInput);
  const shippingFeeRate = getPercent(shippingFeeRateInput);
  const addDiscountRate = getPercent(addDiscountRateInput);
  const addFlatDiscount = getMoney(addFlatDiscountInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);
  const shippingNetProfit = recvShipping * (1 - shippingFeeRate / 100) - supplyPrice - sentShipping;
  const marketDenominator = 1 - marketFeeRate / 100;
  const discountDenominator = 1 - (instantDiscountRate + addDiscountRate) / 100;

  let discountedSellPrice = 0;
  if (marketDenominator !== 0) {
    discountedSellPrice = (targetProfit - shippingNetProfit) / marketDenominator;
  }

  let sellPrice = 0;
  if (discountDenominator !== 0) {
    sellPrice = (discountedSellPrice + addFlatDiscount) / discountDenominator;
  }

  if (sellPriceInput) {
    sellPriceInput.value = formatNumber(Math.max(0, Math.round(sellPrice)));
  }
  calculateMargin();
};

const calculateSellPriceByTargetRate = (targetRate: number): void => {
  const recvShipping = getMoney(recvShippingInput);
  const supplyPrice = getMoney(supplyPriceInput);
  const sentShipping = getMoney(sentShippingInput);
  const marketFeeRate = getPercent(marketFeeInput);
  const shippingFeeRate = getPercent(shippingFeeRateInput);
  const addDiscountRate = getPercent(addDiscountRateInput);
  const addFlatDiscount = getMoney(addFlatDiscountInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);
  const shippingNetProfit = recvShipping * (1 - shippingFeeRate / 100) - supplyPrice - sentShipping;
  const discountedDenominator = 1 - (marketFeeRate + targetRate) / 100;
  const discountDenominator = 1 - (instantDiscountRate + addDiscountRate) / 100;

  let discountedSellPrice = 0;
  if (discountedDenominator !== 0) {
    discountedSellPrice = -shippingNetProfit / discountedDenominator;
  }

  let sellPrice = 0;
  if (discountDenominator !== 0) {
    sellPrice = (discountedSellPrice + addFlatDiscount) / discountDenominator;
  }

  if (sellPriceInput) {
    sellPriceInput.value = formatNumber(Math.max(0, Math.round(sellPrice)));
  }
  calculateMargin();
};


moneyInputs.forEach((input: HTMLInputElement): void => {
  formatMoneyInput(input);
  input.addEventListener('input', (): void => {
    formatMoneyInput(input);
    calculateMargin();
  });
});

percentInputs.forEach((input: HTMLInputElement): void => {
  input.addEventListener('input', (): void => {
    formatPercentInput(input);
    calculateMargin();
  });
});

targetProfitInput?.addEventListener('input', (): void => {
  formatSignedMoneyInput(targetProfitInput);
  calculateSellPriceByTargetProfit(parseSignedMoneyValue(targetProfitInput.value));
});

targetMarginRateInput?.addEventListener('input', (): void => {
  formatPercentInput(targetMarginRateInput);
  calculateSellPriceByTargetRate(parsePercentValue(targetMarginRateInput.value));
});

displayPriceInput?.addEventListener('input', (): void => {
  formatMoneyInput(displayPriceInput);
  const displayPrice = getMoney(displayPriceInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);
  const denominator = 1 - instantDiscountRate / 100;

  if (sellPriceInput && denominator !== 0) {
    sellPriceInput.value = formatNumber(Math.round(displayPrice / denominator));
  }
  calculateMargin();
});

platformSelect?.addEventListener('change', (): void => {
  const option = platformSelect.options[platformSelect.selectedIndex];
  if (marketFeeInput) {
    marketFeeInput.value = option.dataset.market || '0';
  }
  if (shippingFeeRateInput) {
    shippingFeeRateInput.value = option.dataset.shipping || '0';
  }
  calculateMargin();
});

resetButton?.addEventListener('click', (): void => {
  moneyInputs.forEach((input: HTMLInputElement): void => {
    input.value = '';
  });
  percentInputs.forEach((input: HTMLInputElement): void => {
    input.value = '';
  });
  if (marketFeeInput) {
    marketFeeInput.value = document.body.dataset.defaultMarketFee || '0';
  }
  if (shippingFeeRateInput) {
    shippingFeeRateInput.value = document.body.dataset.defaultShippingFee || '0';
  }
  if (platformSelect) {
    platformSelect.value = '';
  }
  calculateMargin();
  sellPriceInput?.focus();
});

applyButton?.addEventListener('click', (): void => {
  const sellPrice = getMoney(sellPriceInput);
  if (sellPrice <= 0) {
    window.alert('판매가격을 0보다 크게 입력해 주세요.');
    return;
  }

  const message: MarginApplyMessage = {
    type: 'admin-margin-calc-apply',
    sellPrice,
    supplyPrice: getMoney(supplyPriceInput),
    shippingFee: getMoney(recvShippingInput),
    targetSelector,
    supplyTargetSelector,
    shippingTargetSelector,
  };
  window.parent.postMessage(message, window.location.origin);
});


calculateMargin();

export {};
