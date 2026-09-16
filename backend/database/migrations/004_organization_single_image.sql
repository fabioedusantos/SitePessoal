ALTER TABLE organizations
    CHANGE COLUMN showcase_image_url image_url VARCHAR(500) NULL;

ALTER TABLE organizations
    DROP COLUMN logo_url;
