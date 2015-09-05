ALTER TABLE users
ADD COLUMN last_micropub_url varchar(255);
ALTER TABLE users
ADD COLUMN last_instagram_img_url varchar(255);
ALTER TABLE users
ADD COLUMN ig_public tinyint(4) not null default 0;
