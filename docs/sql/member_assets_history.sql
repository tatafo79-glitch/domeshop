CREATE TABLE `member_assets_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 식별자',
  `member_id` int unsigned NOT NULL COMMENT '회원 ID (members.id 참조)',
  `asset_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '자산 구분 (DEPOSIT: 적립금, POINT: 포인트)',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '변동 사유',
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '주문번호 (주문과 연동된 변동일 경우)',
  `change_amount` int NOT NULL COMMENT '변동 금액 (양수: 지급/적립, 음수: 차감/사용)',
  `balance_after` int NOT NULL COMMENT '변동 후 잔액 (무결성 추적 및 대조용)',
  `actor_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '실행 주체명 (예: 관리자명, 구매자 상호명, 벤더사 상호명, 혹은 시스템)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '발생 일시',
  PRIMARY KEY (`id`),
  KEY `idx_member_asset` (`member_id`,`asset_type`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 적립금 및 포인트 통합 변동 내역';
