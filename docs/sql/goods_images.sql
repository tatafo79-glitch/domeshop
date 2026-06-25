CREATE TABLE goods_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goods_id INT UNSIGNED NOT NULL COMMENT '상품 ID',
  image_url VARCHAR(255) NOT NULL COMMENT '이미지 경로 (S3 또는 로컬 경로)',
  sort_order TINYINT NOT NULL DEFAULT 0 COMMENT '이미지 노출 순서 (1번이 썸네일)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_goods_id (goods_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상품 전체 이미지(썸네일+추가이미지) 관리 테이블';
