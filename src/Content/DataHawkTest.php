<?php declare(strict_types=1);

namespace Base3ChatWebsite\Content;

use Base3\Api\IOutput;
use ResourceFoundation\Api\IQueryService;

class DataHawkTest implements IOutput {

	private readonly IQueryService $qs;

	public function __construct(IQueryService $qs) {
		$this->qs = $qs;
	}

	// Implementation of IBase

	public static function getName(): string {
		return 'datahawktest';
	}

	// Implementation of IOutput

	public function getOutput(string $out = 'html', bool $final = false): string {
		// Build a simple JSON-based DataHawk query
		$query = [
			'type' => 'select',
			'table' => 'git_repository',
			'fields' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'git_repository',
						'field' => 'name'
					],
					'alias' => 'repository_name'
				],
				[
					'element' => [
						'type' => 'fld',
						'table' => 'git_repository',
						'field' => 'full_name'
					],
					'alias' => 'full_repo_name'
				]
			]
		];

		try {
			$result = $this->qs->executeQuery($query);
		} catch (\Throwable $e) {
			return '<b>DataHawkTest Error:</b> ' . htmlspecialchars($e->getMessage());
		}

		return $this->renderHtml($result->rows, $result->columns);
	}

	public function getHelp(): string {
		return "Help of DataHawkTest\n";
	}

	/**
	 * Renders a minimal HTML table for testing.
	 */
	private function renderHtml(array $rows, array $columns): string {
		$html = "<h2>DataHawk Test Output</h2>";
		$html .= "<table border=\"1\" cellpadding=\"6\" cellspacing=\"0\">";

		// Header row
		$html .= "<tr>";
		foreach ($columns as $col) {
			$html .= "<th>" . htmlspecialchars($col['name']) . "</th>";
		}
		$html .= "</tr>";

		// Data rows
		foreach ($rows as $row) {
			$html .= "<tr>";
			foreach ($columns as $col) {
				$html .= "<td>" . htmlspecialchars((string)($row[$col['name']] ?? '')) . "</td>";
			}
			$html .= "</tr>";
		}

		$html .= "</table>";
		return $html;
	}
}
