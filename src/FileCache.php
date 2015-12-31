<?php
namespace Mouf\Utils\Cache;
use Mouf\Utils\Log\LogInterface;
use Psr\Log\LoggerInterface;

/**
 * This package contains a cache mechanism that relies on temporary files.
 *
 * IMPORTANT: unless you have a good reason to do otherwise, you should use the PhpFileCache instead of this class.
 *
 * TODO: make a global garbage collector that passes sometimes (like sessions in PHP)
 * 
 */
class FileCache implements CacheInterface {
	
	/**
	 * The default time to live of elements stored in the session (in seconds).
	 * Please note that if the session is flushed, all the elements of the cache will disapear anyway.
	 * If empty, the time to live will be the time of the session. 
	 *
	 * @Property
	 * @var int
	 */
	public $defaultTimeToLive;

	/**
	 * A prefix to be added to all the keys of the cache. Very useful to avoid conflicting name between different instances.
	 *
	 * @var string
	 */
	public $prefix = "";
	
	/**
	 * The logger used to trace the cache activity.
	 * Supports both PSR3 compatible logger and old Mouf logger for compatibility reasons.
	 *
	 * @var LoggerInterface|LogInterface
	 */
	public $log;

	/**
	 * The directory the files are stored in.
	 * If none is specified, they are stored in the "filecache" directory.
	 * The directory must end with a trailing "/".
	 *
	 * @Property
	 * @var string
	 */
	public $cacheDirectory;
		
	/**
	 * Whether the directory is relative to the system temp directory or not.
	 * 
	 * @Property
	 * @var boolean
	 */
	public $relativeToSystemTempDirectory = true;
	
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
		
		if (!is_writable($filename)) {
			if (!file_exists($this->getDirectory())) {
				mkdir($this->getDirectory(), 0777, true);
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
		$files = glob($this->getDirectory()."*");
        if ($files !== false){// some file systems wont distinguish between empty match and an error
            $prefixFile = str_replace(array("_", "/", "\\", ":"), array("___", "_s_", "_b_", "_d_"), $this->prefix);
            foreach ($files as $filename) {
                if (empty($prefixFile) || strpos(basename($filename), $prefixFile) === 0) {
                    unlink($filename);
                }
            }
        }
	}
	
	protected function getDirectory() {

		$dir = "";
		if ($this->relativeToSystemTempDirectory) {
			$dir .= sys_get_temp_dir()."/";
		}
		if (!empty($this->cacheDirectory)) {
			$dir .= $this->cacheDirectory;
		} else {
			$dir .= "filecache/";
		}
		return $dir;
	}

	protected function getFileName($key) {
		// Remove any "/" and ":" from the name, and replace those with "_" ...
		$key = str_replace(array("_", "/", "\\", ":"), array("___", "_s_", "_b_", "_d_"), $this->prefix.$key);
		
		// Windows full path need to be less than 260 characters. We need to limit the size of the filename
		$fullPath = $this->getDirectory().$key.".cache";
		
		// Approximative value due to NTFS short file names (e.g. PROGRA~1) that get longer when evaluated by Windows
		if (strlen($fullPath)<160) {
			return $fullPath;
		}
		
		$prefix = str_replace(array("_", "/", "\\", ":"), array("___", "_s_", "_b_", "_d_"), $this->prefix);
		
		// If we go above 160 characters, let's transform the key into a md5
		
		return $this->getDirectory().$prefix.md5($key).'.cache';
	}
}
