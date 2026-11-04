<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Content;

use Base3ChatWebsite\Content\DataHawkTest;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

final class DataHawkTestEdgeCasesTest extends TestCase {

	public function testEmptyResultRendersValidTableWithoutCells(): void {
		$html = $this->service(new QueryResult([], []))->getOutput('html', true);
		$this->assertStringContainsString('<h2>DataHawk Test Output</h2>', $html);
		$this->assertStringContainsString('<table border="1" cellpadding="6" cellspacing="0">', $html);
		$this->assertSame(0, substr_count($html, '<th>'));
		$this->assertSame(0, substr_count($html, '<td>'));
		$this->assertStringContainsString('</table>', $html);
	}

	public function testBooleanNumericNullAndQuoteValuesAreRenderedWithoutHtmlInjection(): void {
		$result = new QueryResult(
			[['name' => 'value'], ['name' => 'missing']],
			[
				['value' => true, 'missing' => 0],
				['value' => false, 'missing' => null],
				['value' => '\'"<script> &']
			]
		);
		$html = $this->service($result)->getOutput();
		$this->assertStringContainsString('<td>1</td><td>0</td>', $html);
		$this->assertStringContainsString('<td></td><td></td>', $html);
		$this->assertStringContainsString('&#039;&quot;&lt;script&gt; &amp;', $html);
		$this->assertStringNotContainsString('<script>', $html);
	}

	public function testErrorsOtherThanRuntimeExceptionsAreAlsoEscaped(): void {
		$queryService = $this->createStub(IQueryService::class);
		$queryService->method('executeQuery')->willThrowException(new \TypeError('invalid <column> & source'));
		$html = (new DataHawkTest($queryService))->getOutput();
		$this->assertSame('<b>DataHawkTest Error:</b> invalid &lt;column&gt; &amp; source', $html);
	}

	private function service(QueryResult $result): DataHawkTest {
		$queryService = $this->createStub(IQueryService::class);
		$queryService->method('executeQuery')->willReturn($result);
		return new DataHawkTest($queryService);
	}
}
