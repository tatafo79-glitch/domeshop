CREATE TABLE `banks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '은행 고유 식별자',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '은행명',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '사용 여부',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '노출 정렬 순서',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 일시',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_banks_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='은행 목록 정보';
