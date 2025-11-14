<?php

namespace Shopware\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_UPDATE_CMD => 'onPreUpdate',
            ScriptEvents::PRE_INSTALL_CMD => 'onPreInstall',
        ];
    }

    public function onPreInstall(Event $event): void
    {
        $this->patchIgnoredCVEs($event->getComposer());
    }

    public function onPreUpdate(Event $event): void
    {
        $this->patchIgnoredCVEs($event->getComposer());
    }

    private function patchIgnoredCVEs(Composer $composer): void
    {
        $composer->getConfig()->merge(['config' => ['audit' => ['ignore' => $this->determineCVEsToIgnore()]]]);
    }

    private function determineCVEsToIgnore(): array
    {
        $dir = getcwd();

        $securityPluginLocations = [
            $dir . '/custom/plugins/SwagPlatformSecurity/composer.json',
            $dir . '/vendor/swag/platform-security/composer.json',
            $dir . '/vendor/store.shopware.com/swagplatformsecurity/composer.json',
        ];

        foreach ($securityPluginLocations as $location) {
            if (file_exists($location)) {
                $content = file_get_contents($location);
                if ($content === false) {
                    continue;
                }

                $json = json_decode($content, true);
                if (isset($json['extra']['shopware']['ignored-cves']) && is_array($json['extra']['shopware']['ignored-cves'])) {
                    return $json['extra']['shopware']['ignored-cves'];
                }
            }
        }

        return [];
    }
}