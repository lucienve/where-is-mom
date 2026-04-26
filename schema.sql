-- Project: Where is Mom
-- Description: Core database schema

CREATE TABLE `app_state` (
    `state_key` VARCHAR(50) NOT NULL,
    `state_value` VARCHAR(255) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert the default state for the mode toggle (1 = On Ship/AIS Mode, 0 = On Land/Photo Mode)
INSERT INTO `app_state` (`state_key`, `state_value`) VALUES ('tracking_mode_on_ship', '1');

CREATE TABLE `locations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source` ENUM('ais', 'photo') NOT NULL,
    `timestamp` DATETIME NOT NULL,
    `coordinates` POINT NOT NULL SRID 4326,
    `image_path` VARCHAR(512) DEFAULT NULL,
    
    -- Extended AIS Payload Fields
    `speed` DECIMAL(5, 2) DEFAULT NULL,
    `course` DECIMAL(5, 2) DEFAULT NULL,
    `heading` INT DEFAULT NULL,
    `destination` VARCHAR(255) DEFAULT NULL,
    `eta` DATETIME DEFAULT NULL,
    `draught` DECIMAL(4, 2) DEFAULT NULL,
    `navigational_status` VARCHAR(100) DEFAULT NULL,
    `raw_api_response` JSON DEFAULT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
