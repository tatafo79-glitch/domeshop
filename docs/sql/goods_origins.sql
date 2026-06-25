CREATE TABLE `goods_origins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nm` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '원산지명',
  `cd0` int DEFAULT NULL COMMENT '부모0 id',
  `cd1` int DEFAULT NULL COMMENT '부모1 id',
  `pathnm0` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '부모0 이름',
  `pathnm1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '부모1 이름',
  `level` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '뎁스',
  `sort` int unsigned NOT NULL DEFAULT '0' COMMENT '정렬',
  `last` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '마직막 여부',
  `create_at` int unsigned NOT NULL DEFAULT '0',
  `update_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `origincd` (`cd0`,`cd1`) USING BTREE,
  KEY `nm` (`nm`),
  KEY `sort` (`sort`),
  KEY `create_at` (`create_at`),
  KEY `update_at` (`update_at`)
) ENGINE=InnoDB AUTO_INCREMENT=50453 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='상품 원산지 테이블';
