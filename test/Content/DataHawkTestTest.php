<?php declare(strict_types=1);

namespace Base3ChatWebsite\Test\Content;

use Base3ChatWebsite\Content\DataHawkTest;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

final class DataHawkTestTest extends TestCase {

	public function testTechnicalNameAndHelp(): void {
		$this->assertSame('datahawktest', DataHawkTest::getName());
		$this->assertSame("Help of DataHawkTest\n", $this->service(new QueryResult([], []))->getHelp());
	}

	public function testQueryDefinitionAndEscapedTableOutput(): void {
		$queryService = $this->createMock(IQueryService::class);
		$queryService->expects($this->once())->method('executeQuery')->with($this->callback(static function(array $query): bool {
			return $query['type'] === 'select'
				&& $query['table'] === 'git_repository'
				&& $query['fields'][0]['alias'] === 'repository_name'
				&& $query['fields'][1]['alias'] === 'full_repo_name';
		}))->willReturn(new QueryResult(
			[['name' => 'repository_name'], ['name' => 'full_repo_name']],
			[
				['repository_name' => '<script>alert(1)</script>', 'full_repo_name' => 'A&B'],
				['repository_name' => 'Missing second column']
			]
		));
		$html = (new DataHawkTest($queryService))->getOutput();
		$this->assertStringContainsString('<th>repository_name</th>', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
		$this->assertStringContainsString('A&amp;B', $html);
		$this->assertStringContainsString('<td>Missing second column</td><td></td>', $html);
		$this->assertStringNotContainsString('<script>', $html);
	}

	public function testHeaderIsEscapedEvenWithoutRows(): void {
		$html = $this->service(new QueryResult([['name' => '<name>']], []))->getOutput();
		$this->assertStringContainsString('<th>&lt;name&gt;</th>', $html);
		$this->assertSame(0, substr_count($html, '<td>'));
	}

	public function testQueryFailuresAreReturnedAsEscapedErrorText(): void {
		$queryService = $this->createStub(IQueryService::class);
		$queryService->method('executeQuery')->willThrowException(new \RuntimeException('bad <script> & "data"'));
		$this->assertSame(
			'<b>DataHawkTest Error:</b> bad &lt;script&gt; &amp; &quot;data&quot;',
			(new DataHawkTest($queryService))->getOutput('html', true)
		);
	}

	private function service(QueryResult $result): DataHawkTest {
		$queryService = $this->createStub(IQueryService::class);
		$queryService->method('executeQuery')->willReturn($result);
		return new DataHawkTest($queryService);
	}
}
