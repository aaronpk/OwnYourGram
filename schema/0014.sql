ALTER TABLE users
    ADD COLUMN `last_poll_date` datetime DEFAULT NULL AFTER `date_created`;
