<?php
namespace Mouf\Utils\Cache;
use Mouf\Utils\Log\LogInterface;
use Psr\Log\LoggerInterface;

class FileCacheTest extends \PHPUnit_Framework_TestCase {

	public function getCacheService() {
		$fileCache = new FileCache();
		$fileCache->cacheDirectory = __DIR__.'/cachedir/';
		$fileCache->defaultTimeToLive = 3600;
		$fileCache->prefix = "TOTO";
		$fileCache->relativeToSystemTempDirectory = false;
		return $fileCache;
	}

	public function testCache() {
		$cacheService = $this->getCacheService();

		$cacheService->set('hello', 'world');
		$result = $cacheService->get('hello');

		$this->assertEquals('world', $result);
	}
}
