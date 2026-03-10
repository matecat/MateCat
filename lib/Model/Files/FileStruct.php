<?php

namespace Model\Files;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class FileStruct extends AbstractDaoSilentStruct implements IDaoStruct
{
    public int $id;
    public int $id_project;
    public string $filename;
    public string $source_language;
    public string $mime_type;
    public string $sha1_original_file;
    public bool $is_converted;

}
