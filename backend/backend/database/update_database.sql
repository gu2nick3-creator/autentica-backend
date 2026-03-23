CREATE TABLE IF NOT EXISTS customers (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  cpf VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  addresses_json JSON NOT NULL,
  total_spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_customers_created_at (created_at)
);

ALTER TABLE products MODIFY sku VARCHAR(100) NULL;

ALTER TABLE orders
  ADD COLUMN customer_id VARCHAR(36) NULL AFTER payment_tag,
  ADD INDEX idx_orders_customer_id (customer_id);
