<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * The dialog that creates a node from its required fields and then opens
 * the editor.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelEditorCreateDialogTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testTheDialogAsksOnlyForTheRequiredFields(): void
	{
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/node/create/test-document/dialog'));

		$this->assertHtmlNodeExists(
			'//form[@id="node-editor-form"][@data-create][@action="/panel/node/create/test-document"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//form[@data-create]//input[@name="content[title][value][zxx]"][@required]',
			$html,
		);
		$this->assertHtmlNodeMissing('//form[@data-create]//*[contains(@name, "content[intro]")]', $html);
		$this->assertHtmlNodeExists('//form[@data-create]//input[@name="dialog"][@value="1"]', $html);
		$this->assertHtmlNodeExists('//form[@data-create]//*[@data-stage="main" and @data-state="unpublished"]', $html);
	}

	public function testCreatingFromTheDialogOpensTheEditorOfAnUnpublishedNode(): void
	{
		$response = $this->makeRequest('POST', '/panel/node/create/test-document', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'dialog' => '1',
				'uid' => 'dialog-created',
				'content' => ['title' => ['value' => ['zxx' => 'From the dialog']]],
			],
		]);

		$location = json_decode($response->getHeaderLine('HX-Location'), true);
		$this->assertIsArray($location);
		$this->assertSame('#main', $location['target']);
		$this->assertStringStartsWith('/panel/node/dialog-created', $location['path']);
		$row = $this->db()->execute(
			"SELECT n.published, d.node AS draft FROM cms.nodes n LEFT JOIN cms.drafts d ON d.node = n.node
				WHERE n.uid = 'dialog-created'",
		)->one();
		$this->assertFalse((bool) $row['published']);
		$this->assertNull($row['draft']);
	}

	public function testAMissingRequiredFieldIsReportedInTheDialog(): void
	{
		$response = $this->makeRequest('POST', '/panel/node/create/test-document', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['_complete' => '1', 'dialog' => '1', 'content' => ['title' => ['value' => ['zxx' => '']]]],
		]);

		$this->assertSame('', $response->getHeaderLine('HX-Location'));
		$this->assertHtmlNodeExists(
			'//*[@id="editor-errors" and @hx-swap-oob="true" and @role="alert"]',
			$this->getHtmlResponse($response),
		);
	}
}
