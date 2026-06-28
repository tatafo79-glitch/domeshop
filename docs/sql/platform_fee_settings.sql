CREATE TABLE IF NOT EXISTS platform_fee_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform_name VARCHAR(50) NOT NULL COMMENT '플랫폼명',
  platform_code VARCHAR(50) NOT NULL COMMENT '플랫폼 코드',
  platform_fee_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '플랫폼 수수료율',
  shipping_fee_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '배송비 수수료율',
  instant_discount_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '즉시할인율',
  additional_discount_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '부가할인율',
  additional_fixed_discount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '부가정액할인',
  is_default CHAR(1) NOT NULL DEFAULT 'N' COMMENT '기본여부(Y/N)',
  memo VARCHAR(255) DEFAULT NULL COMMENT '메모',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '정렬순서',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL COMMENT '삭제일시',
  UNIQUE KEY uk_platform_code (platform_code),
  KEY idx_default (is_default),
  KEY idx_deleted_sort (deleted_at, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='플랫폼 수수료 설정';
