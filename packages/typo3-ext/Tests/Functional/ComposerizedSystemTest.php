<?php
declare(strict_types=1);

namespace AndreasWolf\Typo3Extension\Tests\Functional;

use AndreasWolf\TestExtension\ComposerizedSystemTestCase;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;

final class ComposerizedSystemTest extends ComposerizedSystemTestCase
{
    protected string $composerFile = __DIR__ . '/Fixtures/composer.json';

    /** @test */
    public function systemIsSetUpWithDefaultPackages(): void
    {
        /** @var PackageManager $packageManager */
        $packageManager = $this->get(PackageManager::class);

        self::assertInstanceOf(Package::class, $packageManager->getPackage('core'));
    }
}
