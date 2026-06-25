CREATE TABLE IF NOT EXISTS `member_vendor_code_sequences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'vendor_code 순번 식별자',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 공급사 코드 시퀀스 테이블';