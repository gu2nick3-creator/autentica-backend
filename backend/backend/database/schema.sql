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

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  sku VARCHAR(100) NULL,
  short_description TEXT NULL,
  description LONGTEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(10,2) NULL,
  category VARCHAR(120) NULL,
  product_type ENUM('roupa','sapato') NOT NULL DEFAULT 'roupa',
  sizes_json JSON NOT NULL,
  colors_json JSON NOT NULL,
  images_json JSON NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  on_sale TINYINT(1) NOT NULL DEFAULT 0,
  best_seller TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  review_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS coupons (
  id VARCHAR(36) PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  min_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_uses INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
  id VARCHAR(40) PRIMARY KEY,
  status ENUM('pending','paid','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  coupon_code VARCHAR(80) NULL,
  payment_method VARCHAR(80) NOT NULL DEFAULT 'infinitepay',
  payment_tag VARCHAR(120) NULL,
  customer_id VARCHAR(36) NULL,
  customer_json JSON NOT NULL,
  address_json JSON NOT NULL,
  items_json JSON NOT NULL,
  tracking_code VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_orders_status (status),
  INDEX idx_orders_customer_id (customer_id),
  INDEX idx_orders_created_at (created_at)
);
