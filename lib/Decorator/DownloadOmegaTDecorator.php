<?php

use AbstractControllers\IController;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/10/17
 * Time: 11.58
 *
 */


class DownloadOmegaTDecorator extends AbstractDecorator {

    /**
     * @var downloadController
     */
    protected $controller;

    public function decorate() {

        $output_content = [];

        //set the file Name
        $pathinfo = AbstractFilesStorage::pathinfo_fix( $this->controller->getDefaultFileName( $this->controller->getProject() ) );
        $this->controller->setFilename( $pathinfo[ 'filename' ] . "_" . $this->controller->getJob()->target . "." . $pathinfo[ 'extension' ] );


        if ( $pathinfo[ 'extension' ] != 'zip' ) {
            $this->controller->setFilename( $this->controller->getFilename() . ".zip" );
        }

        $tmsService = new TMSService();
        $tmsService->setOutputType( 'tm' );

        /**
         * @var $tmFile SplTempFileObject
         */
        $tmFile = $tmsService->exportJobAsTMX(
                $this->controller->id_job,
                $this->controller->password,
                $this->controller->getJob()->source,
                $this->controller->getJob()->target,
                $this->controller->getUser()->uid
        );

        $tmsService->setOutputType( 'mt' );

        /**
         * @var $mtFile SplTempFileObject
         */
        $mtFile = $tmsService->exportJobAsTMX( $this->controller->id_job, $this->controller->password, $this->controller->getJob()->source, $this->controller->getJob()->target );

        $tm_id                    = uniqid( 'tm' );
        $mt_id                    = uniqid( 'mt' );
        $output_content[ $tm_id ] = [
                'document_content' => '',
                'output_filename'  => $pathinfo[ 'filename' ] . "_" . $this->controller->getJob()->target . "_TM . tmx"
        ];

        foreach ( $tmFile as $lineNumber => $content ) {
            $output_content[ $tm_id ][ 'document_content' ] .= $content;
        }

        $output_content[ $mt_id ] = [
                'document_content' => '',
                'output_filename'  => $pathinfo[ 'filename' ] . "_" . $this->controller->getJob()->target . "_MT . tmx"
        ];

        foreach ( $mtFile as $lineNumber => $content ) {
            $output_content[ $mt_id ][ 'document_content' ] .= $content;
        }

        return $output_content;

    }

    public function createOmegaTZip( $output_content ) {

        $file = tempnam( "/tmp", "zipmatecat" );

        $zip = new ZipArchive();
        $zip->open( $file, ZipArchive::OVERWRITE );

        $zip_baseDir   = $this->controller->getJob()->id . "/";
        $zip_fileDir   = $zip_baseDir . "inbox/";
        $zip_tm_mt_Dir = $zip_baseDir . "tm/";

        $a[] = $zip->addEmptyDir( $zip_baseDir );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "glossary" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "inbox" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "omegat" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "target" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "terminology" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "tm" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "tm/auto" );

        $rev_index_name = [];

        // Staff with content
        foreach ( $output_content as $key => $f ) {

            $f[ 'output_filename' ] = downloadController::forceOcrExtension( $f[ 'output_filename' ] );

            //Php Zip bug, utf-8 not supported
            $fName = preg_replace( '/[^0-9a-zA-Z_\.\-]/u', "_", $f[ 'output_filename' ] );
            $fName = preg_replace( '/[_]{2,}/', "_", $fName );
            $fName = str_replace( '_.', ".", $fName );
            $fName = str_replace( '._', ".", $fName );
            $fName = str_replace( ".out.sdlxliff", ".sdlxliff", $fName );

            $nFinfo = AbstractFilesStorage::pathinfo_fix( $fName );
            $_name  = $nFinfo[ 'filename' ];
            if ( strlen( $_name ) < 3 ) {
                $fName = substr( uniqid(), -5 ) . "_" . $fName;
            }

            if ( array_key_exists( $fName, $rev_index_name ) ) {
                $fName = uniqid() . $fName;
            }

            $rev_index_name[ $fName ] = $fName;

            if ( substr( $key, 0, 2 ) == 'tm' || substr( $key, 0, 2 ) == 'mt' ) {
                $path = $zip_tm_mt_Dir;
            } else {
                $path = $zip_fileDir;
            }

            $zip->addFromString( $path . $fName, $f[ 'document_content' ] );

        }

        $zip_prjFile = $this->getOmegatProjectFile( $this->controller->getJob()->source, $this->controller->getJob()->target );
        $zip->addFromString( $zip_baseDir . "omegat.project", $zip_prjFile );

        // Close and send to users
        $zip->close();
        $zip_content                  = new ZipContentObject();
        $zip_content->input_filename  = $file;

        $this->controller->setOutputContent( $zip_content );

    }

    private function getOmegatProjectFile( $source, $target ) {
        $source           = strtoupper( $source );
        $target           = strtoupper( $target );
        $defaultTokenizer = "LuceneEnglishTokenizer";

        $omegatFile = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
			<omegat>
			<project version="1.0">
			<source_dir>inbox</source_dir>
			<source_dir_excludes>
			<mask>**/.svn/**</mask>
			<mask>**/CSV/**</mask>
			<mask>**/.cvs/**</mask>
			<mask>**/desktop.ini</mask>
			<mask>**/Thumbs.db</mask>
			</source_dir_excludes>
			<target_dir>__DEFAULT__</target_dir>
			<tm_dir>__DEFAULT__</tm_dir>
			<glossary_dir>terminology</glossary_dir>
			<glossary_file>terminology/new-glossary.txt</glossary_file>
			<dictionary_dir>__DEFAULT__</dictionary_dir>
			<source_lang>@@@SOURCE@@@</source_lang>
			<target_lang>@@@TARGET@@@</target_lang>
			<source_tok>org.omegat.tokenizer.@@@TOK_SOURCE@@@</source_tok>
			<target_tok>org.omegat.tokenizer.@@@TOK_TARGET@@@</target_tok>
			<sentence_seg>false</sentence_seg>
			<support_default_translations>true</support_default_translations>
			<remove_tags>false</remove_tags>
			</project>
			</omegat>';

        $omegatTokenizerMap = [
                "AR" => "LuceneArabicTokenizer",
                "HY" => "LuceneArmenianTokenizer",
                "EU" => "LuceneBasqueTokenizer",
                "BG" => "LuceneBulgarianTokenizer",
                "CA" => "LuceneCatalanTokenizer",
                "ZH" => "LuceneSmartChineseTokenizer",
                "CZ" => "LuceneCzechTokenizer",
                "DK" => "LuceneDanishTokenizer",
                "NL" => "LuceneDutchTokenizer",
                "EN" => "LuceneEnglishTokenizer",
                "FI" => "LuceneFinnishTokenizer",
                "FR" => "LuceneFrenchTokenizer",
                "GL" => "LuceneGalicianTokenizer",
                "DE" => "LuceneGermanTokenizer",
                "GR" => "LuceneGreekTokenizer",
                "IN" => "LuceneHindiTokenizer",
                "HU" => "LuceneHungarianTokenizer",
                "ID" => "LuceneIndonesianTokenizer",
                "IE" => "LuceneIrishTokenizer",
                "IT" => "LuceneItalianTokenizer",
                "JA" => "LuceneJapaneseTokenizer",
                "KO" => "LuceneKoreanTokenizer",
                "LV" => "LuceneLatvianTokenizer",
                "NO" => "LuceneNorwegianTokenizer",
                "FA" => "LucenePersianTokenizer",
                "PT" => "LucenePortugueseTokenizer",
                "RO" => "LuceneRomanianTokenizer",
                "RU" => "LuceneRussianTokenizer",
                "ES" => "LuceneSpanishTokenizer",
                "SE" => "LuceneSwedishTokenizer",
                "TH" => "LuceneThaiTokenizer",
                "TR" => "LuceneTurkishTokenizer"

        ];

        $source_lang     = substr( $source, 0, 2 );
        $target_lang     = substr( $target, 0, 2 );
        $sourceTokenizer = $omegatTokenizerMap[ $source_lang ];
        $targetTokenizer = $omegatTokenizerMap[ $target_lang ];

        if ( $sourceTokenizer == null ) {
            $sourceTokenizer = $defaultTokenizer;
        }
        if ( $targetTokenizer == null ) {
            $targetTokenizer = $defaultTokenizer;
        }

        return str_replace(
                [ "@@@SOURCE@@@", "@@@TARGET@@@", "@@@TOK_SOURCE@@@", "@@@TOK_TARGET@@@" ],
                [ $source, $target, $sourceTokenizer, $targetTokenizer ],
                $omegatFile );


    }

}