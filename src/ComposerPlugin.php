<?php

namespace Shopware\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\VersionParser;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_UPDATE_CMD => 'onPreUpdate',
            ScriptEvents::PRE_INSTALL_CMD => 'onPreUpdate',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function onPreUpdate(Event $event): void
    {
        $parser = new VersionParser();
        $rootPackage = $event->getComposer()->getPackage();
        $requires = $rootPackage->getRequires();

        $swVersion = null;
        if (array_key_exists('shopware/core', $requires)) {
            $swVersion = $requires['shopware/core']->getConstraint();
        }

        if (array_key_exists('shopware/platform', $requires)) {
            $swVersion = $requires['shopware/platform']->getConstraint();
        }

        if ($swVersion === null) {
            if (isset($this->io)) {
                $this->io->warning('Cannot find shopware/core or shopware/platform as requirement in the root composer.json. Ignoring enforcing latest conflict package');
            }
            return;
        }

        foreach ($this->fetchConflicts()['packages']['shopware/conflicts'] as $version) {
            if ($swVersion->matches($parser->parseConstraints($version['require']['shopware/core']))) {

                $requires['shopware/conflicts'] = new Link("shopware/conflicts", "shopware/conflicts", $parser->parseConstraints($version['version']), Link::TYPE_REQUIRE, $version['version']);

                $rootPackage->setRequires($requires);

                break;
            }
        }
    }

    /**
     * @return array{packages: array{"shopware/conflicts": array{version: string, conflict: array<string, string>, require: array{"shopware/core": string}}[]}}
     * @throws \JsonException
     */
    private function fetchConflicts(): array
    {
        $ch = curl_init('https://repo.packagist.org/p2/shopware/conflicts.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $json = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new \RuntimeException("Cannot fetch conflicts JSON file https://repo.packagist.org/p2/shopware/conflicts.json");
        }

        curl_close($ch);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}