DELIMITER $$
DROP PROCEDURE IF EXISTS matecat.explode_splits $$
CREATE PROCEDURE explode_splits(id_job BIGINT)

  BEGIN

    DECLARE id_segment BIGINT DEFAULT 0;
    DECLARE value TEXT;
    DECLARE occurance INT DEFAULT 0;
    DECLARE i INT DEFAULT 0;
    DECLARE splitted_value VARCHAR(50);
    DECLARE done INT DEFAULT 0;
    DECLARE bound CHAR(1) DEFAULT ',';

    DECLARE cur1 CURSOR FOR
      SELECT
        segment_translations_splits.id_segment,
        SUBSTRING_INDEX(
          segment_translations_splits.target_chunk_lengths,
          ':',
          -1
        ) as value
      FROM segment_translations_splits
      WHERE segment_translations_splits.id_job = id_job ;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS temp_splits;

    CREATE TEMPORARY TABLE temp_splits(
      `id` INT NOT NULL,
      `id_segment` BIGINT NOT NULL,
      `value` VARCHAR(255) NOT NULL
    ) ENGINE=Memory;

    OPEN cur1;
      read_loop: LOOP
        FETCH cur1 INTO id_segment, value;
        IF done THEN
          LEAVE read_loop;
        END IF;

        SET occurance = (SELECT LENGTH(value)
                                 - LENGTH(REPLACE(value, bound, ''))
                                 +1);
        SET i=1;

        WHILE i <= occurance DO

          SET splitted_value = (
            SELECT REPLACE(
              SUBSTRING(
                SUBSTRING_INDEX(value, bound, i),
                LENGTH(
                  SUBSTRING_INDEX(value, bound, i - 1)) + 1
              ), ',', ''
            )
          );

          SET splitted_value = REPLACE( splitted_value, '}', '');
          SET splitted_value = REPLACE( splitted_value, ']', '');
          SET splitted_value = REPLACE( splitted_value, '[', '');
          SET splitted_value = REPLACE( splitted_value, '"', '');

          INSERT INTO temp_splits VALUES (i, id_segment, splitted_value);
          SET i = i + 1;

        END WHILE;
      END LOOP;

    CLOSE cur1;
  END; $$
