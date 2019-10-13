ALTER TABLE photos
ADD COLUMN `date_imported` datetime DEFAULT NULL;

UPDATE photos SET date_imported = published WHERE date_imported IS NULL;
