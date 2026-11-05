-- Schema for Bovorn Service V2
-- Database: bovornserv_v2_db

CREATE DATABASE IF NOT EXISTS `bovornserv_v2_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bovornserv_v2_db`;

-- 1. Branch table
CREATE TABLE IF NOT EXISTS `branch` (
  `id_branch` tinyint(1) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id_branch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Employee type (Roles)
CREATE TABLE IF NOT EXISTS `emp_type` (
  `id_emp_type` smallint(2) NOT NULL AUTO_INCREMENT,
  `emp_type_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id_emp_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Employee table
CREATE TABLE IF NOT EXISTS `employee` (
  `id_emp` mediumint(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `addr` varchar(200) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL, -- Increased length for bcrypt hash
  `id_emp_type` smallint(2) NOT NULL,
  `id_branch` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_emp`),
  KEY `fk_emp_branch` (`id_branch`),
  KEY `fk_emp_type` (`id_emp_type`),
  CONSTRAINT `fk_emp_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch` (`id_branch`) ON DELETE CASCADE,
  CONSTRAINT `fk_emp_type` FOREIGN KEY (`id_emp_type`) REFERENCES `emp_type` (`id_emp_type`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Car type table
CREATE TABLE IF NOT EXISTS `car_type` (
  `id_car_type` smallint(2) NOT NULL AUTO_INCREMENT,
  `car_type_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id_car_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Province table
CREATE TABLE IF NOT EXISTS `province` (
  `PROVINCE_ID` int(5) NOT NULL AUTO_INCREMENT,
  `PROVINCE_NAME` varchar(150) NOT NULL,
  PRIMARY KEY (`PROVINCE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Unified Payments table (Combined payment and payment2)
CREATE TABLE IF NOT EXISTS `payment` (
  `id_pay` int(10) NOT NULL AUTO_INCREMENT,
  `id_emp` mediumint(5) NOT NULL,
  `id_branch` tinyint(1) NOT NULL,
  `cus_name` varchar(100) NOT NULL,
  `cus_addr` varchar(200) NOT NULL,
  `cus_phone` varchar(15) NOT NULL,
  `car_regis_id` varchar(20) NOT NULL,
  `id_car_type` smallint(2) NOT NULL,
  `province_id` int(5) NOT NULL,
  `serv_1` double NOT NULL DEFAULT 0,
  `serv_2` double NOT NULL DEFAULT 0,
  `serv_2_1` varchar(100) NOT NULL DEFAULT '',
  `serv_3` double NOT NULL DEFAULT 0,
  `serv_4` double NOT NULL DEFAULT 0,
  `serv_4_1` smallint(3) NOT NULL DEFAULT 0,
  `serv_4_2` varchar(50) NOT NULL DEFAULT '',
  `serv_5` double NOT NULL DEFAULT 0,
  `serv_6` double NOT NULL DEFAULT 0,
  `serv_7` double NOT NULL DEFAULT 0,
  `serv_7_1` varchar(150) NOT NULL DEFAULT '',
  `pay_sum` double NOT NULL DEFAULT 0,
  `discount` double NOT NULL DEFAULT 0,
  `pay_total` double NOT NULL DEFAULT 0,
  `pay_status` tinyint(1) NOT NULL DEFAULT 1, -- 1 = Draft, 2 = Completed, 3 = Cancelled
  `pay_type` tinyint(1) NOT NULL DEFAULT 1, -- 1 = Cash, 2 = Transfer
  `pay_day` smallint(2) NOT NULL,
  `pay_month` smallint(2) NOT NULL,
  `pay_year` mediumint(5) NOT NULL,
  `pay_time` varchar(50) NOT NULL,
  PRIMARY KEY (`id_pay`),
  KEY `fk_pay_emp` (`id_emp`),
  KEY `fk_pay_branch` (`id_branch`),
  KEY `fk_pay_car_type` (`id_car_type`),
  KEY `fk_pay_province` (`province_id`),
  CONSTRAINT `fk_pay_branch` FOREIGN KEY (`id_branch`) REFERENCES `branch` (`id_branch`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_car_type` FOREIGN KEY (`id_car_type`) REFERENCES `car_type` (`id_car_type`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_emp` FOREIGN KEY (`id_emp`) REFERENCES `employee` (`id_emp`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`PROVINCE_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
