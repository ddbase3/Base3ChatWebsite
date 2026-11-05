<?php declare(strict_types=1);

namespace Base3ChatWebsite;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Api\IPlugin;
use Base3\Core\BaseAssetResolver;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use Base3\Database\Mysql\MysqlDatabase;
use Base3\Language\Api\ILanguage;
use Base3\Language\MultiLang\MultiLang;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use Base3\Logger\LoggerProxy;
use Base3\Microservice\Api\IMicroserviceFlags;
use Base3\Microservice\Microservice\MicroserviceHelper;
use Base3\Microservice\Extern\MicroserviceExternHelper;
use Base3\Middleware\Session\SessionMiddleware;
use Base3\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\Page\Api\IPage;
use Base3\ServiceSelector\Routing\RoutingServiceSelector;
use Base3\Session\Api\ISession;
use Base3\Session\BasicSession\BasicSession;
use Base3\Settings\Api\ISettingsStore;
use Base3\Settings\Database\DatabaseSettingsStore;
use Base3\Token\Api\IToken;
use Base3\Token\TokenProxy;
use Base3\Token\FileToken\FileToken;
use Base3\Usermanager\Api\IUsermanager;
use Base3ChatWebsite\Content\Navigation;
use ResourceFoundation\Api\IQueryService;
use DataHawk\Service\DataHawkMicroservice;

class Base3ChatWebsitePlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase
	
	public static function getName(): string {
		return 'base3chatwebsiteplugin';
	}

	// Implementation of IPlugin

	public function init()  {

		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

                        ->set('session', fn($c) => new BasicSession($c->get(IConfiguration::class)), IContainer::SHARED)
                        ->set(ISession::class, 'session', IContainer::ALIAS)

			->set('middlewares', fn($c) => [
                                new SessionMiddleware($c->get(ISession::class)),
                                new AccesscontrolMiddleware($c->get(IAccesscontrol::class))
                        ])

			/*
                        ->set('routes', fn() => [
                                \Base3\Route\Cli\CliRoute::class,
                                \Base3\Route\GenericOutput\GenericOutputRoute::class,
                                \ModuledPage\Route\ModuledPageRoute::class,
                        ])

                        ->set('serviceselector', fn($c) => new RoutingServiceSelector($c), IContainer::SHARED)
			*/

                        ->set('microservicehelper', fn() => new MicroserviceHelper(), IContainer::SHARED)
                        ->set('microserviceserializedhelper', fn($c) => new MicroserviceHelper(IMicroserviceFlags::SERIALIZED), IContainer::SHARED)

                        ->set('crypt', fn() => new \Base3\Crypt\Openssl\OpensslCrypt)

                        ->set('language', fn($c) => new MultiLang($c->get(IConfiguration::class), $c->get(ISession::class)), IContainer::SHARED)
                        ->set(ILanguage::class, 'language', IContainer::ALIAS)

			->set(IDatabase::class, fn($c) => new MysqlDatabase($c->get(IConfiguration::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set('database', IDatabase::class, IContainer::ALIAS)

                        ->set('loginpage', fn($c) => $c->get('microservicehelper')->get('Account', IPage::class, 'loginpagemicroservice'), IContainer::SHARED)

                        ->set('authtoken', fn() => new FileToken)
                        ->set(IToken::class, 'authtoken', IContainer::ALIAS)
                        ->set('ssotoken', fn($c) => new TokenProxy($c->get('microservicehelper')->get('Account', IToken::class, 'authtokenmicroservice')), IContainer::SHARED)

                        ->set('authentications', fn($c) => [
                                fn() => new \Base3\Accesscontrol\Authentication\CliAuth,
                                fn() => new \Base3\Accesscontrol\Authentication\SingleAuth,
                                fn() => new \Base3\Accesscontrol\Authentication\SessionAuth($c->get(ISession::class)),
                                fn() => new \Base3\Accesscontrol\Authentication\InternalHmacAuth,
                                // fn() => new \Base3\Accesscontrol\Authentication\SingleSignOnAuth($c->get('ssotoken')),
                                // fn() => new \Base3\Accesscontrol\Authentication\SingleSignOnAutoAuth($c->get(IConfiguration::class), $c->get(ISession::class), $c),
                                // fn() => new \Base3\Accesscontrol\Authentication\ContinueAuth
                        ])

                        ->set('accesscontrol', fn($c) => new SelectedAccesscontrol($c->get('authentications')), IContainer::SHARED)
                        ->set(IAccesscontrol::class, 'accesscontrol', IContainer::ALIAS)

                        ->set('usermanager', fn($c) => $c->get('microservicehelper')->get('Account', IUsermanager::class, 'usermanagermicroservice'), IContainer::SHARED)

                        ->set(IAssetResolver::class, fn() => new BaseAssetResolver, IContainer::SHARED)

			->set(ISettingsStore::class, fn($c) => new DatabaseSettingsStore($c->get(IDatabase::class)), IContainer::SHARED)

			->set(
				IQueryService::class,
				fn($c) => new DataHawkMicroservice(
					$c->get('microserviceserializedhelper')->get('Data', IQueryService::class, 'datahawkmicroservice')),
				IContainer::SHARED)

                        ->set('navigation', fn($c) => new Navigation($c->get(IMvcView::class), $c->get(IAccesscontrol::class), $c->get(ILinkTargetService::class)), IContainer::SHARED)
                        ->set('logo', 'plugin/Base3ChatWebsite/assets/logo/logo.svg', IContainer::PARAMETER);
	}
}
