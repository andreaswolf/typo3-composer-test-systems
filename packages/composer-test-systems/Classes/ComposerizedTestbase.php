<?php

namespace AndreasWolf\ComposerTestSystems;

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\SystemEnvironmentBuilder;

class ComposerizedTestbase extends \TYPO3\TestingFramework\Core\Testbase
{
    /**
     * Bootstrap basic TYPO3. This bootstraps TYPO3 far enough to initialize database afterwards.
     * For functional and acceptance tests.
     *
     * @param non-empty-string $instancePath Absolute path to test instance
     * @return ContainerInterface
     *
     * TODO this is overwritten from the base class to set Composer mode in {@see SystemEnvironmentBuilder::run()}.
     */
    public function setUpBasicTypo3Bootstrap($instancePath): ContainerInterface
    {
        $_SERVER['PWD'] = $instancePath;
        $_SERVER['argv'][0] = 'typo3/index.php';

        // Reset state from a possible previous run
        GeneralUtility::purgeInstances();

        $classLoader = require $this->getPackagesPath() . '/autoload.php';
        SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_BE | SystemEnvironmentBuilder::REQUESTTYPE_CLI, true);
        $container = Bootstrap::init($classLoader);
        // Make sure output is not buffered, so command-line output can take place and
        // phpunit does not whine about changed output bufferings in tests.
        ob_end_clean();

        $this->dumpClassLoadingInformation();

        return $container;
    }
}