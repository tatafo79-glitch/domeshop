CREATE TABLE `member_admin_memos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 식별자',
  `member_id` int unsigned NOT NULL COMMENT '대상 회원의 고유 ID (members.id 참조)',
  `admin_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '메모를 작성한 관리자 이름 (명칭만 저장)',
  `memo_content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '메모 내용',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성 일시',
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='관리자 전용 회원 메모 타임라인';
