<?php
declare(strict_types=1);

namespace AndreasWolf\TestExtension;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use TYPO3\TestingFramework\Core\Testbase;
use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

class TestSystemBuilder
{
    public function __construct(
        private readonly string $instancePath,
        private readonly string $composerRootDir,
        private readonly Testbase $testbase
    )
    {}

    public function placeAdjustedComposerJsonFileInTestSystem(string $composerJsonFilePath): void
    {
        $this->testbase->createDirectory($this->instancePath . '/.mono');

        file_put_contents(
            $this->instancePath . '/composer.json',
            $this->modifyAndReturnComposerManifest($composerJsonFilePath)
        );
    }

    public function performComposerInstall(): void
    {
        $process = new Process(
            ['composer', 'install', '--no-dev'],
            $this->instancePath,
            ['COMPOSER_ROOT_DIR' => $this->composerRootDir]
        );
        $returnCode = $process->run();

        if ($returnCode !== 0) {
            throw new \RuntimeException('Composer installation in test system ' . $this->instancePath . ' failed.', 1700757647);
        }
    }

    /**
     * Adds the local Composer repository to the given Composer manifest to ensure that it can be used in the test system.
     * Additionally, packagist.org is disabled.
     *
     * @param string $sourceFile The source Composer manifest
     */
    public function modifyAndReturnComposerManifest(string $sourceFile): string
    {
        $testSystemComposerManifest = json_decode(file_get_contents($sourceFile), true);

        $relativeRepositoryPath = Path::makeRelative($this->composerRootDir . '/.mono/', $this->instancePath);
        $testSystemComposerManifest['repositories']['mono'] = [
            'type' => 'composer',
            'url' => $relativeRepositoryPath
        ];
        $testSystemComposerManifest['repositories']['packagist.org'] = false;
        $composerManifestJson = json_encode($testSystemComposerManifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        return $composerManifestJson;
    }
}
