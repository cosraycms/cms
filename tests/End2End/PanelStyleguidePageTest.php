<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Panel\Styleguide
 */
final class PanelStyleguidePageTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->authenticateAs('editor');
	}

	public function testStyleguideRendersComponentsAndTokens(): void
	{
		$this->app = $this->createApp(['app.debug' => true]);

		$response = $this->makeRequest('GET', '/cp/styleguide');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('id="main" class="page cms-styleguide"', $html);
		// Tokens are read from tokens.css, not listed in the view.
		$this->assertStringContainsString('--cms-color-primary', $html);
		$this->assertStringContainsString('background: var(--cms-color-canvas)', $html);
		// Components render through the same partials the editor uses.
		$this->assertStringContainsString('class="cms-button primary"', $html);
		$this->assertStringContainsString('class="cms-field required"', $html);
		$this->assertStringContainsString('data-locale-tab="de"', $html);
	}

	public function testStyleguideIsAbsentWithoutDebug(): void
	{
		$response = $this->makeRequest('GET', '/cp/styleguide');

		$this->assertSame(404, $response->getStatusCode());
	}

	public function testStyleguideRequiresAuthentication(): void
	{
		$this->app = $this->createApp(['app.debug' => true]);

		$response = $this->makeRequest('GET', '/cp/styleguide', ['authToken' => '']);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertStringStartsWith('/cp/login', $response->getHeaderLine('Location'));
	}
}
