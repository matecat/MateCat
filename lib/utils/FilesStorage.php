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
		return $this->getSingleFileInPath($path);
	}

	public function getOriginalFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."orig";

		//return file
		return $this->getSingleFileInPath($path);
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
