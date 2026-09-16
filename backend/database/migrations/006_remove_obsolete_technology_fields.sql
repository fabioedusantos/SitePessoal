SET @technology_level_check = (
    SELECT tc.CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS tc
    JOIN information_schema.CHECK_CONSTRAINTS cc
      ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
     AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
      AND tc.TABLE_NAME = 'technologies'
      AND tc.CONSTRAINT_TYPE = 'CHECK'
      AND cc.CHECK_CLAUSE LIKE '%legacy_level%'
    LIMIT 1
);

SET @drop_technology_level_check = IF(
    @technology_level_check IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE technologies DROP CONSTRAINT `', REPLACE(@technology_level_check, '`', '``'), '`')
);
PREPARE drop_technology_level_check_stmt FROM @drop_technology_level_check;
EXECUTE drop_technology_level_check_stmt;
DEALLOCATE PREPARE drop_technology_level_check_stmt;

ALTER TABLE technologies
    DROP COLUMN IF EXISTS legacy_level,
    DROP COLUMN IF EXISTS first_used_year,
    DROP COLUMN IF EXISTS last_used_year;
