/* Add the multi_photo column with the default set to 1 */
ALTER TABLE users
    ADD COLUMN `multi_photo` tinyint(4) NOT NULL DEFAULT '1';
/* Set all existing users to not support multi-photos */
UPDATE users SET multi_photo = 0;

ALTER TABLE photos
    ADD COLUMN instagram_img_list text DEFAULT NULL after instagram_img;
