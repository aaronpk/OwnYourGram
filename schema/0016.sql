ALTER TABLE users
    ADD COLUMN `media_endpoint` varchar(255) DEFAULT NULL AFTER `micropub_endpoint`;
