CREATE TABLE goods_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goods_id INT UNSIGNED NOT NULL COMMENT '상품 ID',
  file_path VARCHAR(500) NOT NULL COMMENT 'uploaded_files.file_path',
  image_type VARCHAR(20) NOT NULL DEFAULT 'LIST' COMMENT '이미지 유형(LIST/DETAIL)',
  is_main VARCHAR(20) NOT NULL DEFAULT 'N' COMMENT '대표 이미지 여부(Y/N)',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '정렬 순서',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL COMMENT '소프트삭제 일시',
  KEY idx_goods_images_goods (goods_id, image_type, deleted_at, sort_order),
  KEY idx_goods_images_main (goods_id, is_main, deleted_at),
  KEY idx_goods_images_file_path (file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상품 이미지 매핑 테이블';
