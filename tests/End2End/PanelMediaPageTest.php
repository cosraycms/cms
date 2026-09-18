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
		$this->assertStringContainsString('class="page cms-media"', $html);
		$this->assertStringContainsString(
			'<cosray-media-library data-cosray-element="media-library">',
			$html,
		);
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		$this->assertStringContainsString('"defaultLocale":"en"', $html);
		// The screen's content-language selector wraps the library, so the
		// inspector edits the selected translation.
		$this->assertHtmlNodeExists(
			'//div[@data-content-locale-scope][@data-content-locale="en"]'
				. '//*[@data-content-locale-control]/*[@data-content-locale-option="de"]',
			$html,
		);
		// Browser-rendered controls boot with the panel catalog.
		$this->assertStringContainsString('id="verba-catalog"', $html);
		$this->assertStringContainsString('"common:cancel":"Cancel"', $html);
		// The masthead carries the media area.
		$this->assertStringContainsString('href="/cp/media"', $html);
		// This panel defines no collections, so the content entry has nowhere
		// to lead and stays away.
		$this->assertStringNotContainsString('>Content<', $html);
		// The library's filters mount into the shell's rail.
		$this->assertHtmlNodeExists('//aside[@class="cms-sidebar"]//*[@data-media-rail]', $html);
	}

	public function testMediaPageRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/cp/media', ['authToken' => '']);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertStringStartsWith('/cp/login', $response->getHeaderLine('Location'));
	}
}
