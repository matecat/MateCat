USE matecat;

ALTER TABLE converters ADD stable TINYINT DEFAULT 1 NOT NULL AFTER conversion_api_version;