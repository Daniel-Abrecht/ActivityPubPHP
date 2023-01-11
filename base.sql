
CREATE TABLE IF NOT EXISTS `id` (
  `id` INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `uri` VARCHAR(512) NOT NULL UNIQUE,
  `deleted` DATETIME
);
INSERT IGNORE INTO `id` (`id`,`uri`) VALUES (1, 'id');

CREATE TABLE IF NOT EXISTS `version` (
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id` INT NOT NULL,
  `type` INT NOT NULL,
  PRIMARY KEY (`created`, `id`),
  FOREIGN KEY (`id`) REFERENCES `id`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`type`) REFERENCES `id`(`id`)
);

/* INSERT IGNORE INTO `id` (`id`,`type`,`uri`) VALUES (1, 1, 'id'); */

