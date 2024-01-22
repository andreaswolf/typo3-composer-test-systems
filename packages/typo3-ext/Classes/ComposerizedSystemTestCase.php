<?php

namespace AndreasWolf\TestExtension;

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseSnapshot;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\TestingFramework\Core\Testbase;

abstract class ComposerizedSystemTestCase extends FunctionalTestCase
{
    private bool $isFirstTest = true;

    private ContainerInterface $container;

    protected string $composerFile;

    /**
     * This is copied from {@see FunctionalTestCase::setUp()} and adjusted to skip core/extension symlinking
     * and replace it with a `composer install` call.
     */
    protected function setUp(): void
    {
        if (!defined('ORIGINAL_ROOT')) {
            self::markTestSkipped('Functional tests must be called through phpunit on CLI');
        }

        $this->identifier = self::getInstanceIdentifier();
        $this->instancePath = self::getInstancePath();
        putenv('TYPO3_PATH_ROOT=' . $this->instancePath);
        putenv('TYPO3_PATH_APP=' . $this->instancePath);

        if ($this->composerFile) {
            $testbase = new ComposerizedTestbase();
        } else {
            $testbase = new Testbase();
        }
        $testbase->setTypo3TestingContext();

        // See if we're the first test of this test case.
        /*$currentTestCaseClass = get_called_class();
        if (self::$currentTestCaseClass !== $currentTestCaseClass) {
            self::$currentTestCaseClass = $currentTestCaseClass;
        } else {
            $this->isFirstTest = false;
        }*/

        // sqlite db path preparation
        $dbPathSqlite = dirname($this->instancePath) . '/functional-sqlite-dbs/test_' . $this->identifier . '.sqlite';
        $dbPathSqliteEmpty = dirname($this->instancePath) . '/functional-sqlite-dbs/test_' . $this->identifier . '.empty.sqlite';

        $testSystemBuilder = new TestSystemBuilder($this->instancePath, $testbase);

        if (!$this->isFirstTest) {
            // Reusing an existing instance. This typically happens for the second, third, ... test
            // in a test case, so environment is set up only once per test case.
            GeneralUtility::purgeInstances();
            $this->container = $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            if ($this->initializeDatabase) {
                $testbase->initializeTestDatabaseAndTruncateTables($dbPathSqlite, $dbPathSqliteEmpty);
            }
        } else {
            DatabaseSnapshot::initialize(dirname($this->getInstancePath()) . '/functional-sqlite-dbs/', $this->identifier);
            $testbase->removeOldInstanceIfExists($this->instancePath);
            // Basic instance directory structure
            $testbase->createDirectory($this->instancePath . '/fileadmin');
            $testbase->createDirectory($this->instancePath . '/typo3temp/var/transient');
            $testbase->createDirectory($this->instancePath . '/typo3temp/assets');
            $testbase->createDirectory($this->instancePath . '/typo3conf/ext');
            // Additionally requested directories
            foreach ($this->additionalFoldersToCreate as $directory) {
                $testbase->createDirectory($this->instancePath . '/' . $directory);
            }

            if ($this->composerFile) {
                // this is special setup code for Composer-based systems
                $testSystemBuilder->placeAdjustedComposerJsonFileInTestSystem($this->composerFile);
                $testSystemBuilder->performComposerInstall();
            } else {
                // this branch is copied verbatim from the parent method

                $defaultCoreExtensionsToLoad = [
                    'core',
                    'backend',
                    'frontend',
                    'extbase',
                    'install',
                    'fluid',
                ];
                $frameworkExtension = [
                    'Resources/Core/Functional/Extensions/json_response',
                    'Resources/Core/Functional/Extensions/private_container',
                ];

                $testbase->setUpInstanceCoreLinks($this->instancePath, $defaultCoreExtensionsToLoad, $this->coreExtensionsToLoad);
                $testbase->linkTestExtensionsToInstance($this->instancePath, $this->testExtensionsToLoad);
                $testbase->linkFrameworkExtensionsToInstance($this->instancePath, $frameworkExtension);
            }
            // the rest of the method is copied from the base method

            $testbase->linkPathsInTestInstance($this->instancePath, $this->pathsToLinkInTestInstance);
            $testbase->providePathsInTestInstance($this->instancePath, $this->pathsToProvideInTestInstance);
            $localConfiguration['DB'] = $testbase->getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration();

            $originalDatabaseName = '';
            $dbName = '';
            $dbDriver = $localConfiguration['DB']['Connections']['Default']['driver'];
            if ($dbDriver !== 'pdo_sqlite') {
                $originalDatabaseName = $localConfiguration['DB']['Connections']['Default']['dbname'];
                if ($originalDatabaseName !== preg_replace('/[^a-zA-Z0-9_]/', '', $originalDatabaseName)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Database name "%s" is invalid. Use a valid name, for example "%s".',
                            $originalDatabaseName,
                            preg_replace('/[^a-zA-Z0-9_]/', '', $originalDatabaseName)
                        ),
                        1695139917
                    );
                }
                // Append the unique identifier to the base database name to end up with a single database per test case
                $dbName = $originalDatabaseName . '_ft' . $this->identifier;
                $localConfiguration['DB']['Connections']['Default']['dbname'] = $dbName;
                $testbase->testDatabaseNameIsNotTooLong($originalDatabaseName, $localConfiguration);
                if ($dbDriver === 'mysqli' || $dbDriver === 'pdo_mysql') {
                    $localConfiguration['DB']['Connections']['Default']['charset'] = 'utf8mb4';
                    $localConfiguration['DB']['Connections']['Default']['tableoptions']['charset'] = 'utf8mb4';
                    $localConfiguration['DB']['Connections']['Default']['tableoptions']['collate'] = 'utf8mb4_unicode_ci';
                    $localConfiguration['DB']['Connections']['Default']['initCommands'] = 'SET SESSION sql_mode = \'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY\';';
                }
            } else {
                // sqlite dbs of all tests are stored in a dir parallel to instance roots. Allows defining this path as tmpfs.
                $testbase->createDirectory(dirname($this->instancePath) . '/functional-sqlite-dbs');
                $localConfiguration['DB']['Connections']['Default']['path'] = $dbPathSqlite;
            }

            // Set some hard coded base settings for the instance. Those could be overruled by
            // $this->configurationToUseInTestInstance if needed again.
            $localConfiguration['SYS']['displayErrors'] = '1';
            $localConfiguration['SYS']['debugExceptionHandler'] = '';
            // By setting errorHandler to empty string, only the phpunit error handler is
            // registered in functional tests, so settings like convertWarningsToExceptions="true"
            // in FunctionalTests.xml will let tests fail that throw warnings.
            $localConfiguration['SYS']['errorHandler'] = '';
            $localConfiguration['SYS']['trustedHostsPattern'] = '.*';
            $localConfiguration['SYS']['encryptionKey'] = 'i-am-not-a-secure-encryption-key';
            $localConfiguration['GFX']['processor'] = 'GraphicsMagick';
            // Set cache backends to null backend instead of database backend let us save time for creating
            // database schema for it and reduces selects/inserts to the database for cache operations, which
            // are generally not really needed for functional tests. Specific tests may restore this in if needed.
            $localConfiguration['SYS']['caching']['cacheConfigurations']['hash']['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
            $localConfiguration['SYS']['caching']['cacheConfigurations']['imagesizes']['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
            $localConfiguration['SYS']['caching']['cacheConfigurations']['pages']['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
            $localConfiguration['SYS']['caching']['cacheConfigurations']['rootline']['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend';
            $testbase->setUpLocalConfiguration($this->instancePath, $localConfiguration, $this->configurationToUseInTestInstance);
            if (!$this->composerFile) {
                $testbase->setUpPackageStates(
                    $this->instancePath,
                    $defaultCoreExtensionsToLoad,
                    $this->coreExtensionsToLoad,
                    $this->testExtensionsToLoad,
                    $frameworkExtension
                );
            }
            $this->container = $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            if ($this->initializeDatabase) {
                if ($dbDriver !== 'pdo_sqlite') {
                    $testbase->setUpTestDatabase($dbName, $originalDatabaseName);
                } else {
                    $testbase->setUpTestDatabase($dbPathSqlite, $originalDatabaseName);
                }
            }
            $testbase->loadExtensionTables();
            if ($this->initializeDatabase) {
                $testbase->createDatabaseStructure($this->container);
                if ($dbDriver === 'pdo_sqlite') {
                    // Copy sqlite file '/path/functional-sqlite-dbs/test_123.sqlite' to
                    // '/path/functional-sqlite-dbs/test_123.empty.sqlite'. This is re-used for consecutive tests.
                    copy($dbPathSqlite, $dbPathSqliteEmpty);
                }
            }
        }
        $testbase->loadExtensionTables();
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}