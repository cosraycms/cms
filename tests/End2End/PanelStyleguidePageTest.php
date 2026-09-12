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
		$this->assertStringContainsString('class="page cms-styleguide"', $html);
		// Tokens are read from tokens.css, not listed in the view.
		$this->assertStringContainsString('--cms-color-accent', $html);
		$this->assertStringContainsString('background: var(--cms-color-canvas)', $html);
		// Components render through the same partials the editor uses.
		$this->assertStringContainsString('class="cms-button primary"', $html);
		$this->assertStringContainsString('class="cms-field required"', $html);
		$this->assertHtmlNodeExists(
			'//div[@data-content-locale-scope][@data-content-locale="de"]'
				. '//*[@data-content-locale-control]/*[@data-content-locale-option="de"][@aria-checked="true"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//input[@name="content[fallback-title][value][de]"][@value=""][@data-fallback-input]',
			$html,
		);
		// A translated sub-field of a row renders one variant per locale under
		// the pane's selector, like a top-level field.
		$this->assertHtmlNodeExists(
			'//div[@data-content-locale-scope]//*[@data-content-locale-control]'
				. '/following::div[@data-repeater-row]//div[@class="variant"][@data-locale="de"]',
			$html,
		);
		// A block with one field hides that field's label from sight; a
		// block with several keeps them.
		$this->assertHtmlNodeExists(
			'//div[@data-repeater-row]//div[contains(@class, "cms-field")]'
				. '/label[contains(@class, "sr-only")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//div[@data-repeater-row]//div[contains(@class, "cms-field")]'
				. '/label[@class="label"]',
			$html,
		);
		// No block marks a field as required: reaching for the block is what
		// makes its content mandatory. Top-level fields still do.
		$this->assertHtmlNodeMissing(
			'//div[contains(concat(" ", normalize-space(@class), " "), " block ")][@data-repeater-row]'
				. '//div[contains(concat(" ", normalize-space(@class), " "), " required ")]',
			$html,
		);
		$this->assertHtmlNodeMissing(
			'//div[contains(concat(" ", normalize-space(@class), " "), " block ")][@data-repeater-row]//textarea[@required]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//div[contains(concat(" ", normalize-space(@class), " "), " cms-field ")]'
				. '[contains(concat(" ", normalize-space(@class), " "), " required ")]',
			$html,
		);
		$this->assertStringContainsString('<th class="col-actions" role="columnheader"></th>', $html);
		$this->assertStringContainsString('class="chip is-create"', $html);
		$this->assertStringContainsString('class="cms-entries"', $html);
		$this->assertStringContainsString('data-repeater-add="App\\Styleguide\\Quote"', $html);
		// Blocks: the quiet one-column list and the twelve-column grid, each a
		// typed repeater with its layout on the row and a picker per type.
		$this->assertStringContainsString('class="cms-blocks-editor is-list"', $html);
		$this->assertStringContainsString('class="cms-blocks-editor is-grid"', $html);
		$this->assertStringContainsString('data-name="content[grid][value][de]"', $html);
		$this->assertStringContainsString('style="--colspan: 6; --rowspan: 1; --indent: 3; --reserved: 9"', $html);
		$this->assertStringContainsString('name="content[grid][value][en][0][layout][rowspan]"', $html);
		$this->assertStringContainsString('data-repeater-add="Cosray\\Block\\Heading"', $html);
		$this->assertStringContainsString('name="content[story][value][zxx][1][fields][text][json]"', $html);
		// Two richtext samples: the default toolbar and a #[Tools]-trimmed one,
		// each an element host carrying its tools list in the field payload.
		$this->assertStringContainsString('tag="cosray-richtext"', $html);
		$this->assertStringContainsString('tag="cosray-image"', $html);
		$this->assertStringContainsString('tag="cosray-file"', $html);
		$this->assertStringContainsString('tag="cosray-video"', $html);
		$this->assertStringContainsString('"sg-gallery-14"', $html);
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		$this->assertStringContainsString(
			'"tools":["undo","redo","bold","italic","strike","h2","h3","bullet-list","ordered-list","link"]',
			$html,
		);
		$this->assertStringContainsString('"tools":["bold","italic","link","source"]', $html);
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
