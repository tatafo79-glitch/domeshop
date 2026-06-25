CREATE TABLE `uploaded_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `original_name` varchar(255) NOT NULL COMMENT '원본 파일명',
  `stored_name` varchar(255) NOT NULL COMMENT '난독화 저장 파일명',
  `file_path` varchar(500) NOT NULL COMMENT 'DB 저장 경로 (예: 2026/06/19/xxxx.jpg)',
  `file_size` int unsigned NOT NULL DEFAULT '0' COMMENT '파일 크기(bytes)',
  `mime_type` varchar(100) NOT NULL COMMENT 'MIME 타입',
  `category` varchar(50) NOT NULL COMMENT '용도 분류 (business-license, bank-book, goods 등)',
  `is_used` tinyint(1) NOT NULL DEFAULT '0' COMMENT '사용여부 (0:미사용/고아, 1:사용중)',
  `uploader_name` varchar(100) NOT NULL COMMENT '업로더 (상호명 또는 SYSTEM)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_uploader` (`uploader_name`),
  KEY `idx_is_used` (`is_used`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='업로드 파일 메타 관리';
