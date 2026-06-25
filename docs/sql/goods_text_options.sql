CREATE TABLE goods_text_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goods_id INT UNSIGNED NOT NULL COMMENT '상품 ID',
  title VARCHAR(100) NOT NULL COMMENT '입력 안내 문구 (예: 각인할 문구를 적어주세요)',
  is_required VARCHAR(20) NOT NULL DEFAULT 'N' COMMENT '필수 입력 여부 (Y/N)',
  max_length INT NOT NULL DEFAULT 50 COMMENT '최대 입력 가능 글자 수',
  is_display VARCHAR(20) NOT NULL DEFAULT 'Y' COMMENT '노출 여부 (Y/N)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_goods_id (goods_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상품 텍스트 입력 옵션 테이블';
