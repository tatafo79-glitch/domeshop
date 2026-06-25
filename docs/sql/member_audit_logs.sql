CREATE TABLE `member_audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 식별자',
  `member_id` int unsigned NOT NULL COMMENT '대상 회원의 고유 ID (members.id 참조)',
  `modifier_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '수정한 주체의 이름 (예: 관리자명, 혹은 본인)',
  `action_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UPDATE' COMMENT '액션 종류 (UPDATE, PASSWORD_RESET, LOGIN 등)',
  `changed_data` json DEFAULT NULL COMMENT '변경 이력 상세 (old, new 값이 포함된 JSON)',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '액션을 수행한 사용자의 IP 주소 (IPv4/IPv6 호환)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '수정/실행 일시',
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 정보 수정 및 활동 추적 로그';
