-- Create secure database and user for production.
-- IMPORTANT: Change the password before running.

CREATE DATABASE IF NOT EXISTS `khtwatos`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'khtwatos_user'@'localhost'
  IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, CREATE VIEW, SHOW VIEW, TRIGGER
  ON `khtwatos`.*
  TO 'khtwatos_user'@'localhost';

FLUSH PRIVILEGES;
