ALTER TABLE users
ADD COLUMN last_micropub_url varchar(255);
ALTER TABLE users
ADD COLUMN last_instagram_img_url varchar(255);
ALTER TABLE users
ADD COLUMN ig_public tinyint(4) not null default 0;
ALTER TABLE users
ADD COLUMN photo_count_this_week int(11) not null default 0;
