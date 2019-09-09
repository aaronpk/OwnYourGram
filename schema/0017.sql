ALTER TABLE users
    ADD COLUMN `next_poll_date` datetime DEFAULT NULL AFTER `date_created`;

UPDATE users
SET next_poll_date = last_poll_date;
