<?php

namespace FilesStorage;

interface IFilesStorage {
    public static function moveFileFromUploadSessionToQueuePath( $upload_session );
}
