<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Panel\Media
 */
final class PanelMediaPageTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->authenticateAs('editor');
	}

	public function testMediaPageRendersMountAndSystemPayload(): void
	{
		$response = $this->makeRequest('GET', '/cp/media');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('id="main" class="page media-page"', $html);
		$this->assertStringContainsString(
			'<cosray-media-library data-cosray-element="media-library">',
			$html,
		);
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		// The system payload carries the locales the meta form's tabs need.
		$this->assertStringContainsString('"defaultLocale":"en"', $html);
		// The sidebar gains a Media entry.
		$this->assertStringContainsString('href="/cp/media"', $html);
	}

	public function testMediaPageRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/cp/media', ['authToken' => '']);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertStringStartsWith('/cp/login', $response->getHeaderLine('Location'));
	}
}
