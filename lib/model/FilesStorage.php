<?
/*

files
	|_file id
		|_package
		|	|_manifest
		|	|_orig
		|	|	|_original file
		|	|_work
		|		|_xliff file
		|_orig
		|	|_original file
		|_xliff
			|_xliff file

cache
	|_sha1+lang
		|_package
			|_manifest
			|_orig
			|	|_original file
			|_work
				|_xliff file

*/
class FilesStorage{

	private $filesDir;
	private $cacheDir;

	public function __construct($files=false,$cache=false){
		//override default config
		if($files){
			$this->filesDir=$files;
		}else{
			$this->filesDir=INIT::$FILES_REPOSITORY;
		}
		if($cache){
			$this->cacheDir=$cache;
		}else{
			$this->cacheDir=INIT::$CACHE_REPOSITORY;
		}
	}

	public function getOriginalFromCache($hash,$lang){
		//compose path
		$path=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig";

		//return file
		$filePath=$this->getSingleFileInPath($path);

		//an unconverted xliff is never stored in orig dir; look for it in xliff dir
		if(!$filePath){
			$filePath=$this->getXliffFromCache($hash,$lang);
		}

		return $filePath;
	}

	public function getOriginalFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."orig";

		//return file
		$filePath=$this->getSingleFileInPath($path);

		//an unconverted xliff is never stored in orig dir; look for it in xliff dir
		if(!$filePath){
			$filePath=$this->getXliffFromFileDir($id);
		}

		return $filePath;
	}

	public function getXliffFromCache($hash,$lang){
		//compose path
		$path=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work";

		//return file
		return $this->getSingleFileInPath($path);
	}

	public function getXliffFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."xliff";

		//return file
		return $this->getSingleFileInPath($path);
	}

	public function makeCachePackage($hash, $lang, $originalPath=false, $xliffPath){
		//ensure old stuff is overwritten
		if(is_dir($this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang)){
			shell_exec("rm -fr ".$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang);
		}

		//create cache dir structure
		$cacheDir=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package";
		mkdir($cacheDir,0755,true);
		mkdir($cacheDir.DIRECTORY_SEPARATOR."orig");
		mkdir($cacheDir.DIRECTORY_SEPARATOR."work");

		//if it's not an xliff as original
		if(!$originalPath){
			//set original moval as successful
			$outcome1=true;

			//use original xliff
			$xliffDestination=$cacheDir.DIRECTORY_SEPARATOR."work".DIRECTORY_SEPARATOR.basename($xliffPath);
		}else{
			//move original
			$outcome1=rename($originalPath,$cacheDir.DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($originalPath));

			//set naming for converted xliff
			$xliffDestination=$cacheDir.DIRECTORY_SEPARATOR."work".DIRECTORY_SEPARATOR.basename($originalPath).'.xlf';
		}

		//move converted xliff
		$outcome2=rename($xliffPath, $xliffDestination);

		return $outcome1 and $outcome2;
	}

	public function linkSessionToCache($hash, $lang, $uid){
		//get upload dir
		$dir=INIT::$UPLOAD_REPOSITORY.DIRECTORY_SEPARATOR.$uid;

		//create a file in it, named after cache position on storage
		return touch($dir.DIRECTORY_SEPARATOR.$hash."|".$lang);
	}

	public function moveFromCacheToFileDir($hash,$lang,$idFile){

		//destination dir
		$fileDir=$this->filesDir.DIRECTORY_SEPARATOR.$idFile;
		$cacheDir=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package";

		//check if doesn't exist
		if(!is_dir($fileDir)){
			//make files' directory structure
			mkdir($fileDir);
			mkdir($fileDir.DIRECTORY_SEPARATOR."package");
			mkdir($fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig");
			mkdir($fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work");
			mkdir($fileDir.DIRECTORY_SEPARATOR."orig");
			mkdir($fileDir.DIRECTORY_SEPARATOR."xliff");
		}

		//make links from cache to files
		//BUG: this stuff may not work if FILES and CACHES are on different filesystems
		//FIX: we could check in advance and, in case, use copy instead of links

		//check if manifest from a LongHorn conversion exists
		$manifestFile=$cacheDir.DIRECTORY_SEPARATOR."manifest.rkm";
		if(file_exists($manifestFile)) $longhorn=true;

		if($longhorn){
			link($manifestFile, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR.basename($manifestFile));
		}

		//orig
		$filePath=$this->getSingleFileInPath($cacheDir.DIRECTORY_SEPARATOR."orig");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($filePath));

		if($longhorn){
			link($filePath, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($filePath));
		}

		//work
		$filePath=$this->getSingleFileInPath($cacheDir.DIRECTORY_SEPARATOR."work");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."xliff".DIRECTORY_SEPARATOR.basename($filePath));

		if($longhorn){
			link($filePath, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work".DIRECTORY_SEPARATOR.basename($filePath));
		}
	}

	public function getSingleFileInPath($path){
		//check if it actually exist
		if(is_dir($path)){
			//get files in dir
			$files=scandir($path);

			//init file name dir
			$filename=false;

			//scan dir for file
			foreach($files as $file){
				//skip dir pointers
				if('.'==$file or '..'==$file) continue;

				//get the remaining file (it's the only file in dir)
				$filename=$file;

				//no need to loop anymore
				break;
			}

			if(!$filename){
				//file not found (dir was empty)
				$filePath=false;
			}else{
				//compose path
				$filePath=$path.DIRECTORY_SEPARATOR.$filename;
			}
		}else{
			//non existent dir
			$filePath=false;
		}

		return $filePath;
	}


	public function getOriginalFilesForJob($id_job, $id_file, $password){
		$where_id_file = "";
		if ( !empty( $id_file ) ) {
			$where_id_file = " and fj.id_file=$id_file";
		}
		$query = "select fj.id_file, f.filename, j.source from files_job fj
			inner join files f on f.id=fj.id_file
			inner join jobs j on j.id=fj.id_job
			where fj.id_job=$id_job $where_id_file and j.password='$password'";

		$db      = Database::obtain();
		$results = $db->fetch_array( $query );

		foreach($results as $k=>$result){
			//try fetching from files dir
			$filePath=$this->getOriginalFromFileDir($result['id_file']);

			if(!$filePath){
				//file is on the database; let's copy it to disk to make it compliant to file-on-disk structure
				//this moves both original and xliff
				$this->migrateFileDB2FS($result['id_file'], $result['filename'], $result['source']);

				//now, try again fetching from disk :)
				$filePath=$this->getOriginalFromFileDir($result['id_file']);
			}

			$results[$k]['originalFilePath']=$filePath;
		}

		return $results;
	}

	public function getFilesForJob( $id_job, $id_file ) {
		$where_id_file = "";

		if ( !empty( $id_file ) ) {
			$where_id_file = " and id_file=$id_file";
		}

		$query = "select fj.id_file, f.filename, j.source from files_job fj
			inner join files f on f.id=fj.id_file
			join jobs as j on j.id=fj.id_job
			where fj.id_job = $id_job $where_id_file";

		$db      = Database::obtain();
		$results = $db->fetch_array( $query );

		foreach($results as $k=>$result){
			//try fetching from files dir
			$originalPath=$this->getOriginalFromFileDir($result['id_file']);

			if(!$originalPath){
				//file is on the database; let's copy it to disk to make it compliant to file-on-disk structure
				//this moves both original and xliff
				$this->migrateFileDB2FS($result['id_file'], $result['filename'], $result['source']);

				//now, try again fetching from disk :)
				$originalPath=$this->getOriginalFromFileDir($result['id_file']);
			}

			$results[$k]['originalFilePath']=$originalPath;

			//note that we trust this to succeed on first try since, at this stage, we already built the file package
			$results[$k]['xliffFilePath']=$this->getXliffFromFileDir($result['id_file']);
		}

		return $results;
	}

	private function migrateFileDB2FS($id_file, $filename, $source_lang){
		//create temporary storage to place stuff
		$tempdir="/tmp".DIRECTORY_SEPARATOR.str_shuffle(sha1(time()));
		mkdir($tempdir,0755);

		//fetch xliff from the files database
		$xliffContent=$this->getXliffFromDB($id_file);

		//try pulling the original content too (if it's empty it means that it was an unconverted xliff)
		$fileContent=$this->getOriginalFromDB($id_file);

		if(!empty($fileContent)){
			//it's a converted file
			//create temporary file with appropriately modified name
			$tempXliff = $tempdir.DIRECTORY_SEPARATOR.$filename.".xlf";

			//create file
			$tempOriginal = $tempdir.DIRECTORY_SEPARATOR.$filename;

			//flush content
			file_put_contents($tempOriginal, $fileContent);

			//get hash, based on original
			$sha1=sha1($fileContent);

			//free memory
			unset($fileContent);
		}else{
			//if it's a unconverted xliff
			//create temporary file with original name
			$tempXliff = $tempdir.DIRECTORY_SEPARATOR.$filename;

			// set original to empty
			$tempOriginal=false;

			//get hash
			$sha1=sha1($xliffContent);
		}

		//flush xliff file content
		file_put_contents($tempXliff, $xliffContent);

		//free memory
		unset($xliffContent);

		//build a cache package
		$this->makeCachePackage($sha1, $source_lang, $tempOriginal, $tempXliff);

		//build a file package
		$this->moveFromCacheToFileDir($sha1, $source_lang, $id_file);

		//clean temporary stuff
		Utils::deleteDir($tempdir);
	}

	public function getOriginalFromDB($id_file){
		$query = "select original_file from files where id= $id_file";

		$db      = Database::obtain();
		$results = $db->fetch_array( $query );

		return gzinflate($results[0]['original_file']);

	}

	public function getXliffFromDB($id_file){
		$query = "select xliff_file from files where id= $id_file";

		$db      = Database::obtain();
		$results = $db->fetch_array( $query );

		return $results[0]['xliff_file'];
	}
}

?>
