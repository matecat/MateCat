<?php
namespace Files;
use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;
use Jobs_JobStruct;
use Segments_SegmentDao;
use Translations_SegmentTranslationDao;
use Translations_SegmentTranslationStruct;

class FileStruct extends AbstractDaoSilentStruct implements IDaoStruct {
    public $id;
    public $id_project;
    public $filename;
    public $source_language;
    public $mime_type;
    public $sha1_original_file;
    public $is_converted;

    public function getSegmentsCount() {
        return ( new Segments_SegmentDao() )->countByFile( $this );
    }

    /**
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao();

        return $dao->getByFile( $this );
    }

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return array
     */
    public function getMaxMinSegmentBoundariesForChunk( Jobs_JobStruct $chunk ) {
        return ( new FilesJobDao() )->getSegmentBoundariesForChunk( $this, $chunk );
    }

}
