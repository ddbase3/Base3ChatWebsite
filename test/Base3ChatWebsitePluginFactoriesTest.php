<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Permission;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Template\Authorization\AudienceCatalog;
use Base3Template\Authorization\FeatureAuthorizationService;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Accesscontrol\Api\IAuthentication;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Configuration\Api\IConfiguration;
use Base3\Core\BaseAssetResolver;
use Base3\Core\ServiceLocator;
use Base3\Crypt\Openssl\OpensslCrypt;
use Base3\Database\Api\IDatabase;
use Base3\Database\Mysql\MysqlDatabase;
use Base3\Language\Api\ILanguage;
use Base3\Language\MultiLang\MultiLang;
use Base3\Microservice\Microservice\MicroserviceHelper;
use Base3\Microservice\Api\IMicroserviceConnector;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Settings\Database\DatabaseSettingsStore;
use Base3\Token\Api\IToken;
use Base3\Api\IClassMap;
use Base3Template\Authentication\XrmAuthentication;
use Base3Template\Usermanager\XrmUsermanager;
use Base3\Usermanager\Api\IUsermanager;
use Base3ChatWebsite\Content\Navigation;
use Base3\Session\Api\ISession;
use Base3ChatWebsite\Base3ChatWebsitePlugin;
use PHPUnit\Framework\TestCase;

final class Base3ChatWebsitePluginFactoriesTest extends TestCase {

	public function testLanguageAndDatabaseFactoriesUseTheContainerServices(): void {
		$definitions = $this->definitions();
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn([]);
		$session = $this->createStub(ISession::class);
		$container = $this->container([
			IConfiguration::class => $configuration,
			ISession::class => $session
		]);

		$language = ($definitions['language'][0])($container);
		$this->assertInstanceOf(MultiLang::class, $language);
		$this->assertSame([], $language->getLanguages());
		$this->assertSame('language', $definitions[ILanguage::class][0]);

		$database = ($definitions[IDatabase::class][0])($container);
		$this->assertInstanceOf(MysqlDatabase::class, $database);
		$this->assertFalse($database->connected());
		$this->assertSame(IDatabase::class, $definitions['database'][0]);
	}

	public function testCryptAndAssetFactoriesDoNotRequireApplicationDatabase(): void {
		$definitions = $this->definitions();
		$this->assertInstanceOf(OpensslCrypt::class, ($definitions['crypt'][0])());
		$assetResolver = ($definitions[IAssetResolver::class][0])();
		$this->assertInstanceOf(BaseAssetResolver::class, $assetResolver);
		$this->assertSame('./plugin/Base3ChatWebsite/assets/logo/logo.svg', $assetResolver->resolve($definitions['logo'][0]));
	}

	public function testMicroserviceFactoriesUseExistingServiceLocator(): void {
		$definitions = $this->definitions();
		$original = ServiceLocator::getInstance();
		$locator = new ServiceLocator();
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn([]);
		$locator->set('configuration', $configuration, IContainer::SHARED);
		ServiceLocator::useInstance($locator);
		try {
			$this->assertInstanceOf(MicroserviceHelper::class, ($definitions['microservicehelper'][0])());
			$this->assertInstanceOf(MicroserviceHelper::class, ($definitions['microserviceserializedhelper'][0])($this->container([])));
		} finally {
			ServiceLocator::useInstance($original);
		}
	}

	public function testAuthenticationCollectionBuildsOnlyFrameworkStrategies(): void {
		$definitions = $this->definitions();
		$original = ServiceLocator::getInstance();
		$locator = new ServiceLocator();
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn([]);
		$locator->set('configuration', $configuration, IContainer::SHARED);
		ServiceLocator::useInstance($locator);
		try {
			$session = $this->createStub(ISession::class);
			$container = $this->container([ISession::class => $session, 'xrmauthentication' => new XrmAuthentication($this->createStub(IMicroserviceConnector::class))]);
			$factories = ($definitions['authentications'][0])($container);
			$names = [];
			foreach ($factories as $factory) {
				$authentication = $factory();
				$this->assertInstanceOf(IAuthentication::class, $authentication);
				$names[] = $authentication::getName();
			}
			$this->assertSame(['cliauth', 'sessionauth', 'xrmauthentication'], $names);
			$accesscontrol = ($definitions['accesscontrol'][0])($this->container(['authentications' => $factories]));
			$this->assertInstanceOf(SelectedAccesscontrol::class, $accesscontrol);
			$this->assertSame('accesscontrol', $definitions[IAccesscontrol::class][0]);
		} finally {
			ServiceLocator::useInstance($original);
		}
	}


	public function testIdentityServiceUsesOnlyExistingTemplateAndKeyHarborClient(): void {
		$definitions = $this->definitions();
		$this->assertArrayHasKey('xrmauthenticationconnector', $definitions);
		$this->assertArrayHasKey('xrmusermanagerconnector', $definitions);
		$this->assertArrayNotHasKey('ssotoken', $definitions);
		$this->assertSame('xrmusermanager', $definitions[IUsermanager::class][0]);
		$this->assertSame(IContainer::ALIAS, $definitions[IUsermanager::class][1]);
	}

	public function testSettingsAuthorizationUsesSharedTemplateAndXrmGrantMetadata(): void {
		$definitions = $this->definitions();
		$this->assertArrayHasKey(IAuthorizationService::class, $definitions);
		$users = $this->createStub(IUsermanager::class);
		$users->method('getPermissions')->willReturn([]);
		$evaluator = new UserCriterionEvaluator($this->createStub(IClassMap::class));
		$catalog = new AudienceCatalog($this->createStub(ISettingsStore::class), $this->createStub(IClassMap::class), $evaluator);
		$service = ($definitions[IAuthorizationService::class][0])($this->container([
			IUsermanager::class => $users,
			AudienceCatalog::class => $catalog,
			UserCriterionEvaluator::class => $evaluator
		]));
		$this->assertInstanceOf(FeatureAuthorizationService::class, $service);
		$this->assertFalse($service->decide(new AuthorizationRequest('base3', Permission::for('system', 'admin')))->isAllowed());
	}

	public function testSettingsStoreFactoryUsesTheConfiguredDatabaseWithoutConnectingExternally(): void {
		$definitions = $this->definitions();
		$queries = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('connect')->willReturnCallback(static function() use (&$queries): void {
			$queries[] = 'connect';
		});
		$database->method('nonQuery')->willReturnCallback(function(string $query) use (&$queries): void {
			$this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `base3_settingsstore`', $query);
			$queries[] = 'create';
		});
		$database->method('isError')->willReturn(false);
		$store = ($definitions[ISettingsStore::class][0])($this->container([IDatabase::class => $database]));
		$this->assertInstanceOf(DatabaseSettingsStore::class, $store);
		$this->assertSame(['connect', 'connect', 'create'], $queries);
	}

	public function testNavigationFactoryUsesConfiguredViewAndServices(): void {
		$definitions = $this->definitions();
		$view = $this->createStub(IMvcView::class);
		$view->method('loadTemplate')->willReturn('navigation');
		$accesscontrol = $this->createStub(IAccesscontrol::class);
		$usermanager = $this->createStub(IUsermanager::class);
		$usermanager->method('getUser')->willReturn(null);
		$links = $this->createStub(ILinkTargetService::class);
		$navigation = ($definitions['navigation'][0])($this->container([
			IMvcView::class => $view,
			IAccesscontrol::class => $accesscontrol,
			IUsermanager::class => $usermanager,
			ILinkTargetService::class => $links
		]));
		$this->assertInstanceOf(Navigation::class, $navigation);
		$this->assertSame('navigation', $navigation->getOutput());
	}

	private function definitions(): array {
		$definitions = [];
		$container = $this->createStub(IContainer::class);
		$container->method('set')->willReturnCallback(static function(string $key, mixed $value, int $flags = 0) use ($container, &$definitions): IContainer {
			$definitions[$key] = [$value, $flags];
			return $container;
		});
		(new Base3ChatWebsitePlugin($container))->init();
		return $definitions;
	}

	private function container(array $services): IContainer {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->willReturnCallback(static fn(string $name): mixed => $services[$name] ?? null);
		return $container;
	}
}
