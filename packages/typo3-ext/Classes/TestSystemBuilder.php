<?php
declare(strict_types=1);

namespace AndreasWolf\TestExtension;

use Symfony\Component\Process\Process;
use TYPO3\TestingFramework\Core\Testbase;

class TestSystemBuilder
{
    public function __construct(private readonly string $instancePath, private readonly Testbase $testbase)
    {
    }

    public function copyComposerJsonFileToTestSystem(string $jsonFilePath): void
    {
        // Composer test system setup – start
        $this->testbase->createDirectory($this->instancePath . '/.mono');
        // TODO get path to packages.json from some configuration instead of hardcoding it
        copy(__DIR__ . '/../../../.mono/packages.json', $this->instancePath . '/.mono/packages.json');
        // TODO adjust file to include required repositories + config/allow-plugins
        copy($jsonFilePath, $this->instancePath . '/composer.json');

        $process = new Process(['composer', 'install', '--no-dev'], $this->instancePath);
        $returnCode = $process->run();
        if ($returnCode !== 0) {
            throw new \RuntimeException('Composer installation in test system ' . $this->instancePath . ' failed.', 1700757647);
        }
    }
}
