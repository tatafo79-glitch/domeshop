CREATE TABLE goods_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goods_id INT UNSIGNED NOT NULL COMMENT '상품 ID',
  option_val1 VARCHAR(100) NOT NULL COMMENT '옵션값 1 (예: 빨강)',
  option_val2 VARCHAR(100) DEFAULT NULL COMMENT '옵션값 2 (예: XL)',
  option_supply_price INT NOT NULL DEFAULT 0 COMMENT '옵션 추가 공급가 (+/-)',
  option_sell_price INT NOT NULL DEFAULT 0 COMMENT '옵션 추가 판매가 (+/-)',
  option_compliance_price INT NOT NULL DEFAULT 0 COMMENT '옵션 추가 준수가격 (+/-)',
  stock INT NOT NULL DEFAULT 0 COMMENT '해당 옵션의 개별 재고',
  soldout TINYINT NOT NULL DEFAULT 0 COMMENT '옵션 개별 품절 여부 (0: 판매중, 1: 품절)',
  is_display VARCHAR(20) NOT NULL DEFAULT 'Y' COMMENT '옵션 노출 여부 (Y/N)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_goods_display_soldout (goods_id, is_display, soldout),
  KEY idx_soldout (soldout)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상품 옵션 조합(SKU) 테이블';
