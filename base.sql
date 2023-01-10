
CREATE TABLE IF NOT EXISTS `@id` (
  `@id` INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `@type` INT NOT NULL,
  `uri` VARCHAR(512) NOT NULL UNIQUE,
  FOREIGN KEY (`@type`) REFERENCES `@id`(`@id`)
);

INSERT IGNORE INTO `@id` (`@id`,`@type`,`uri`) VALUES (1, 1, '@id');

