CREATE DATABASE IF NOT EXISTS car_rental_secure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE car_rental_secure;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS password_reset_requests;
DROP TABLE IF EXISTS maintenance;
DROP TABLE IF EXISTS rentals;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS users;

CREATE TABLE users(
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','MANAGER','RENTAL_STAFF') NOT NULL,
  status ENUM('ACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  failed_attempts INT NOT NULL DEFAULT 0,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_reset_requests(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('PENDING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_by INT NULL,
  processed_at DATETIME NULL,
  INDEX idx_reset_request_user(user_id),
  INDEX idx_reset_request_status(status),
  CONSTRAINT fk_reset_request_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reset_request_admin FOREIGN KEY(processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cars(
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(20) NOT NULL UNIQUE,
  make VARCHAR(50) NOT NULL,
  model VARCHAR(50) NOT NULL,
  purchase_date DATE NOT NULL,
  purchase_price DECIMAL(12,2) NOT NULL CHECK (purchase_price >= 0),
  road_tax_expiry DATE NOT NULL,
  insurance_expiry DATE NOT NULL,
  daily_rate DECIMAL(10,2) NOT NULL CHECK (daily_rate > 0),
  status ENUM('IDLE','RENTED','MAINTENANCE') NOT NULL DEFAULT 'IDLE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers(
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  mobile VARCHAR(30) NOT NULL,
  email VARCHAR(254) NOT NULL,
  address VARCHAR(255) NULL,
  licence_last4 CHAR(4) NOT NULL,
  licence_expiry DATE NOT NULL,
  licence_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer_email(email)
) ENGINE=InnoDB;

CREATE TABLE rentals(
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_id INT NOT NULL,
  customer_id INT NOT NULL,
  rental_date DATE NOT NULL,
  return_date DATE NOT NULL,
  actual_return_date DATE NULL,
  standard_amount DECIMAL(10,2) NOT NULL CHECK (standard_amount >= 0),
  amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (discount_percent >= 0),
  approval_status ENUM('NOT_REQUIRED','PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'NOT_REQUIRED',
  requested_by INT NOT NULL,
  approved_by INT NULL,
  approval_note VARCHAR(255) NULL,
  approved_at DATETIME NULL,
  status ENUM('PENDING_APPROVAL','ACTIVE','COMPLETED','REJECTED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rental_car FOREIGN KEY(car_id) REFERENCES cars(id),
  CONSTRAINT fk_rental_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
  CONSTRAINT fk_rental_requested_by FOREIGN KEY(requested_by) REFERENCES users(id),
  CONSTRAINT fk_rental_approved_by FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE maintenance(
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  cost DECIMAL(10,2) NOT NULL CHECK (cost >= 0),
  start_date DATE NOT NULL,
  completion_date DATE NULL,
  status ENUM('OPEN','COMPLETED') NOT NULL DEFAULT 'OPEN',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_car FOREIGN KEY(car_id) REFERENCES cars(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  event_type VARCHAR(60) NOT NULL,
  target VARCHAR(120) NULL,
  result VARCHAR(20) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event(event_type),
  CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO cars(reg_no,make,model,purchase_date,purchase_price,road_tax_expiry,insurance_expiry,daily_rate,status) VALUES
('JRV1001','Toyota','Vios','2022-03-01',78000,'2027-03-01','2027-03-01',150,'IDLE'),
('JRV1002','Honda','City','2023-05-11',82000,'2027-05-11','2027-05-11',160,'IDLE'),
('JRV1003','Perodua','Myvi','2022-07-20',56000,'2027-07-20','2027-07-20',110,'IDLE'),
('JRV1004','Perodua','Bezza','2021-02-10',48000,'2027-02-10','2027-02-10',100,'MAINTENANCE'),
('JRV1005','Proton','X50','2024-01-18',98000,'2027-01-18','2027-01-18',220,'IDLE'),
('JRV1006','Toyota','Corolla Cross','2024-03-12',135000,'2027-03-12','2027-03-12',260,'IDLE'),
('JRV1007','Honda','HR-V','2023-06-01',145000,'2027-06-01','2027-06-01',280,'IDLE'),
('JRV1008','Perodua','Alza','2022-09-09',75000,'2027-09-09','2027-09-09',180,'IDLE'),
('JRV1009','Toyota','Innova','2021-11-15',125000,'2027-11-15','2027-11-15',240,'IDLE'),
('JRV1010','Proton','Saga','2022-04-22',46000,'2027-04-22','2027-04-22',95,'IDLE');

-- Five synthetic maintenance records for coursework demonstration.
-- Four are completed and one is OPEN. JRV1004 is therefore correctly in MAINTENANCE state.
INSERT INTO maintenance(car_id,description,cost,start_date,completion_date,status) VALUES
((SELECT id FROM cars WHERE reg_no='JRV1001'),'Scheduled engine oil and filter service',185.00,'2026-01-12','2026-01-12','COMPLETED'),
((SELECT id FROM cars WHERE reg_no='JRV1002'),'Front brake pad replacement and inspection',420.00,'2026-02-18','2026-02-19','COMPLETED'),
((SELECT id FROM cars WHERE reg_no='JRV1005'),'Tyre replacement and wheel alignment',760.00,'2026-03-22','2026-03-22','COMPLETED'),
((SELECT id FROM cars WHERE reg_no='JRV1009'),'Battery replacement and electrical system check',295.00,'2026-07-14','2026-07-14','COMPLETED'),
((SELECT id FROM cars WHERE reg_no='JRV1004'),'Engine diagnostic and cooling-system repair',540.00,'2026-09-15',NULL,'OPEN');

