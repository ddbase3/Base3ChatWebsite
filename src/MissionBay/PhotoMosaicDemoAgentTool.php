<?php declare(strict_types=1);

namespace Base3ChatWebsite\MissionBay;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;

/**
 * PhotoMosaicDemoAgentTool
 *
 * Opens the chatbot canvas and renders a large iframe pointing to:
 * https://mosaic-creator.de/mosaicgallery.php
 */
class PhotoMosaicDemoAgentTool extends AbstractAgentResource implements IAgentTool {

	public static function getName(): string {
		return 'photomosaicdemoagenttool';
	}

	public function getDescription(): string {
		return 'Shows the Photo Mosaic demo page inside the chatbot canvas (iframe).';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Photo Mosaic Demo',
			'category' => 'demo',
			'tags' => ['demo', 'canvas', 'image', 'mosaic'],
			'priority' => 50,
			'function' => [
				'name' => 'open_photo_mosaic_demo',
				'description' => 'Opens the canvas and shows https://mosaic-creator.de/mosaicgallery.php in a large iframe.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'canvas_id' => [
							'type' => 'string',
							'description' => 'Target canvas id (default: "main").'
						],
						'title' => [
							'type' => 'string',
							'description' => 'Canvas title (default: "Photo Mosaic Demo").'
						],
						'open' => [
							'type' => 'boolean',
							'description' => 'Whether to open/focus the canvas (default: true).'
						]
					]
				]
			]
		]];
	}

	public function callTool(string $toolName, array $arguments, IAgentContext $context): array {
		if ($toolName !== 'open_photo_mosaic_demo') {
			throw new \InvalidArgumentException("Unsupported tool: $toolName");
		}

		$canvasId = trim((string)($arguments['canvas_id'] ?? 'main'));
		if ($canvasId === '') $canvasId = 'main';

		$title = trim((string)($arguments['title'] ?? 'Photo Mosaic Demo'));
		if ($title === '') $title = 'Photo Mosaic Demo';

		$open = $arguments['open'] ?? true;
		$open = filter_var($open, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
		if ($open === null) $open = true;

		$url = 'https://mosaic-creator.de/mosaicgallery.php?embed';

		$stream = $context->getVar('eventstream');
		if (!$stream) {
			return [
				'ok' => false,
				'error' => 'Missing eventstream in context.'
			];
		}

		// Big, responsive iframe that fills the canvas content area nicely
		$html = ''
			. '<div class="canvas-iframe-wrap" style="height:550px; min-height:420px;">'
			. '  <iframe'
			. '    src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
			. '    title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
			. '    style="width:100%; height:100%; min-height:420px; border:0; border-radius:10px; background:#fff;"'
			. '    loading="lazy"'
			. '    referrerpolicy="no-referrer-when-downgrade"'
			. '    allow="fullscreen"'
			. '  ></iframe>'
			. '</div>';

		try {
			if ($open && !$stream->isDisconnected()) {
				$stream->push('canvas.open', [
					'id' => $canvasId,
					'title' => $title,
					'focus' => true
				]);
			}

			if (!$stream->isDisconnected()) {
				$stream->push('canvas.render', [
					'id' => $canvasId,
					'mode' => 'replace',
					'title' => $title,
					'blocks' => [[
						'type' => 'html',
						'html' => $html,
						'sanitize' => false
					]]
				]);
			}
		} catch (\Throwable $e) {
			return [
				'ok' => false,
				'error' => 'Failed to push canvas events: ' . $e->getMessage()
			];
		}

		return [
			'ok' => true,
			'canvas_id' => $canvasId,
			'url' => $url
		];
	}
}
