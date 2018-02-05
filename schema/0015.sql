ALTER TABLE users
    ADD COLUMN `add_tags` text DEFAULT NULL AFTER `blacklist`;
