<?php

set_time_limit(180);

class downloadOriginalController extends downloadController {

	private $id_job;
	private $password;
	private $fname;
	private $download_type;
	private $id_file;


	public function __construct() {

		$filterArgs = array(
				'filename'      => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_FLAG_STRIP_LOW
					),
				'id_file'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
				'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
				'download_type' => array(
					'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
					),
				'password'      => array(
					'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
					)
				);

		$__postInput = filter_var_array( $_REQUEST, $filterArgs );

		//NOTE: This is for debug purpose only,
		//NOTE: Global $_POST Overriding from CLI Test scripts
		//$__postInput = filter_var_array( $_POST, $filterArgs );

		$this->fname         = $__postInput[ 'filename' ];
		$this->id_file       = $__postInput[ 'id_file' ];
		$this->id_job        = $__postInput[ 'id_job' ];
		$this->download_type = $__postInput[ 'download_type' ];
		$this->password      = $__postInput[ 'password' ];

	}

	public function doAction() {

		//get storage object
		$fs= new FilesStorage();
		$files_job = $fs->getOriginalFilesForJob( $this->id_job, $this->id_file, $this->password );

		$output_content = array();
		foreach ( $files_job as $file ) {
			$id_file                                  = $file[ 'id_file' ];
			$output_content[ $id_file ][ 'filename' ] = $file[ 'filename' ];
			$output_content[ $id_file ][ 'contentPath' ] = $file[ 'originalFilePath' ];
		}

		if ( $this->download_type == 'all' ) {
			if ( count( $output_content ) > 1 ) {
				$this->_filename = $this->fname;
				$pathinfo       = pathinfo( $this->fname );
				if ( $pathinfo[ 'extension' ] != 'zip' ) {
					$this->_filename = $pathinfo[ 'basename' ] . ".zip";
				}
				$this->content = $this->composeZip( $output_content ); //add zip archive content here;
			} elseif ( count( $output_content ) == 1 ) {
				$this->setContent( $output_content );
			}
		} else {
			$this->setContent( $output_content );
		}
	}

	/**
	 * There is a foreach, but this should be always one element
	 *
	 * @param $output_content
	 */
	private function setContent( $output_content ) {
		foreach ( $output_content as $oc ) {
			$this->_filename = $oc[ 'filename' ];
			$this->content  = file_get_contents($oc[ 'contentPath' ]);
		}
	}

	private function composeZip( $output_content ) {
		$file = tempnam( "/tmp", "zipmatecat" );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::OVERWRITE );

		foreach ( $output_content as $f ) {
			$zip->addFile($f[ 'contentPath' ], $f[ 'filename' ]);
		}

		// Close and send to users
		$zip->close();
		$zip_content = file_get_contents($file);
		unlink( $file );

		return $zip_content;
	}

}
