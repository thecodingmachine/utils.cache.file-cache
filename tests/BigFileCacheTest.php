<?php
namespace Mouf\Utils\Cache;

class BigFileCacheTest extends FileCacheTest {

	public function getCacheService() {
		$fileCache = new BigFileCache(
            3600, null, __DIR__.'/cachedir/', false
        );
		$fileCache->setHashDepth(2);
		return $fileCache;
	}
}
