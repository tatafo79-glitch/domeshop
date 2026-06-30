interface MarginApplyMessage {
  type: 'admin-margin-calc-apply';
  sellPrice: number;
  supplyPrice: number;
  shippingFee: number;
  actualShippingFee: number;
  shippingQtyLimit?: number;
  targetSelector: string;
  supplyTargetSelector: string;
  shippingTargetSelector: string;
  actualShippingTargetSelector: string;
  shippingQtyLimitTargetSelector: string;
}


const targetSelector = new URLSearchParams(window.location.search).get('target') || '#sell_price';
const supplyTargetSelector = new URLSearchParams(window.location.search).get('supply_target') || '#supply_price';
const shippingTargetSelector = new URLSearchParams(window.location.search).get('shipping_target') || '#shipping_fee';
const actualShippingTargetSelector = new URLSearchParams(window.location.search).get('actual_shipping_target') || '#actual_shipping_fee';
const shippingQtyLimitTargetSelector = new URLSearchParams(window.location.search).get('shipping_qty_limit_target') || '#shipping_qty_limit';

const getInput = (id: string): HTMLInputElement | null => document.getElementById(id) as HTMLInputElement | null;
const getSelect = (id: string): HTMLSelectElement | null => document.getElementById(id) as HTMLSelectElement | null;
const getNode = (id: string): HTMLElement | null => document.getElementById(id);

const sellPriceInput = getInput('mcSellPrice');
const recvShippingInput = getInput('mcRecvShipping');
const supplyPriceInput = getInput('mcSupplyPrice');
const sentShippingInput = getInput('mcSentShipping');
const shippingDiffMarginInput = getInput('mcShippingDiffMargin');
const packageQtyLimitInput = getInput('mcPackageQtyLimit');
const purchaseQtyInput = getInput('mcPurchaseQty');
const marketFeeInput = getInput('mcMarketFee');
const shippingFeeRateInput = getInput('mcShippingFee');
const addDiscountRateInput = getInput('mcAddDiscountRate');
const addFlatDiscountInput = getInput('mcAddFlatDiscount');
const instantDiscountRateInput = getInput('mcInstantDiscountRate');
const displayPriceInput = getInput('mcDisplayPriceInput');
const settlementNode = getNode('mcSettlement');
const resultProfitNode = getNode('mcResultProfit');
const resultMarginRateNode = getNode('mcResultMarginRate');
const formulaListNode = document.getElementById('mcFormulaList') as HTMLUListElement | null;
const targetProfitInput = getInput('mcProfit');
const targetMarginRateInput = getInput('mcMarginRate');
const resetButton = document.getElementById('mcResetBtn') as HTMLButtonElement | null;
const applyButton = document.getElementById('mcApplyBtn') as HTMLButtonElement | null;
const platformSelect = getSelect('mcPlatformSelect');
let targetMode: 'profit' | 'rate' | null = null;

const moneyInputs: HTMLInputElement[] = [
  sellPriceInput,
  recvShippingInput,
  supplyPriceInput,
  sentShippingInput,
  addFlatDiscountInput,
].filter((input: HTMLInputElement | null): input is HTMLInputElement => input !== null);

const quantityInputs: HTMLInputElement[] = [
  packageQtyLimitInput,
  purchaseQtyInput,
].filter((input: HTMLInputElement | null): input is HTMLInputElement => input !== null);

const percentInputs: HTMLInputElement[] = [
  marketFeeInput,
  shippingFeeRateInput,
  addDiscountRateInput,
  instantDiscountRateInput,
].filter((input: HTMLInputElement | null): input is HTMLInputElement => input !== null);

const formatNumber = (value: number): string => Math.round(value).toLocaleString('ko-KR');
const formatPercent = (value: number): string => value.toFixed(2).replace(/\.?0+$/, '');

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
const getQuantity = (input: HTMLInputElement | null): number => Math.max(1, parseMoneyValue(input?.value ?? ''));
const getShippingCount = (): number => Math.max(1, Math.ceil(getQuantity(purchaseQtyInput) / getQuantity(packageQtyLimitInput)));
const isShippingDiffIncluded = (): boolean => shippingDiffMarginInput?.checked ?? false;
const calculateShippingNetProfit = (
  recvShipping: number,
  sentShipping: number,
  shippingCount: number,
  shippingFeeRate: number,
): number => (recvShipping * shippingCount * (1 - shippingFeeRate / 100)) - (sentShipping * shippingCount);

const setFormulaLines = (lines: string[]): void => {
  if (!formulaListNode) {
    return;
  }

  formulaListNode.replaceChildren();
  lines.forEach((line: string): void => {
    const item = document.createElement('li');
    item.textContent = line;
    formulaListNode.append(item);
  });
};

const calculateMargin = (): void => {
  const sellPrice = getMoney(sellPriceInput);
  const recvShipping = getMoney(recvShippingInput);
  const supplyPrice = getMoney(supplyPriceInput);
  const sentShipping = getMoney(sentShippingInput);
  const purchaseQty = getQuantity(purchaseQtyInput);
  const shippingCount = getShippingCount();
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
  const totalDiscountedSellPrice = discountedSellPrice * purchaseQty;
  const shippingNetProfit = calculateShippingNetProfit(recvShipping, sentShipping, shippingCount, shippingFeeRate);
  const purchaseCost = supplyPrice * purchaseQty;
  const feeAmount = Math.floor(totalDiscountedSellPrice * (marketFeeRate / 100));
  const totalReceived = totalDiscountedSellPrice;
  const totalCost = purchaseCost;
  const settlement = totalReceived - feeAmount;
  const profit = settlement - totalCost + shippingNetProfit;
  const marginRate = totalDiscountedSellPrice > 0 ? (profit / totalDiscountedSellPrice) * 100 : 0;

  if (displayPriceInput && document.activeElement !== displayPriceInput) {
    displayPriceInput.value = formatNumber(displayPrice);
  }
  if (settlementNode) {
    settlementNode.textContent = formatNumber(settlement);
  }
  if (resultProfitNode) {
    resultProfitNode.textContent = formatNumber(profit);
  }
  if (resultMarginRateNode) {
    resultMarginRateNode.textContent = marginRate.toFixed(1);
  }
  setFormulaLines([
    `할인 후 판매가: ${formatNumber(sellPrice)} - 즉시/부가 할인 ${formatNumber(discountAmount)} = ${formatNumber(discountedSellPrice)}원`,
    `상품 매출: ${formatNumber(discountedSellPrice)} × 구매수량 ${formatNumber(purchaseQty)} = ${formatNumber(totalDiscountedSellPrice)}원`,
    `플랫폼 수수료: ${formatNumber(totalDiscountedSellPrice)} × ${formatPercent(marketFeeRate)}% = ${formatNumber(feeAmount)}원`,
    `정산금액: ${formatNumber(totalReceived)} - ${formatNumber(feeAmount)} = ${formatNumber(settlement)}원`,
    `매입금액: ${formatNumber(supplyPrice)} × ${formatNumber(purchaseQty)} = ${formatNumber(purchaseCost)}원`,
    `배송비 차액: (${formatNumber(recvShipping)} × ${formatNumber(shippingCount)} × (1 - ${formatPercent(shippingFeeRate)}%)) - (${formatNumber(sentShipping)} × ${formatNumber(shippingCount)}) = ${formatNumber(shippingNetProfit)}원`,
    `판매이익: ${formatNumber(settlement)} - ${formatNumber(totalCost)} + ${formatNumber(shippingNetProfit)} = ${formatNumber(profit)}원`,
    `마진율: ${formatNumber(profit)} ÷ ${formatNumber(totalDiscountedSellPrice)} × 100 = ${marginRate.toFixed(1)}%`,
  ]);
};

const calculateSellPriceByTargetProfit = (targetProfit: number): void => {
  const recvShipping = getMoney(recvShippingInput);
  const supplyPrice = getMoney(supplyPriceInput);
  const sentShipping = getMoney(sentShippingInput);
  const purchaseQty = getQuantity(purchaseQtyInput);
  const shippingCount = getShippingCount();
  const shippingDiffIncluded = isShippingDiffIncluded();
  const marketFeeRate = getPercent(marketFeeInput);
  const shippingFeeRate = getPercent(shippingFeeRateInput);
  const addDiscountRate = getPercent(addDiscountRateInput);
  const addFlatDiscount = getMoney(addFlatDiscountInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);
  const shippingNetProfit = shippingDiffIncluded ? calculateShippingNetProfit(recvShipping, sentShipping, shippingCount, shippingFeeRate) : 0;
  const purchaseCost = supplyPrice * purchaseQty;
  const marketDenominator = 1 - marketFeeRate / 100;
  const discountDenominator = 1 - (instantDiscountRate + addDiscountRate) / 100;

  let discountedSellPrice = 0;
  if (marketDenominator !== 0 && purchaseQty > 0) {
    discountedSellPrice = (targetProfit - shippingNetProfit + purchaseCost) / (marketDenominator * purchaseQty);
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
  const purchaseQty = getQuantity(purchaseQtyInput);
  const shippingCount = getShippingCount();
  const shippingDiffIncluded = isShippingDiffIncluded();
  const marketFeeRate = getPercent(marketFeeInput);
  const shippingFeeRate = getPercent(shippingFeeRateInput);
  const addDiscountRate = getPercent(addDiscountRateInput);
  const addFlatDiscount = getMoney(addFlatDiscountInput);
  const instantDiscountRate = getPercent(instantDiscountRateInput);
  const shippingNetProfit = shippingDiffIncluded ? calculateShippingNetProfit(recvShipping, sentShipping, shippingCount, shippingFeeRate) : 0;
  const purchaseCost = supplyPrice * purchaseQty;
  const discountedDenominator = 1 - (marketFeeRate + targetRate) / 100;
  const discountDenominator = 1 - (instantDiscountRate + addDiscountRate) / 100;

  let discountedSellPrice = 0;
  if (discountedDenominator !== 0 && purchaseQty > 0) {
    discountedSellPrice = (purchaseCost - shippingNetProfit) / (discountedDenominator * purchaseQty);
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
    if (input === sellPriceInput) {
      targetMode = null;
      calculateMargin();
      return;
    }

    recalculateByTargetMode();
  });
});

quantityInputs.forEach((input: HTMLInputElement): void => {
  formatMoneyInput(input);
  input.addEventListener('input', (): void => {
    formatMoneyInput(input);
    calculateMargin();
  });
});

percentInputs.forEach((input: HTMLInputElement): void => {
  input.addEventListener('input', (): void => {
    formatPercentInput(input);
    recalculateByTargetMode();
  });
});

const recalculateByTargetMode = (): void => {
  if (targetMode === 'profit' && targetProfitInput) {
    calculateSellPriceByTargetProfit(parseSignedMoneyValue(targetProfitInput.value));
    return;
  }

  if (targetMode === 'rate' && targetMarginRateInput) {
    calculateSellPriceByTargetRate(parsePercentValue(targetMarginRateInput.value));
    return;
  }

  calculateMargin();
};

shippingDiffMarginInput?.addEventListener('change', recalculateByTargetMode);

const applyTargetProfit = (): void => {
  if (!targetProfitInput) {
    return;
  }

  targetMode = 'profit';
  formatSignedMoneyInput(targetProfitInput);
  if (targetMarginRateInput) {
    targetMarginRateInput.value = '0';
  }
  calculateSellPriceByTargetProfit(parseSignedMoneyValue(targetProfitInput.value));
};

const applyTargetRate = (): void => {
  if (!targetMarginRateInput) {
    return;
  }

  targetMode = 'rate';
  formatPercentInput(targetMarginRateInput);
  if (targetProfitInput) {
    targetProfitInput.value = '0';
  }
  calculateSellPriceByTargetRate(parsePercentValue(targetMarginRateInput.value));
};

const handleTargetEnter = (event: KeyboardEvent): void => {
  if (event.key !== 'Enter') {
    return;
  }

  event.preventDefault();
  if (event.currentTarget === targetProfitInput) {
    applyTargetProfit();
    return;
  }

  if (event.currentTarget === targetMarginRateInput) {
    applyTargetRate();
  }
};

targetProfitInput?.addEventListener('input', applyTargetProfit);
targetProfitInput?.addEventListener('change', applyTargetProfit);
targetProfitInput?.addEventListener('keydown', handleTargetEnter);

targetMarginRateInput?.addEventListener('input', applyTargetRate);
targetMarginRateInput?.addEventListener('change', applyTargetRate);
targetMarginRateInput?.addEventListener('keydown', handleTargetEnter);

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

const setInputValue = (input: HTMLInputElement | null, value: string): void => {
  if (input) {
    input.value = value;
  }
};

const applySelectedPlatform = (): void => {
  if (!platformSelect) {
    return;
  }

  const option = platformSelect.options[platformSelect.selectedIndex];
  setInputValue(marketFeeInput, option.dataset.market || '0');
  setInputValue(shippingFeeRateInput, option.dataset.shipping || '0');
  setInputValue(instantDiscountRateInput, option.dataset.instant || '0');
  setInputValue(addDiscountRateInput, option.dataset.add || '0');
  setInputValue(addFlatDiscountInput, option.dataset.flat || '0');
  if (addFlatDiscountInput) {
    formatMoneyInput(addFlatDiscountInput);
  }
  calculateMargin();
};

platformSelect?.addEventListener('change', applySelectedPlatform);

resetButton?.addEventListener('click', (): void => {
  targetMode = null;
  moneyInputs.forEach((input: HTMLInputElement): void => {
    input.value = '';
  });
  quantityInputs.forEach((input: HTMLInputElement): void => {
    input.value = '1';
  });
  percentInputs.forEach((input: HTMLInputElement): void => {
    input.value = '';
  });
  if (shippingDiffMarginInput) {
    shippingDiffMarginInput.checked = false;
  }
  if (marketFeeInput) {
    marketFeeInput.value = document.body.dataset.defaultMarketFee || '0';
  }
  if (shippingFeeRateInput) {
    shippingFeeRateInput.value = document.body.dataset.defaultShippingFee || '0';
  }
  if (platformSelect) {
    platformSelect.value = document.body.dataset.defaultPlatformCode || '';
    applySelectedPlatform();
  } else {
    calculateMargin();
  }
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
    actualShippingFee: getMoney(sentShippingInput),
    shippingQtyLimit: getQuantity(packageQtyLimitInput),
    targetSelector,
    supplyTargetSelector,
    shippingTargetSelector,
    actualShippingTargetSelector,
    shippingQtyLimitTargetSelector,
  };
  window.parent.postMessage(message, window.location.origin);
});


applySelectedPlatform();
calculateMargin();

export {};
