<?php
namespace Mouf\Utils\Cache;

use Mouf\Utils\Log\LogInterface;
use Psr\Log\LoggerInterface;

/**
 * This package contains a cache mechanism that relies on temporary files.
 * It is very close to the "classic" FileCache, but it will create a subdirectory system in order
 * to avoid having all files inside the same folders.
 *
 * One important property is $hashDept. It sets the length of the subdirectory.
 * The higher it is, the more subfolders will be created, but the lesser cache file they will contain.
 * Max count of subfolders is 16^$hashDepth, so you cannot set a depth higher then 4 as it could create
 * more than 1 000 000 subfolders.
 *
 * As an example :
 *    - 1 000 000 items are stored into 256 subfolders for $hashDepth == 2, witch means around 4 000 files per folder
 *    - 1 000 000 items are stored into 4096 subfolders for $hashDepth == 3, witch means around 250 files per folder
 *
 * If you consider caching more than 1 000 000 items, you should consider another cache service
 *
 * WARNING : YOU CANNOT SHARE HIS FOLDER WITH OTHER CACHE SYSTEMS, or even put any other file
 * into the $cacheDirectory, as it will remove the whole folder when purged.
 * 
 */
class BigFileCache implements CacheInterface {
	
	/**
	 * The default time to live of elements stored in the session (in seconds).
	 * Please note that if the session is flushed, all the elements of the cache will disapear anyway.
	 * If empty, the time to live will be the time of the session. 
	 *
	 * @Property
	 * @var int
	 */
    private $defaultTimeToLive;
	
	/**
	 * The logger used to trace the cache activity.
	 * Supports both PSR3 compatible logger and old Mouf logger for compatibility reasons.
	 *
	 * @var LoggerInterface|LogInterface
	 */
    private $log;

	/**
	 * The directory the files are stored in.
	 * If none is specified, they are stored in the "filecache" directory.
	 * The directory must end with a trailing "/".
	 *
	 * @Property
	 * @var string
	 */
    private $cacheDirectory;
		
	/**
	 * Whether the directory is relative to the system temp directory or not.
	 * 
	 * @Property
	 * @var boolean
	 */
    private $relativeToSystemTempDirectory = true;

    private $hashDepth = 2;

    /**
     * @param int $defaultTimeToLive
     * @param LoggerInterface|LogInterface $log
     * @param string $cacheDirectory
     * @param boolean $relativeToSystemTempDirectory
     * @param int $hashDepth
     */
    function __construct($defaultTimeToLive, $log, $cacheDirectory, $relativeToSystemTempDirectory)
    {
        $this->defaultTimeToLive = $defaultTimeToLive;
        $this->log = $log;
        $this->cacheDirectory = $cacheDirectory;
        $this->relativeToSystemTempDirectory = $relativeToSystemTempDirectory;
    }

    /**
     * Must be between 1 and 4
     * Sets the length of the subdirectory. The higher it is, the more subfolders will be created, but the lesser cache file they will contain.
     * Max count of subfolders is 16^$hashDepth, so you cannot set a depth higher then 4 as it could create more than 1 000 000 subfolders
     * As an example :
     *    - 1 000 000 items are stored into 256 subfolders for $hashDepth == 2, witch means around 4 000 files per folder
     *    - 1 000 000 items are stored into 4096 subfolders for $hashDepth == 3, witch means around 250 files per folder
     *
     * If you consider caching more than 1 000 000 items, you should consider another cache service
     *
     * @param int $hashDepth
     */
    public function setHashDepth($hashDepth)
    {
        if ($hashDepth > 4 || $hashDepth < 1){
            throw new \Exception("hashDepth property should be betwwen 1 and 4");
        }
        $this->hashDepth = $hashDepth;
    }




    /**
	 * Returns the cached value for the key passed in parameter.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		$filename = $this->getFileName($key);

		if (is_readable($filename)) {
            $fp = fopen($filename, "r");
            if ($fp === false) {//File may have been deleted between is_readable and fopen
                return null;
            }
            $timeout = fgets($fp);
			
			if ($timeout > time() || $timeout==0) {
				$contents = "";
				while (!feof($fp)) {
				  $contents .= fread($fp, 65536);
				}
				fclose($fp);
				$value = unserialize($contents);
				//$this->log->trace("Retrieving key '$key' from file cache: value returned:".var_export($value, true));
				if ($this->log) {
					if ($this->log instanceof LoggerInterface) {
						$this->log->info("Retrieving key '{key}' from file cache.", array('key'=>$key));
					} else {
						$this->log->trace("Retrieving key '$key' from file cache.");
					}						
				}
				return $value;
			} else {
				fclose($fp);
				unlink($filename);
				if ($this->log) {
					if ($this->log instanceof LoggerInterface) {
						$this->log->info("Retrieving key '{key}' from file cache: key outdated, cache miss.", array('key'=>$key));
					} else {
						$this->log->trace("Retrieving key '$key' from file cache: key outdated, cache miss.");
					}
				}
				return null;
			}
		} else {
			if ($this->log) {
				if ($this->log instanceof LoggerInterface) {
					$this->log->info("Retrieving key '{key}' from file cache: cache miss.", array('key'=>$key));
				} else {
					$this->log->trace("Retrieving key '$key' from file cache: cache miss.");
				}
			}
			return null;
		}
	}
	
	/**
	 * Sets the value in the cache.
	 *
	 * @param string $key The key of the value to store
	 * @param mixed $value The value to store
	 * @param float $timeToLive The time to live of the cache, in seconds.
	 */
	public function set($key, $value, $timeToLive = null) {
		$filename = $this->getFileName($key);
		//$this->log->trace("Storing value in cache: key '$key', value '".var_export($value, true)."'");
		if ($this->log) {
			if ($this->log instanceof LoggerInterface) {
				$this->log->info("Storing value in cache: key '{key}'", array('key'=>$key));
			} else {
				$this->log->trace("Storing value in cache: key '$key'");
			}
		}

        $oldUmask = umask(0);
		$subfolder = $this->getDirectory($key);
		if (!is_writable($filename)) {
			if (!file_exists($subfolder)) {
				mkdir($subfolder, 0777, true);
			}
		}
		
		if ($timeToLive == null) {
			if (empty($this->defaultTimeToLive)) {
				$timeOut = 0;
			} else {
				$timeOut = time() + $this->defaultTimeToLive;
			}
		} else {
			$timeOut = time() + $timeToLive;
		}
		
		$fp = fopen($filename, "w");
		fwrite($fp, $timeOut."\n");
		fwrite($fp, serialize($value));
		fclose($fp);
        // Cache is shared with group, not with the rest of the world.
        chmod($filename, 0660);

        umask($oldUmask);
	}
	
	/**
	 * Removes the object whose key is $key from the cache.
	 *
	 * @param string $key The key of the object
	 */
	public function purge($key) {
		if ($this->log) {
			if ($this->log instanceof LoggerInterface) {
				$this->log->info("Purging key '{key}' from file cache.", array('key'=>$key));
			} else {
				$this->log->trace("Purging key '$key' from file cache.");
			}
		}
		$filename = $this->getFileName($key);
		if (file_exists($filename)) {
			unlink($filename);
		}
	}
	
	/**
	 * Removes all the objects from the cache.
	 *
	 */
	public function purgeAll() {
		if ($this->log) {
			if ($this->log instanceof LoggerInterface) {
				$this->log->info("Purging the whole file cache.");
			} else {
				$this->log->trace("Purging the whole file cache.");
			}
		}
        self::rrmdir($this->getDirectory());
	}

    /**
     * @param mixed $key : if set, this function will return the cache directory WITH subfolder
     * @return string
     */
	protected function getDirectory($key = null) {

		$dir = "";
		if ($this->relativeToSystemTempDirectory) {
			$dir .= sys_get_temp_dir()."/";
		}
		if (!empty($this->cacheDirectory)) {
			$dir .= $this->cacheDirectory;
		} else {
			$dir .= "filecache/";
		}

        if ($key){
            $subFolder = substr(hash("sha256", $key), 0, $this->hashDepth) . "/";
        }else{
            $subFolder = "";
        }

		return $dir.$subFolder;
	}

	protected function getFileName($key) {
        $subFolder = $this->getDirectory($key);
		// Remove any "/" and ":" from the name, and replace those with "_" ...
		$key = str_replace(array("_", "/", "\\", ":"), array("___", "_s_", "_b_", "_d_"), $key);

        // Windows full path need to be less than 260 characters. We need to limit the size of the filename
        $fullPath = $subFolder.$key.".cache";

        // Approximative value due to NTFS short file names (e.g. PROGRA~1) that get longer when evaluated by Windows
        if (strlen($fullPath)<160) {
            return $fullPath;
		}
		

		// If we go above 160 characters, let's transform the key into a md5
		return $subFolder.md5($key).'.cache';
	}

    private static function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir")
                        self::rrmdir($dir."/".$object);
                    else unlink   ($dir."/".$object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
