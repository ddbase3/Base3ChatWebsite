<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\Language\Api\ILanguage;
use Base3\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\Middleware\Session\SessionMiddleware;
use Base3\Session\Api\ISession;
use Base3\Session\BasicSession\BasicSession;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3ChatWebsite\Base3ChatWebsitePlugin;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use Base3\Settings\Api\ISettingsStore;

final class Base3ChatWebsitePluginTest extends TestCase {

	public function testTechnicalNameAndServiceRegistration(): void {
		$this->assertSame('base3chatwebsiteplugin', Base3ChatWebsitePlugin::getName());
		$definitions = $this->registerServices();

		$this->assertSame('plugin/Base3ChatWebsite/assets/logo/logo.svg', $definitions['logo'][0]);
		$this->assertSame(IContainer::PARAMETER, $definitions['logo'][1]);
		$this->assertSame('session', $definitions[ISession::class][0]);
		$this->assertSame('language', $definitions[ILanguage::class][0]);
		$this->assertSame(IDatabase::class, $definitions['database'][0]);
		$this->assertSame('accesscontrol', $definitions[IAccesscontrol::class][0]);
		$this->assertSame('authtoken', $definitions[\Base3\Token\Api\IToken::class][0]);
		$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $definitions[IDatabase::class][1]);
		$this->assertSame(IContainer::SHARED, $definitions[IAssetResolver::class][1]);
		$this->assertSame(IContainer::SHARED, $definitions[ISettingsStore::class][1]);
		$this->assertSame(IContainer::SHARED, $definitions[IQueryService::class][1]);
		$this->assertSame(IContainer::SHARED, $definitions['navigation'][1]);
		$this->assertSame(IContainer::SHARED, $definitions['session'][1]);
		$this->assertSame(IContainer::SHARED, $definitions['microservicehelper'][1]);
		$this->assertSame(IContainer::SHARED, $definitions['microserviceserializedhelper'][1]);
		$this->assertSame(IContainer::SHARED, $definitions[Base3ChatWebsitePlugin::getName()][1]);
		$this->assertInstanceOf(Base3ChatWebsitePlugin::class, $definitions[Base3ChatWebsitePlugin::getName()][0]);
	}

	public function testLocalFactoriesUseExistingFrameworkServices(): void {
		$definitions = $this->registerServices();
		$configuration = $this->createStub(IConfiguration::class);
		$session = $this->createStub(ISession::class);
		$accesscontrol = $this->createStub(IAccesscontrol::class);
		$container = $this->createStub(IContainer::class);
		$container->method('get')->willReturnCallback(static fn(string $name): mixed => match ($name) {
			IConfiguration::class => $configuration,
			ISession::class => $session,
			IAccesscontrol::class => $accesscontrol,
			default => null
		});

		$this->assertInstanceOf(BasicSession::class, ($definitions['session'][0])($container));
		$this->assertInstanceOf(\Closure::class, $definitions['microservicehelper'][0]);
		$this->assertInstanceOf(\Closure::class, $definitions['microserviceserializedhelper'][0]);
		$middlewares = ($definitions['middlewares'][0])($container);
		$this->assertCount(2, $middlewares);
		$this->assertInstanceOf(SessionMiddleware::class, $middlewares[0]);
		$this->assertInstanceOf(AccesscontrolMiddleware::class, $middlewares[1]);
		$this->assertInstanceOf(\Base3\Core\BaseAssetResolver::class, ($definitions[IAssetResolver::class][0])());
	}

	public function testAuthenticationFactoryExposesExpectedMethods(): void {
		$definitions = $this->registerServices();
		$container = $this->createStub(IContainer::class);
		$authentications = ($definitions['authentications'][0])($container);
		$this->assertCount(4, $authentications);
		foreach ($authentications as $factory) {
			$this->assertInstanceOf(\Closure::class, $factory);
		}
	}

	private function registerServices(): array {
		$definitions = [];
		$container = $this->createStub(IContainer::class);
		$container->method('set')->willReturnCallback(static function(string $key, mixed $value, int $flags = 0) use (&$definitions, $container): IContainer {
			$definitions[$key] = [$value, $flags];
			return $container;
		});
		(new Base3ChatWebsitePlugin($container))->init();
		return $definitions;
	}
}
