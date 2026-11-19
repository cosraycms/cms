<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelSystemInfoTest extends End2EndTestCase
{
	public function testOnlySuperusersSeeTheSystemInfo(): void
	{
		$this->authenticateAs('admin');

		$this->assertResponseStatus(403, $this->makeRequest('GET', '/panel/system-info'));
		$this->assertStringNotContainsString(
			'/panel/system-info',
			$this->getHtmlResponse($this->makeRequest('GET', '/panel/profile')),
		);
	}

	public function testTheOverviewReportsTheRuntime(): void
	{
		$this->authenticateAs('superuser');

		$response = $this->makeRequest('GET', '/panel/system-info');
		$html = $this->getHtmlResponse($response);

		$this->assertResponseOk($response);
		$this->assertHtmlNodeExists('//a[@href="/panel/system-info" and @aria-current="page"]', $html);
		$this->assertHtmlNodeExists('//h2[normalize-space()="Extensions"]', $html);
		$this->assertHtmlNodeExists('//dt[normalize-space()="Server version"]', $html);
		$this->assertHtmlNodeExists(
			'//dt[normalize-space()="Migrations"]/following-sibling::dd[contains(., "all applied")]',
			$html,
		);
		$this->assertHtmlNodeExists('//h2[normalize-space()="Job queue"]', $html);
		$this->assertStringContainsString(PHP_VERSION, $html);
		$this->assertHtmlNodeExists('//button[@data-copy="system-info-text"]', $html);
		$this->assertHtmlNodeExists('//template[@id="system-info-text" and starts-with(., "System info")]', $html);
	}

	public function testThePhpTabSearchesDirectivesAndExtensions(): void
	{
		$this->authenticateAs('superuser');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/system-info?tab=php&q=memory_limit'));

		$this->assertHtmlNodeExists('//tbody/tr/th[.="memory_limit"]', $html);
		$this->assertHtmlNodeMissing('//tbody/tr/th[.="post_max_size"]', $html);
		$this->assertHtmlNodeMissing('//tbody/tr/th[.="intl"]', $html);
	}
}
