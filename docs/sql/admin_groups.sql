CREATE TABLE IF NOT EXISTS `admin_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '그룹 고유 식별자',
  `name` varchar(50) NOT NULL COMMENT '관리자 그룹명',
  `description` varchar(255) DEFAULT NULL COMMENT '그룹 설명',
  `permissions` text DEFAULT NULL COMMENT '권한 범위 (JSON 형식: {"modules": {"goods": "rw", ...}, "special": {"deposit_adjust": true, ...}})',
  `level` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '권한 레벨 (숫자가 높을수록 고권한, 예: 99=최고관리자)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 그룹 테이블';
