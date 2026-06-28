CREATE TABLE IF NOT EXISTS goods_restricted_words (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  word VARCHAR(100) NOT NULL COMMENT '원본 단어',
  normalized_word VARCHAR(100) NOT NULL COMMENT '검색 비교용 정규화 단어',
  word_type VARCHAR(20) NOT NULL COMMENT '단어 유형(PROHIBITED/ADULT)',
  target_scope VARCHAR(20) NOT NULL DEFAULT 'BOTH' COMMENT '검사 대상(NAME/MANUFACTURER/KEYWORD/NAME_KEYWORD/BOTH)',
  match_type VARCHAR(20) NOT NULL DEFAULT 'CONTAINS' COMMENT '매칭 방식(CONTAINS/EXACT/WORD)',
  is_active VARCHAR(1) NOT NULL DEFAULT 'Y' COMMENT '사용 여부(Y/N)',
  memo VARCHAR(255) DEFAULT NULL COMMENT '관리 메모',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL COMMENT '소프트삭제 일시',
  UNIQUE KEY uk_word_type_normalized_word (word_type, normalized_word),
  KEY idx_active_type_scope_id (is_active, word_type, target_scope, id),
  KEY idx_deleted_id (deleted_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상품 금지단어 관리';
