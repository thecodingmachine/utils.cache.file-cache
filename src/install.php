<?php
/*
 * Copyright (c) 2012 David Negrier
 *
 * See the file LICENSE.txt for copying permission.
 */

require_once __DIR__."/../../../autoload.php";

use Mouf\Actions\InstallUtils;
use Mouf\MoufManager;

// Let's init Mouf
InstallUtils::init(InstallUtils::$INIT_APP);

// Let's create the instance
$moufManager = MoufManager::getMoufManager();
if (!$moufManager->instanceExists("fileCacheService")) {
	$fileCacheService = $moufManager->createInstance("Mouf\\Utils\\Cache\\FileCache");
	$fileCacheService->setName("fileCacheService");
	$fileCacheService->getProperty("defaultTimeToLive")->setValue(3600);
	if ($moufManager->instanceExists("errorLogLogger")) {
		$fileCacheService->getProperty("log")->setValue($moufManager->getInstanceDescriptor("errorLogLogger"));
	}
} else {
	$fileCacheService = $moufManager->getInstanceDescriptor("fileCacheService");
}

$configManager = $moufManager->getConfigManager();
$constants = $configManager->getMergedConstants();
if (isset($constants['ROOT_URL'])) {
	$fileCacheService->getProperty('prefix')->setValue('ROOT_URL')->setOrigin('config');
}

// Let's rewrite the MoufComponents.php file to save the component
$moufManager->rewriteMouf();

// Finally, let's continue the install
InstallUtils::continueInstall();
?>