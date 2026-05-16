<?php

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/03/26
 * Time: 19:18
 *
 */
class AlterTableSegmentMetadataAddUniqueIndex
{
    public array $sql_up = [
        '
        ALTER TABLE `segment_metadata`
            DROP INDEX `idx_id_segment_meta_key`,
            ADD UNIQUE INDEX `idx_id_segment_meta_key` (`id_segment`, `meta_key`);
    '
    ];

    public array $sql_down = [
        '
        ALTER TABLE `segment_metadata`
            DROP INDEX `idx_id_segment_meta_key`,
            ADD INDEX `idx_id_segment_meta_key` (`id_segment`, `meta_key`);
    '
    ];
}