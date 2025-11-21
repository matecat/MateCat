<?php

namespace Utils\Constants;

class ConversionHandlerStatus
{

    const int ZIP_HANDLING  = 2;
    const int OK            = 1;
    const int NOT_CONVERTED = 0;

    // ERRORS
    const int INVALID_FILE                 = -1;
    const int NESTED_ZIP_FILES_NOT_ALLOWED = -2;
    const int SOURCE_ERROR                 = -3;
    const int TARGET_ERROR                 = -4;
    const int UPLOAD_ERROR                 = -6;
    const int MISCONFIGURATION             = -7;
    const int INVALID_TOKEN                = -19;
    const int OCR_WARNING                  = -20;
    const int OCR_ERROR                    = -21;
    const int INVALID_SEGMENTATION_RULE    = -22;
    const int GENERIC_ERROR                = -100;
    const int FILESYSTEM_ERROR             = -103;
    const int S3_ERROR                     = -230;

    const array warningCodes = [
            ConversionHandlerStatus::OCR_WARNING,
            ConversionHandlerStatus::ZIP_HANDLING,
    ];

    const array errorCodes = [
            ConversionHandlerStatus::INVALID_FILE,
            ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED,
            ConversionHandlerStatus::SOURCE_ERROR,
            ConversionHandlerStatus::TARGET_ERROR,
            ConversionHandlerStatus::UPLOAD_ERROR,
            ConversionHandlerStatus::MISCONFIGURATION,
            ConversionHandlerStatus::INVALID_TOKEN,
            ConversionHandlerStatus::INVALID_SEGMENTATION_RULE,
            ConversionHandlerStatus::OCR_ERROR,
            ConversionHandlerStatus::GENERIC_ERROR,
            ConversionHandlerStatus::FILESYSTEM_ERROR,
            ConversionHandlerStatus::S3_ERROR,
    ];

}