<?php

use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\LockArrayRepository;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\ComposerPlugin\ComposerPlugin;

class ComposerPluginTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [
                ScriptEvents::PRE_UPDATE_CMD => 'onPreUpdate',
                ScriptEvents::PRE_INSTALL_CMD => 'onPreInstall',
            ],
            ComposerPlugin::getSubscribedEvents()
        );
    }

    public function testDisableDoesNothing(): void
    {
        $plugin = new ComposerPlugin();
        $plugin->deactivate($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);

        $event->expects($this->never())->method('getComposer');

        $plugin->onPreInstall($event);
        $plugin->onPreUpdate($event);
    }

    public function testUninstallDoesNothing(): void
    {
        $plugin = new ComposerPlugin();
        $plugin->uninstall($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);

        $event->expects($this->never())->method('getComposer');

        $plugin->onPreInstall($event);
        $plugin->onPreUpdate($event);
    }

    public function testInstallSetsConflictVersionFromLock() : void
    {
        $plugin = new ComposerPlugin();
        $plugin->activate($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);
        $composer = $this->createMock(Composer\Composer::class);
        $locker = $this->createMock(Composer\Package\Locker::class);

        $lockRepository = new LockArrayRepository();
        $lockRepository->addPackage(new Package("shopware/conflicts", "1.0.0", "1.0.0"));

        $rootPackage = new RootPackage("shopware/production", "1.0.0", "1.0.0");

        $event->method('getComposer')->willReturn($composer);
        $composer->expects($this->once())->method('getLocker')->willReturn($locker);
        $composer->method('getPackage')->willReturn($rootPackage);

        $locker->expects($this->once())->method('getLockedRepository')->willReturn($lockRepository);

        $plugin->onPreInstall($event);

        $requires = $rootPackage->getRequires();

        static::assertArrayHasKey('shopware/conflicts', $requires);
        static::assertSame('1.0.0', $requires['shopware/conflicts']->getConstraint()->getPrettyString());
    }

    /**
     * @return string
     */
    public static function provideRootNames(): array
    {
        return [
            ['shopware/core'],
            ['shopware/platform'],
        ];
    }

    #[DataProvider('provideRootNames')]
    public function testUpdateSetsConflictVersionRootPackage(string $rootPackageName): void
    {
        $plugin = new ComposerPlugin();
        $plugin->activate($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);
        $composer = $this->createMock(Composer\Composer::class);

        $rootPackage = new RootPackage($rootPackageName, "1.0.0", "1.0.0");

        $event->method('getComposer')->willReturn($composer);
        $composer->method('getPackage')->willReturn($rootPackage);

        $plugin->onPreUpdate($event);

        $requires = $rootPackage->getRequires();

        static::assertArrayHasKey('shopware/conflicts', $requires);
    }

    public function testUpdateDoesNotSetConflictVersionNoShopwareRequired(): void
    {
        $plugin = new ComposerPlugin();
        $plugin->activate($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);
        $composer = $this->createMock(Composer\Composer::class);

        $rootPackage = new RootPackage("shopware/production", "1.0.0", "1.0.0");

        $event->method('getComposer')->willReturn($composer);
        $composer->method('getPackage')->willReturn($rootPackage);

        $plugin->onPreUpdate($event);

        $requires = $rootPackage->getRequires();

        static::assertArrayNotHasKey('shopware/conflicts', $requires);
    }

    public function testUpdateWithShopwareCoreRequired(): void
    {
        $plugin = new ComposerPlugin();
        $plugin->activate($this->createMock(Composer\Composer::class), $this->createMock(Composer\IO\IOInterface::class));

        $event = $this->createMock(Composer\Script\Event::class);
        $composer = $this->createMock(Composer\Composer::class);

        $rootPackage = new RootPackage("shopware/production", "1.0.0", "1.0.0");
        $rootPackage->setRequires([
            'shopware/core' => new Composer\Package\Link('shopware/core', 'shopware/core', new \Composer\Semver\Constraint\Constraint('=', '6.5.0.0'), Link::TYPE_REQUIRE, '6.5.0.0')
        ]);

        $event->method('getComposer')->willReturn($composer);
        $composer->method('getPackage')->willReturn($rootPackage);

        $plugin->onPreUpdate($event);

        $requires = $rootPackage->getRequires();

        static::assertArrayHasKey('shopware/conflicts', $requires);
    }
}