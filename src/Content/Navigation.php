<?php declare(strict_types=1);

namespace Base3ChatWebsite\Content;

use Base3\Api\IMvcView;
use Base3\Api\IOutput;
use Base3\Core\ServiceLocator;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Usermanager\Api\IUsermanager;
use Base3\LinkTarget\Api\ILinkTargetService;

class Navigation implements IOutput {

        // protected $servicelocator;
        // private $usermanager;

        public function __construct(
                private readonly IMvcView $view,
		private readonly IAccesscontrol $accesscontrol,
		private readonly IUsermanager $usermanager,
		private readonly ILinkTargetService $linktargetservice
        ) {
                // $this->servicelocator = ServiceLocator::getInstance();
                // $this->usermanager = $this->servicelocator->get('usermanager');
        }

        // Implementation of IBase

        public static function getName(): string {
                return 'navigation';
        }

        // Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(DIR_PLUGIN . 'Base3ChatWebsite');
                $this->view->setTemplate('Content/Navigation.php');
                $this->view->assign('authenticated', !!$this->accesscontrol->getUserId());
		$this->view->assign('getlink', fn($t, $p = []) => $this->linktargetservice->getLink($t, $p));

                $user = $this->usermanager->getUser();
                $this->view->assign("role", $user == null ? "" : ((object) $user)->role);

                return $this->view->loadTemplate();
        }

        public function getHelp(): string {
                return 'Help of Navigation' . "\n";
        }
}
