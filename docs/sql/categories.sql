CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED DEFAULT NULL COMMENT '부모 카테고리 ID (최상위는 NULL)',
  name VARCHAR(100) NOT NULL COMMENT '카테고리명',
  path VARCHAR(255) NOT NULL COMMENT '계층형 경로 (카테고리 라이크 검색용, 예: /1/2/5/)',
  depth TINYINT NOT NULL COMMENT '카테고리 단계 (1~4)',
  is_leaf VARCHAR(20) NOT NULL DEFAULT 'Y' COMMENT '마지막(최하위) 카테고리 여부 (Y/N)',
  sort_order INT NOT NULL DEFAULT 0 COMMENT '진열 순서',
  is_active VARCHAR(20) NOT NULL DEFAULT 'Y' COMMENT '노출 여부 (Y/N)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_parent_id (parent_id),
  KEY idx_path (path),
  KEY idx_depth (depth)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='카테고리 정보 테이블';
