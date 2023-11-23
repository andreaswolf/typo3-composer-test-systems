<?php
declare(strict_types=1);

namespace Helhum\ComposerMono;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Json\JsonFile;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Script\Event;
use Composer\Util\Filesystem;

class LocalRepositoryFactory
{
    /**
     * @var string
     */
    private $baseDir;

    /**
     * @var array
     */
    private $appsComposerBackup = [];

    public function __construct(Composer $composer)
    {
        $composerConfig = $composer->getConfig();
        $vendorDir = $composerConfig->get('vendor-dir');
        $this->baseDir = realpath(substr($vendorDir, 0, -strlen($composerConfig->get('vendor-dir', $composerConfig::RELATIVE_PATHS))));
    }

    public function createComposerRepositoryFromInstalledPackages(Event $event): void
    {
        $fileSystem = new Filesystem();
        $composer = $event->getComposer();
        $rootPackage = $composer->getPackage();
        $pluginOptions = $rootPackage->getExtra()['helhum/composer-mono'] ?? [];
        $appsDir = $fileSystem->normalizePath($this->baseDir . '/' . ($pluginOptions['apps-dir'] ?? 'apps'));
        $autoLoadGenerator = $composer->getAutoloadGenerator();
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $packagesAndPath = $autoLoadGenerator->buildPackageMap($composer->getInstallationManager(), $rootPackage, $localRepo->getCanonicalPackages());

        $packages = [];
        $arrayDumper = new ArrayDumper();
        foreach ($packagesAndPath as [$package, $path]) {
            $packageInfo = $arrayDumper->dump($package);
            if ($package->getType() !== 'metapackage') {
                unset($packageInfo['dist'],
                    $packageInfo['source'],
                    $packageInfo['installation-source'],
                    $packageInfo['notification-url'],
                );
                $packageSourceDir = $package === $rootPackage ? $this->baseDir : $composer->getInstallationManager()->getInstallPath($package);
                $packageInfo['dist'] = [
                    'type' => 'path',
                    // relative dirs here are treated as relative to the *project composer.json file*
                    'url' => $packageSourceDir,
                    'reference' => $package->getInstallationSource() === 'source' ? $package->getSourceReference() : $package->getDistReference(),
                ];
            }
            $version = $package->getPrettyVersion();
            if (isset($pluginOptions['versions'][$package->getName()])) {
                $version = $packageInfo['version'] = $pluginOptions['versions'][$package->getName()];
                $packageInfo['version_normalized'] = $version . '.0';
            }
            $packages[$package->getName()][$version] = $packageInfo;
        }

        $packageJson = [
            'packages' => $packages,
        ];

        $composerRepoDir = $fileSystem->normalizePath($this->baseDir . '/' . ($pluginOptions['repo-dir'] ?? '.mono'));
        $fileSystem->ensureDirectoryExists($composerRepoDir);
        file_put_contents($composerRepoDir . '/packages.json', json_encode($packageJson, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
