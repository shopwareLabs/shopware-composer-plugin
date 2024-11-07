<?php

namespace Shopware\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    private bool $disable = false;

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_UPDATE_CMD => 'onPreUpdate',
            ScriptEvents::PRE_INSTALL_CMD => 'onPreInstall',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->disable = true;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->disable = true;
    }

    public function onPreInstall(Event $event): void
    {
        if ($this->disable) {
            return;
        }

        $lockData = $event->getComposer()->getLocker()->getLockedRepository();

        foreach($lockData->getPackages() as $package) {
            if ($package->getName() === 'shopware/conflicts') {
                $requires = $event->getComposer()->getPackage()->getRequires();

                $requires['shopware/conflicts'] = new Link("shopware/conflicts", "shopware/conflicts", (new VersionParser)->parseConstraints($package->getVersion()), Link::TYPE_REQUIRE, $package->getVersion());

                $event->getComposer()->getPackage()->setRequires($requires);
                break;
            }
        }
    }

    public function onPreUpdate(Event $event): void
    {
        if ($this->disable) {
            return;
        }

        $parser = new VersionParser();
        $rootPackage = $event->getComposer()->getPackage();

        $swVersion = $this->getShopwareVersion($rootPackage);

        if ($swVersion === null) {
            if (isset($this->io)) {
                $this->io->warning('Cannot find shopware/core or shopware/platform as requirement in the root composer.json. Ignoring enforcing latest conflict package');
            }
            return;
        }

        foreach ($this->fetchConflicts()['packages']['shopware/conflicts'] as $version) {
            if ($swVersion->matches($parser->parseConstraints($version['require']['shopware/core']))) {
                $requires = $rootPackage->getRequires();

                $requires['shopware/conflicts'] = new Link("shopware/conflicts", "shopware/conflicts", $parser->parseConstraints($version['version']), Link::TYPE_REQUIRE, $version['version']);

                $rootPackage->setRequires($requires);

                break;
            }
        }
    }

    private function getShopwareVersion(RootPackageInterface $package): ?ConstraintInterface
    {
        $requires = $package->getRequires();

        if (array_key_exists('shopware/core', $requires)) {
            return $requires['shopware/core']->getConstraint();
        }

        if (array_key_exists('shopware/platform', $requires)) {
            return $requires['shopware/platform']->getConstraint();
        }

        if ($package->getName() === 'shopware/core' || $package->getName() === 'shopware/platform') {
            return (new VersionParser)->parseConstraints($package->getVersion());
        }

        return null;
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