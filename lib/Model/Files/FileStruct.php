<?php
namespace Model\Files;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentDao;
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
        return ( new SegmentDao() )->countByFile( $this );
    }

    /**
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getTranslations() {
        $dao = new Translations_SegmentTranslationDao();

        return $dao->getByFile( $this );
    }

    /**
     * @param \Model\Jobs\JobStruct $chunk
     *
     * @return array
     */
    public function getMaxMinSegmentBoundariesForChunk( JobStruct $chunk ) {
        return ( new FilesJobDao() )->getSegmentBoundariesForChunk( $this, $chunk );
    }

}
