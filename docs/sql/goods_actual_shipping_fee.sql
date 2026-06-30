ALTER TABLE goods
  ADD COLUMN actual_shipping_fee INT NOT NULL DEFAULT 0 COMMENT '실제 배송비' AFTER shipping_fee;
