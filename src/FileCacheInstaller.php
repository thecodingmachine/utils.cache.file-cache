<?php
namespace Mouf\Utils\Cache;

use Mouf\Installer\PackageInstallerInterface;
use Mouf\MoufManager;

/**
 * Installer for file cache
 */
class FileCacheInstaller implements PackageInstallerInterface {

    /**
     * (non-PHPdoc)
     * @see \Mouf\Installer\PackageInstallerInterface::install()
     */
    public static function install(MoufManager $moufManager) {
        if (!$moufManager->instanceExists("fileCacheService")) {
            $fileCacheService = $moufManager->createInstance("Mouf\\Utils\\Cache\\FileCache");
            $fileCacheService->setName("fileCacheService");
            $fileCacheService->getProperty("defaultTimeToLive")->setValue(3600);
            /*if ($moufManager->instanceExists("psr.errorLogLogger")) {
                $fileCacheService->getProperty("log")->setValue($moufManager->getInstanceDescriptor("psr.errorLogLogger"));
            }*/
        } else {
            $fileCacheService = $moufManager->getInstanceDescriptor("fileCacheService");
        }

        $configManager = $moufManager->getConfigManager();
        $constants = $configManager->getMergedConstants();
        if (isset($constants['SECRET'])) {
            $fileCacheService->getProperty('prefix')->setValue('SECRET')->setOrigin('config');
        }

        // Let's rewrite the MoufComponents.php file to save the component
        $moufManager->rewriteMouf();
    }
}
?>