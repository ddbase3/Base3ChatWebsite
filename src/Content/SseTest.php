<?php declare(strict_types=1);

namespace Base3ChatWebsite\Content;

use Base3\Api\IMvcView;
use Base3\Api\IOutput;

class SseTest implements IOutput {

	public function __construct(
		private readonly IMvcView $view
	) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'ssetest';
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(DIR_PLUGIN . 'Base3ChatWebsite');
		$this->view->setTemplate('Content/SseTest.php');

		$this->view->assign('example', 0);

		return $this->view->loadTemplate();
	}

	public function getHelp(): string {
		return 'Help of SseTest' . "\n";
	}
}
