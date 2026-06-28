-- 기존 환경에서 admin_settings 테이블을 이미 생성했다면 아래 구문을 먼저 실행합니다.
-- RENAME TABLE admin_settings TO settings;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_group VARCHAR(50) NOT NULL COMMENT '설정 그룹(goods_register 등)',
  setting_key VARCHAR(100) NOT NULL COMMENT '설정 키',
  setting_value TEXT NOT NULL COMMENT '설정 값',
  value_type VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '값 타입(string/int/float/bool/json)',
  description VARCHAR(255) DEFAULT NULL COMMENT '설정 설명',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_setting_group_key (setting_group, setting_key),
  KEY idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='전역 운영 설정';

INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description) VALUES
('goods_register', 'pricing_method', 'SUPPLY_PRICE', 'string', '기본 판매가 산정방식'),
('goods_register', 'margin_rate', '20', 'int', '기본 마진율'),
('goods_register', 'rounding_unit', '10', 'int', '반올림 단위'),
('goods_register', 'rounding_type', 'ROUND', 'string', '반올림 처리 방식'),
('goods_register', 'block_under_supply_price', 'Y', 'string', '가격 보호'),
('goods_register', 'default_shipping_type', 'PAID', 'string', '기본 배송 정책'),
('goods_register', 'shipping_fee', '2500', 'int', '노출 배송비'),
('goods_register', 'actual_shipping_fee', '2500', 'int', '실제 배송비'),
('goods_register', 'shipping_qty_limit', '1', 'int', '합포장 기준 수량'),
('goods_register', 'max_image_count', '10', 'int', '이미지 등록 수'),
('goods_register', 'max_option_count', '100', 'int', '옵션 등록 수'),
('goods_register', 'max_text_option_count', '20', 'int', '텍스트 옵션 등록 수'),
('goods_register', 'extra_shipping_jeju', '0', 'int', '제주 추가 배송비'),
('goods_register', 'extra_shipping_island', '0', 'int', '도서산간 추가 배송비'),
('goods_register', 'return_shipping_fee', '2500', 'int', '반품 배송비'),
('goods_register', 'exchange_shipping_fee', '5000', 'int', '교환 배송비')
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value),
  value_type = VALUES(value_type),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;
