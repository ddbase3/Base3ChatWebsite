<?php declare(strict_types=1);

namespace Base3ChatWebsite;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
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
use Base3\Middleware\Session\SessionMiddleware;
use Base3\Middleware\Accesscontrol\AccesscontrolMiddleware;
use Base3\Page\Api\IPage;
use Base3\ServiceSelector\Routing\RoutingServiceSelector;
use Base3\Session\Api\ISession;
use Base3\Session\BasicSession\BasicSession;
use Base3\Settings\Api\ISettingsStore;
use Base3\Settings\Database\DatabaseSettingsStore;
use Base3\Token\Api\IToken;
use Base3\Token\FileToken\FileToken;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Base3System\Base3SystemAuthorizationService;
use Base3\Usermanager\UserCriterionEvaluator;
use Base3Template\Authorization\AudienceCatalog;
use Base3Template\Authorization\AudienceEditor;
use Base3Template\Authorization\FeatureAuthorizationService;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Accesscontrol\Api\IAuthentication;
use Base3Template\Authentication\XrmAuthentication;
use Base3Template\Usermanager\XrmUsermanager;
use KeyHarborClient\Microservice\KeyHarborConnector;
use Base3Tools\Status\File\FileStatusHandler;
use Base3ChatWebsite\Content\Navigation;
use Base3ChatWebsite\Chat\GlobalChatbotRenderOutput;
use Base3ChatWebsite\FileManager\ChatbotFileManagerOutput;
use ClientStack\FileManager\FileManagerHttpService;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IManagedFileStorageService;
use FileBridge\Local\LocalManagedFileStorageService;
use Base3\Event\Api\IEventManager;
use ClientStack\FileManager\FileUploadService;
use DataHawk\Service\DataHawkMicroservice;
use RuntimeException;

class Base3ChatWebsitePlugin implements IPlugin {

	private const FILE_MANAGER_SUBDIRECTORY = 'filemanager';

	private static function getFileManagerRoot(IConfiguration $configuration): string {
		$dataDirectory = rtrim(trim($configuration->getString('directories', 'data', '')), DIRECTORY_SEPARATOR);
		if ($dataDirectory === '' || !str_starts_with($dataDirectory, DIRECTORY_SEPARATOR)) {
			throw new RuntimeException('Missing absolute BASE3 data directory configuration: [directories] data.');
		}
		return $dataDirectory . DIRECTORY_SEPARATOR . self::FILE_MANAGER_SUBDIRECTORY;
	}

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase
	
	public static function getName(): string {
		return 'base3chatwebsiteplugin';
	}

	// Implementation of IPlugin

	public function init()  {

		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			// PageHeader opts this website into the shared ClientStack launcher.
			->set('globalChatbotOutputName', GlobalChatbotRenderOutput::getName(), IContainer::PARAMETER)
			->set('globalChatbotConfigGroup', GlobalChatbotRenderOutput::CONFIG_GROUP, IContainer::PARAMETER)
			->set('globalChatbotConfigName', GlobalChatbotRenderOutput::CONFIG_NAME, IContainer::PARAMETER)

			// Use the existing Chatbot SettingsTab display data for project-owned resources.
			->set('chatbotConfigDisplayData', fn($c) => [
				'group' => GlobalChatbotRenderOutput::CONFIG_GROUP,
				'name' => GlobalChatbotRenderOutput::CONFIG_NAME,
				'resources' => [
					'enabled' => true,
					'max_file_size' => FileManagerHttpService::DEFAULT_MAX_FILE_SIZE,
					'endpoints' => ChatbotFileManagerOutput::buildEndpoints($c->get(ILinkTargetService::class))
				]
			], IContainer::SHARED)

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

                        ->set('loginpage', fn($c) => new \Base3Template\Page\Login(
				$c->get(IClassMap::class), $c->get(IMvcView::class), $c->get(ILanguage::class)
			), IContainer::SHARED)

                        ->set('authtoken', fn() => new FileToken)
                        ->set(IToken::class, 'authtoken', IContainer::ALIAS)

                        ->set('xrmauthenticationconnector', fn($c) => new KeyHarborConnector(
                                rtrim($c->get(IConfiguration::class)->getString('xrm', 'url'), '/') . '/xrmauthenticationmicroservice.json',
                                IAuthentication::class,
                                $c->get(IConfiguration::class), 'xrm', 'token', ['login' => '?string']
                        ), IContainer::SHARED)
                        ->set('xrmusermanagerconnector', fn($c) => new KeyHarborConnector(
                                rtrim($c->get(IConfiguration::class)->getString('xrm', 'url'), '/') . '/xrmusermanagermicroservice.json',
                                IUsermanager::class,
                                $c->get(IConfiguration::class), 'xrm', 'token', ['getUserById' => '?array', 'searchUsers' => 'list', 'getAllRoles' => 'list']
                        ), IContainer::SHARED)
                        ->set('xrmauthentication', fn($c) => new XrmAuthentication($c->get('xrmauthenticationconnector')), IContainer::SHARED)
                        ->set('xrmusermanager', fn($c) => new XrmUsermanager($c->get('xrmusermanagerconnector'), $c->get(IAccesscontrol::class)), IContainer::SHARED)

                        ->set('authentications', fn($c) => [
                                fn() => new \Base3\Accesscontrol\Authentication\CliAuth,
                                fn() => new \Base3\Accesscontrol\Authentication\SessionAuth($c->get(ISession::class)),
                                fn() => $c->get('xrmauthentication')
                        ])

                        ->set('accesscontrol', fn($c) => new SelectedAccesscontrol($c->get('authentications')), IContainer::SHARED)
                        ->set(IAccesscontrol::class, 'accesscontrol', IContainer::ALIAS)

                        ->set(IUsermanager::class, 'xrmusermanager', IContainer::ALIAS)
                        ->set('usermanager', IUsermanager::class, IContainer::ALIAS)
                        // XRM remains the identity and native grant owner. The shared
                        // feature evaluator reads this site's own declared audiences.
                        ->set(AudienceEditor::class, fn($c) => new AudienceEditor(
                                $c->get(IClassMap::class), $c->get(UserCriterionEvaluator::class),
                                $c->get(IUsermanager::class), 'xrmrolecriterion'
                        ), IContainer::SHARED)
                        ->set(IAuthorizationService::class, fn($c) => new FeatureAuthorizationService(
                                new Base3SystemAuthorizationService($c->get(IUsermanager::class)),
                                $c->get(AudienceCatalog::class), $c->get(UserCriterionEvaluator::class),
                                $c->get(IUsermanager::class)
                        ), IContainer::SHARED)


                        ->set(IAssetResolver::class, fn() => new BaseAssetResolver, IContainer::SHARED)

			->set(IManagedFileStorageService::class, fn($c) => new LocalManagedFileStorageService(
				$c->get(IConfiguration::class),
				$c->get(IEventManager::class),
				self::getFileManagerRoot($c->get(IConfiguration::class))
			), IContainer::SHARED)
			->set(FileUploadService::class, fn($c) => new FileUploadService(
				$c->get(IManagedFileStorageService::class),
				self::getFileManagerRoot($c->get(IConfiguration::class)) . DIRECTORY_SEPARATOR . 'uploads'
			), IContainer::SHARED)
			->set(ISettingsStore::class, fn($c) => new DatabaseSettingsStore($c->get(IDatabase::class)), IContainer::SHARED)

			->set(
				IQueryService::class,
				fn($c) => new DataHawkMicroservice(
					$c->get('microserviceserializedhelper')->get('Data', IQueryService::class, 'datahawkmicroservice')),
				IContainer::SHARED)

                        ->set('statushandler', fn($c) => new FileStatusHandler($c->get(IAccesscontrol::class)), IContainer::SHARED)

                        ->set('navigation', fn($c) => new Navigation($c->get(IMvcView::class), $c->get(IAccesscontrol::class), $c->get(IUsermanager::class), $c->get(ILinkTargetService::class)), IContainer::SHARED)
                        ->set('logo', 'plugin/Base3ChatWebsite/assets/logo/logo.svg', IContainer::PARAMETER);
	}
}
