CREATE TABLE IF NOT EXISTS `admin_group_audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 식별자',
  `admin_id` int unsigned NOT NULL COMMENT '행위자 관리자 ID (members.id)',
  `admin_user_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '행위자 로그인 아이디 스냅샷',
  `admin_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '행위자 이름',
  `group_id` int unsigned DEFAULT NULL COMMENT '대상 그룹 ID (admin_groups.id)',
  `action_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '액션 종류 (CREATE, UPDATE, DELETE)',
  `diff_data` json DEFAULT NULL COMMENT '변경 내역 (before, after)',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '행위자 IP',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  PRIMARY KEY (`id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `chk_admin_id_positive` CHECK (`admin_id` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 그룹 권한 변경 감사 로그';
