CREATE TABLE `data_type_edtf` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `resource_id` INT NOT NULL,
    `property_id` INT NOT NULL,
    `value_min_date` BIGINT NOT NULL,
    `value_min_time` INT NOT NULL,
    `value_max_date` BIGINT NOT NULL,
    `value_max_time` INT NOT NULL,
    INDEX idx_resource (`resource_id`),
    INDEX idx_property (`property_id`),
    INDEX idx_property_value_min (`property_id`, `value_min_date`, `value_min_time`),
    INDEX idx_property_value_max (`property_id`, `value_max_date`, `value_max_time`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

ALTER TABLE `data_type_edtf` ADD CONSTRAINT fk_edtf_resource FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
ALTER TABLE `data_type_edtf` ADD CONSTRAINT fk_edtf_property FOREIGN KEY (`property_id`) REFERENCES `property` (`id`) ON DELETE CASCADE;
