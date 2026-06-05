-- PixelForge Booster System Migration
-- Creates user_boosters table and seeds starter boosters for existing users

CREATE TABLE IF NOT EXISTS `user_boosters` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `booster_type` ENUM('hint','hammer','shuffle','extraMoves','colorBurst','lightning') NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_booster` (`user_id`, `booster_type`),
  CONSTRAINT `fk_user_boosters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed starter boosters for existing users based on their level
INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'hint', LEAST(5, GREATEST(0, FLOOR(u.level / 1)))
FROM `users` u
WHERE u.level >= 1
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'hammer', LEAST(5, GREATEST(0, FLOOR(u.level / 2)))
FROM `users` u
WHERE u.level >= 2
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'shuffle', LEAST(3, GREATEST(0, FLOOR(u.level / 3)))
FROM `users` u
WHERE u.level >= 3
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'extraMoves', LEAST(2, GREATEST(0, FLOOR(u.level / 4)))
FROM `users` u
WHERE u.level >= 4
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'colorBurst', LEAST(1, FLOOR(u.level / 8))
FROM `users` u
WHERE u.level >= 5
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

INSERT INTO `user_boosters` (`user_id`, `booster_type`, `quantity`)
SELECT u.id, 'lightning', LEAST(1, FLOOR(u.level / 12))
FROM `users` u
WHERE u.level >= 10
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);
