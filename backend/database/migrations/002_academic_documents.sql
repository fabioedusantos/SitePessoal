ALTER TABLE education
    ADD COLUMN certificate_url VARCHAR(500) NULL AFTER logo_url;

ALTER TABLE evaluation_boards
    ADD COLUMN logo_url VARCHAR(500) NULL AFTER institution;
