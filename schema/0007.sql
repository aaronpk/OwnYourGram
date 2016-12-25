ALTER TABLE photos
ADD COLUMN processed TINYINT(4) NOT NULL DEFAULT 0;

UPDATE photos SET processed = 1;
