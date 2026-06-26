ALTER TABLE `uploaded_files`
  ADD KEY `idx_file_category_used` (`file_path`,`category`,`is_used`);

ALTER TABLE `member_assets_history`
  ADD KEY `idx_asset_created_id` (`asset_type`,`created_at`,`id`);

ALTER TABLE `members`
  ADD KEY `idx_role_status_approval_id` (`role`,`status`,`approval_status`,`id`),
  ADD KEY `idx_created_id` (`created_at`,`id`),
  ADD KEY `idx_last_login_id` (`last_login_at`,`id`),
  ADD KEY `idx_deposit_id` (`deposit`,`id`),
  ADD KEY `idx_mileage_id` (`mileage`,`id`);
