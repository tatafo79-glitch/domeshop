CREATE TABLE `admin_excel_download_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 식별자',
  `admin_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '관리자 아이디',
  `admin_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '관리자 이름',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '요청 IP 주소 (IPv4/IPv6)',
  `download_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '다운로드 종류 (MEMBER_LIST 등)',
  `target_menu` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '대상 메뉴 (회원목록 등)',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SUCCESS' COMMENT '상태 (SUCCESS, BLOCKED, FAILED)',
  `fail_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '실패/차단 사유',
  `filter_summary` json DEFAULT NULL COMMENT '다운로드 필터 조건 상세',
  `total_count` int unsigned NOT NULL DEFAULT '0' COMMENT '총 다운로드 건수',
  `download_reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '다운로드 사유',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '다운로드 일시',
  PRIMARY KEY (`id`),
  KEY `idx_admin_ip_status_created` (`admin_id`, `ip_address`, `status`, `created_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 엑셀 다운로드 감사 로그';
