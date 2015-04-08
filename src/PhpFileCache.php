<?php
namespace Mouf\Utils\Cache;
use Mouf\Utils\Log\LogInterface;
use Psr\Log\LoggerInterface;

/**
 * This package contains a cache mechanism that relies on executable PHP files.
 * For a file cache, it is quite fast because it relies on the Opcache to cache data.
 *
 * It features best performances than the regular FileCache. However, be aware that it generates PHP files
 * that are directly "included" in your application, so no third party should have access to the repository where
 * you store the cache.
 *
 */
class PhpFileCache extends FileCache {

	public function __construct() {
		// Let's check if the parameters are ok.
		// We should not allow opcache file revalidation should be set to 0
		if (ini_get('opcache.revalidate_freq') != 0) {
			throw new \Exception("In order to use PhpFileCache, you must set the parameter 'opcache.revalidate_freq' to 0 in your php.ini file. Current value: '".ini_get('opcache.revalidate_freq')."'");
		}
	}

	/**
	 * Returns the cached value for the key passed in parameter.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		$filename = $this->getFileName($key);

		if ( ! is_file($filename)) {
			if ($this->log) {
				if ($this->log instanceof LoggerInterface) {
					$this->log->info("Retrieving key '{key}' from file cache: cache miss.", array('key'=>$key));
				} else {
					$this->log->trace("Retrieving key '$key' from file cache: cache miss.");
				}
			}
			return false;
		}
		$value = include $filename;

		if ($value['lifetime'] !== 0 && $value['lifetime'] < time()) {
			if ($this->log) {
				if ($this->log instanceof LoggerInterface) {
					$this->log->info("Retrieving key '{key}' from file cache: key outdated, cache miss.", array('key'=>$key));
				} else {
					$this->log->trace("Retrieving key '$key' from file cache: key outdated, cache miss.");
				}
			}
			return false;
		}

		if ($this->log) {
			if ($this->log instanceof LoggerInterface) {
				$this->log->info("Retrieving key '{key}' from file cache.", array('key'=>$key));
			} else {
				$this->log->trace("Retrieving key '$key' from file cache.");
			}
		}

		return $value['data'];
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

		if (is_object($value) && !method_exists($value, '__set_state')) {
			throw new \InvalidArgumentException(
				"Invalid argument given, PhpFileCache only allows objects that implement __set_state() " .
				"and fully support var_export(). You can use the FileCache to save arbitrary object " .
				"graphs using serialize()/deserialize()."
			);
		}

		$data = array(
			'lifetime'  => $timeOut,
			'data'      => $value
		);

		$data  = var_export($data, true);
		$code   = sprintf('<?php return %s;', $data);

		file_put_contents($filename, $code);
        // Cache is shared with group, not with the rest of the world.
        chmod($filename, 0660);

        umask($oldUmask);
	}

}
