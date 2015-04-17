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
	|_sha1+ lang
		|_package
			|_manifest
			|_orig
			|	|_original file
			|_work
				|_xliff file

*/
class FilesStorage{

	private $filesDir;
	private $packsDir;

	public function __construct($files=false,$packs=false){
		//override default config
		if($files){
			$this->filesDir=$files;
		}else{
			$this->filesDir=INIT::$FILES_REPOSITORY;
		}
		if($packs){
			$this->packsDir=$packs;
		}else{
			$this->packsDir=INIT::$PACK_REPOSITORY;
		}
	}

	public function getOriginalFromCache($hash,$lang){
		//compose path
		$path=$this->packsDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig";

		//return file
		return $this->getFileFromPath($path);
	}

	public function getOriginalFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."orig";

		//return file
		return $this->getFileFromPath($path);
	}

	public function getXliffFromCache($hash,$lang){
		//compose path
		$path=$this->packsDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work";

		//return file
		return $this->getFileFromPath($path);
	}

	public function getXliffFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."xliff";

		//return file
		return $this->getFileFromPath($path);
	}

	public function moveFromCacheToFileDir($hash,$lang,$idFile){
		//destination dir
		$fileDir=$this->filesDir.DIRECTORY_SEPARATOR.$idFile;
		$packDir=$this->packsDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package";

		//check if doesn't exist
		if(!is_dir($fileDir)){
			//make files' directory structure
			mkdir($destPath);
			mkdir($destPath.DIRECTORY_SEPARATOR."package");
			mkdir($destPath.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig");
			mkdir($destPath.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work");
			mkdir($destPath.DIRECTORY_SEPARATOR."orig");
			mkdir($destPath.DIRECTORY_SEPARATOR."xliff");
		}

		//make links from cache to files
		//BUG: this stuff may not work if FILES and PACKS are on different filesystems
		//FIX: we could check in advance and, in case, use copy instead of links

		//orig
		$filePath=$this->getFileFromPath($packDir.DIRECTORY_SEPARATOR."orig");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig".basename($filePath));
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."orig".basename($filePath));

		//work
		$filePath=$this->getFileFromPath($packDir.DIRECTORY_SEPARATOR."work");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."xliff".basename($filePath));

		//manifest
		$manifestFile=$packDir.DIRECTORY_SEPARATOR."manifest.rkm";
		link($manifestFile, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR.basename($manifestFile));
	}

	private function getFileFromPath($path){
		//check if it actually exist
		if(is_dir($path)){
			//get filename
			$files=scandir($path);
			foreach($files as $file){
				//skip dir pointers
				if('.'==$file or '..'==$file) continue;

				//get the remaining file (it's the only file in dir)
				$filename=$file;

				//no need to loop anymore
				break;
			}

			//compose path
			$filePath=$path.DIRECTORY_SEPARATOR.$filename;
		}else{
			$filePath=false;
		}

		return $filePath;
	}
}

?>
