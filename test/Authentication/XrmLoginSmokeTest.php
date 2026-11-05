<?php declare(strict_types=1);

/** Standalone: php plugin/Base3ChatWebsite/test/Authentication/XrmLoginSmokeTest.php */

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3Template\Content\LoginBlock;
use Base3\Accesscontrol\Authentication\SessionAuth;
use Base3\Accesscontrol\Selected\SelectedAccesscontrol;
use Base3\Microservice\Api\IMicroserviceConnector;
use Base3\Usermanager\Role;
use Base3Template\Authentication\XrmAuthentication;
use Base3Template\Usermanager\XrmUsermanager;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_SRC' => $root . '/src/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/', 'DIR_CNF' => $root . '/cnf/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_SRC . 'Core/Autoloader.php';
Base3\Core\Autoloader::register();

function assertXrmIdentity(bool $ok, string $message): void {
	if (!$ok) throw new RuntimeException($message);
}

final class ChatTestConnector implements IMicroserviceConnector {
	public array $calls = [];
	public bool $fail = false;
	public function getMicroserviceUrl() { return 'https://xrm.example.test/test.json'; }
	public function getProxiedInstance(): object { return $this; }
	public function __call($method, $args) {
		$this->calls[] = [$method, $args];
		if ($this->fail) throw new RuntimeException('Service unavailable.');
		if ($method === 'login') return $args[0] === ['username' => 'alice', 'password' => 'correct'] ? 'alice' : null;
		if ($method === 'getUserById') {
			return $args[0] === 'alice' ? [
				'id' => 42, 'userid' => 'alice', 'name' => 'Alice', 'role' => 'member',
				'roles' => [['id' => 3, 'name' => 'member']],
				'groups' => [['id' => 5, 'name' => 'editors']],
				'permissions' => [['id' => 8, 'scope' => 'blog', 'permission' => 'view']]
			] : null;
		}
		throw new RuntimeException('Unknown test operation.');
	}
}

final class ChatTestSession implements \Base3\Session\Api\ISession {
	public function start(): bool { return true; }
	public function started(): bool { return true; }
	public function getId(): string { return session_id(); }
	public function destroy(): bool { $_SESSION = []; return true; }
	public function has(string $key): bool { return array_key_exists($key, $_SESSION); }
	public function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
	public function set(string $key, mixed $value): void { $_SESSION[$key] = $value; }
	public function remove(string $key): void { unset($_SESSION[$key]); }
}

final class ChatTestAccesscontrol implements IAccesscontrol {
	public function __construct(public mixed $id = null) {}
	public function getUserId() { return $this->id; }
	public function authenticate(): void {}
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION = [];
$connector = new ChatTestConnector();
$auth = new XrmAuthentication($connector);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['login' => '1', 'username' => 'alice', 'password' => 'correct', 'login_csrf' => 'wrong'];
$_SESSION[LoginBlock::CSRF_SESSION_KEY] = 'expected';
assertXrmIdentity($auth->login() === null && !$connector->calls, 'CSRF bypassed.');
$_SESSION['authentication'] = ['userid' => 'previous'];
$auth->logout();
assertXrmIdentity($_SESSION['authentication']['userid'] === 'previous', 'Invalid CSRF cleared an established session.');
unset($_SESSION['authentication']);

$_POST['login_csrf'] = 'expected';
$_POST['password'] = 'wrong';
assertXrmIdentity($auth->login() === null, 'Invalid password accepted.');
assertXrmIdentity($connector->calls[0] === ['login', [['username' => 'alice', 'password' => 'wrong']]], 'Wrong remote call.');
$_POST['password'] = 'correct';
assertXrmIdentity($auth->login() === 'alice', 'Valid remote login rejected.');
assertXrmIdentity(!isset($_SESSION[LoginBlock::CSRF_SESSION_KEY]), 'CSRF key remained after login.');

$_SESSION['authentication'] = ['userid' => 'old'];
$_SESSION[LoginBlock::CSRF_SESSION_KEY] = 'expected';
$auth->logout();
assertXrmIdentity(!isset($_SESSION['authentication']), 'New login retained an old session.');

$_SESSION[LoginBlock::CSRF_SESSION_KEY] = 'expected';
$connector->fail = true;
try {
	$auth->login();
	throw new RuntimeException('Service failure was silently accepted.');
} catch (RuntimeException $e) {
	assertXrmIdentity($e->getMessage() === 'Service unavailable.', 'Wrong failure cause.');
}
$connector->fail = false;

$access = new ChatTestAccesscontrol('alice');
$manager = new XrmUsermanager($connector, $access);
$user = $manager->getUser();
assertXrmIdentity($user?->userid === 'alice' && $user?->role === 'member', 'XRM identity missing.');
assertXrmIdentity(count($manager->getGroups()) === 1 && $manager->getGroups()[0]->name === 'editors', 'XRM group missing.');
assertXrmIdentity(count($manager->getRoles()) === 1 && $manager->getRoles()[0] instanceof Role, 'XRM role was not hydrated.');
assertXrmIdentity($manager->hasRole(Role::named('member')), 'Native role missing.');
assertXrmIdentity($manager->getPermissions()[0]->permission === 'view', 'XRM permission metadata missing.');
$access->id = null;
assertXrmIdentity($manager->getUser() === null, 'Anonymous actor retained identity.');

try {
	$manager->getAllUsers();
	throw new RuntimeException('User enumeration was enabled.');
} catch (RuntimeException $e) {
	assertXrmIdentity($e->getMessage() === 'Unknown test operation.', 'Unexpected remote catalog response.');
}

// SelectedAccesscontrol must persist only the verified XRM login in the native Chat session.
$_SESSION = [LoginBlock::CSRF_SESSION_KEY => 'expected'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['login' => '1', 'username' => 'alice', 'password' => 'correct', 'login_csrf' => 'expected'];
$_REQUEST = $_POST;
$select = new SelectedAccesscontrol([
	new SessionAuth(new ChatTestSession()),
	new XrmAuthentication($connector)
]);
assertXrmIdentity($select->getUserId() === 'alice', 'SelectedAccesscontrol did not authenticate XRM login.');
assertXrmIdentity(($_SESSION['authentication']['userid'] ?? null) === 'alice', 'Chat session was not updated.');
$_POST = [];
$_REQUEST = ['logout' => 1];
$select = new SelectedAccesscontrol([
	new SessionAuth(new ChatTestSession()),
	new XrmAuthentication($connector)
]);
assertXrmIdentity($select->getUserId() === null, 'Chat logout retained the user.');
assertXrmIdentity(!isset($_SESSION['authentication']), 'Chat logout retained the session identity.');

$_SESSION = [];
session_write_close();
echo "XRM identity login and usermanager smoke: OK\n";
