<?php
namespace Mouf\Utils\Cache;
use Mouf\Utils\Log\LogInterface;
use Psr\Log\LoggerInterface;

class PhpFileCacheTest extends FileCacheTest {

	public function getCacheService() {
		$fileCache = new PhpFileCache();
		$fileCache->cacheDirectory = __DIR__.'/cachedir/';
		$fileCache->defaultTimeToLive = 3600;
		$fileCache->prefix = "TOTO";
		$fileCache->relativeToSystemTempDirectory = false;
		return $fileCache;
	}
}
