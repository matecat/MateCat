<?php

namespace Constants;

class ConversionHandlerStatus {

    const ZIP_HANDLING                 = 2;
    const OK                           = 1;
    const NOT_CONVERTED                = 0;

    // ERRORS
    const INVALID_FILE                 = -1;
    const NESTED_ZIP_FILES_NOT_ALLOWED = -2;
    const SOURCE_ERROR                 = -3;
    const TARGET_ERROR                 = -4;
    const UPLOAD_ERROR                 = -6;
    const MISCONFIGURATION             = -7;
    const INVALID_TOKEN                = -19;
    const OCR_WARNING                  = -20;
    const OCR_ERROR                    = -21;
    const GENERIC_ERROR                = -100;
    const FILESYSTEM_ERROR             = -103;
    const S3_ERROR                     = -230;

}