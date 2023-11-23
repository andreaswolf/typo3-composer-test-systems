<?php
declare(strict_types=1);

namespace Helhum\ComposerMono;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class MonoPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var LocalRepositoryFactory
     */
    private $localRepositoryFactory;

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => ['postAutoloadDump'],
        ];
    }

    public function postAutoloadDump(Event $event): void
    {
        $this->localRepositoryFactory->createComposerRepositoryFromInstalledPackages($event);
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->localRepositoryFactory = new LocalRepositoryFactory($composer);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // nothing to do
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // nothing to do
    }
}
