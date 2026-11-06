<?php declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

$source = dirname(__DIR__, 3);
$privateRoot = sys_get_temp_dir() . '/base3-data-storage-' . bin2hex(random_bytes(8));
$webRoot = $privateRoot . '/html';
$dataDirectory = $privateRoot . '/data';
mkdir($webRoot, 0700, true);
mkdir($dataDirectory, 0700, true);
define('DIR_ROOT', $webRoot . DIRECTORY_SEPARATOR);

spl_autoload_register(static function(string $class) use ($source): void {
	$maps = [
		'Base3\\' => $source . '/src/',
		'Base3ChatWebsite\\' => $source . '/plugin/Base3ChatWebsite/src/',
		'FileBridge\\' => $source . '/plugin/FileBridge/src/',
		'ClientStack\\' => $source . '/plugin/ClientStack/src/',
		'ResourceFoundation\\' => $source . '/plugin/ResourceFoundation/src/'
	];
	foreach ($maps as $prefix => $path) {
		if (!str_starts_with($class, $prefix)) continue;
		$path .= str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
		if (is_file($path)) require $path;
		return;
	}
});

try {
	$c = new \Base3\Core\ServiceLocator();
	$configuration = new \Base3\Configuration\ArrayConfiguration();
	$configuration->setValue('directories', 'data', $dataDirectory);
	$c->set(\Base3\Configuration\Api\IConfiguration::class, $configuration, \Base3\Api\IContainer::SHARED);
	$c->set(\Base3\Event\Api\IEventManager::class, new \Base3\Event\EventManager(), \Base3\Api\IContainer::SHARED);
	(new \Base3ChatWebsite\Base3ChatWebsitePlugin($c))->init();
	$s = $c->get(\ResourceFoundation\Api\IManagedFileStorageService::class);
	$u = $c->get(\ClientStack\FileManager\FileUploadService::class);
	$mode = \ResourceFoundation\Api\IManagedFileStorageService::MODE_COLLECTION;
	$fileStorage = $s->openOrCreate('chatbot', 'default', $mode);
	if (!$fileStorage->write('direct.txt','private data')) throw new \RuntimeException('Storage write failed');
	if ($fileStorage->read('direct.txt') !== 'private data') throw new \RuntimeException('Storage read failed');
	$ownedFiles = glob($dataDirectory . '/filemanager/owners/*/direct.txt');
	if (count($ownedFiles) !== 1) throw new \RuntimeException('Storage path not under data/filemanager/owners');
	$session = $u->start('chatbot', 'default', $mode, 'actor1', 2048,
		['name'=>'upload.txt','path'=>'','size'=>3,'chunkSize'=>3,'totalChunks'=>1]);
	$uploadId = $session['uploadId'];
	$tmp = tempnam(sys_get_temp_dir(),'b3chk');
	file_put_contents($tmp,'xyz');
	$u->saveChunk('chatbot','default',$mode,'actor1',['uploadId'=>$uploadId,'index'=>0],
		['chunk'=>['error'=>UPLOAD_ERR_OK,'tmp_name'=>$tmp]]);
	unlink($tmp);
	$sessions = glob($dataDirectory . '/filemanager/uploads/*/' . $uploadId . '/meta.json');
	if (count($sessions) !== 1) throw new \RuntimeException('Upload session not in data/filemanager/uploads');
	$u->finish('chatbot','default',$mode,'actor1',$uploadId);
	if ($fileStorage->read('upload.txt') !== 'xyz') throw new \RuntimeException('Upload content failed');
	if (is_dir($webRoot . '/data')) throw new \RuntimeException('Storage was placed beneath the public root');
	if (glob($webRoot . '/userfiles/filemanager/*')) throw new \RuntimeException('Unexpected legacy files');
	$missingConfig = new \Base3\Configuration\ArrayConfiguration();
	$missingContainer = new \Base3\Core\ServiceLocator();
	$missingContainer->set(\Base3\Configuration\Api\IConfiguration::class, $missingConfig, \Base3\Api\IContainer::SHARED);
	$missingContainer->set(\Base3\Event\Api\IEventManager::class, new \Base3\Event\EventManager(), \Base3\Api\IContainer::SHARED);
	(new \Base3ChatWebsite\Base3ChatWebsitePlugin($missingContainer))->init();
	try {
		$missingContainer->get(\ResourceFoundation\Api\IManagedFileStorageService::class);
		throw new \RuntimeException('Missing data directory configuration was accepted');
	} catch (\RuntimeException $exception) {
		if (!str_contains($exception->getMessage(), '[directories] data')) throw $exception;
	}
	echo "PASS: FileBridge and ClientStack use configured [directories] data outside DIR_ROOT\n";
} finally {
	$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($privateRoot,\FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $file) {
		if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
		else unlink($file->getPathname());
	}
	rmdir($privateRoot);
}
