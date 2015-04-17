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
		$path=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."work";

		//return file
		return $this->getFileFromPath($path);
	}

	public function getXliffFromFileDir($id){
		//compose path
		$path=$this->filesDir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR."xliff";

		//return file
		return $this->getFileFromPath($path);
	}

	public function makeCachePackage($hash, $lang, $originalPath, $xliffPath){
		//ensure old stuff is overwritten
		if(is_dir($this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang)){
			shell_exec("rm -fr ".$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang);
		}

		//create cache dir structure
		$cacheDir=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package";
		mkdir($cacheDir,0755,true);
		mkdir($cacheDir.DIRECTORY_SEPARATOR."orig");
		mkdir($cacheDir.DIRECTORY_SEPARATOR."work");

		//move original
		$outcome1=rename($originalPath,$cacheDir.DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($originalPath));

		//move converted xliff
		$outcome2=rename($xliffPath,$cacheDir.DIRECTORY_SEPARATOR."work".DIRECTORY_SEPARATOR.basename($originalPath).'.xlf');

		return $outcome1 and $outcome2;
	}

	public function linkSessionToCache($hash, $lang, $uid){
			//get upload dir
			$dir=INIT::$UPLOAD_REPOSITORY.DIRECTORY_SEPARATOR.$uid;

			//create a file in it, named after cache position on storage
			return touch($dir.DIRECTORY_SEPARATOR.$hash."|".$lang);
	}

	public function moveFromCacheToFileDir($hash,$lang,$idFile){

		log::doLog("$hash,$lang,$idFile");
		//destination dir
		$fileDir=$this->filesDir.DIRECTORY_SEPARATOR.$idFile;
		$cacheDir=$this->cacheDir.DIRECTORY_SEPARATOR.$hash."|".$lang.DIRECTORY_SEPARATOR."package";

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
		//BUG: this stuff may not work if FILES and CACHES are on different filesystems
		//FIX: we could check in advance and, in case, use copy instead of links

		//orig
		$filePath=$this->getFileFromPath($cacheDir.DIRECTORY_SEPARATOR."orig");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."package".DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($filePath));
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."orig".DIRECTORY_SEPARATOR.basename($filePath));

		//work
		$filePath=$this->getFileFromPath($cacheDir.DIRECTORY_SEPARATOR."work");
		link($filePath, $fileDir.DIRECTORY_SEPARATOR."xliff".DIRECTORY_SEPARATOR.basename($filePath));

		//manifest
		$manifestFile=$cacheDir.DIRECTORY_SEPARATOR."manifest.rkm";
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
