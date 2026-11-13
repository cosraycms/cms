<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Block\ActionBlock;
use Cosray\Tests\Fixtures\Block\SpacerBlock;
use Cosray\Tests\Fixtures\Node\TestSettingsDocument;
use Cosray\Tests\Fixtures\Plugin\TestCallout;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;

final class PanelBlockSettingsTest extends End2EndTestCase
{
	private const string ROW = 'content[blocks][value][zxx][0]';

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->plugin(TestPlugin::class);
		$bootstrap->node(TestSettingsDocument::class);

		return $bootstrap;
	}

	public function testSettingsFieldsAreEditedInTheBlockDialog(): void
	{
		$html = $this->editor('settings-editor', [
			'uid' => 'action',
			'type' => ActionBlock::class,
			'fields' => [
				'caption' => ['value' => ['zxx' => 'Download']],
				'target' => ['value' => ['zxx' => ['href' => '/plan.pdf']]],
				'style' => ['value' => ['zxx' => 'outlined']],
			],
		]);
		$block = '//div[@data-repeater-row][input[@value="action"]]';
		$dialog = $block . '/dialog[@data-dialog="meta"]';

		// The caption stays on the canvas, a bare single field.
		$this->assertHtmlNodeExists(
			$block
				. '/div[@class="cms-blocks-editor-body"]//input[@name="'
				. self::ROW
				. '[fields][caption][value][zxx]"]',
			$html,
		);
		$this->assertHtmlNodeExists($block . '[contains(@class, "is-bare")]', $html);
		// Target and style sit in the dialog under their labels, with their
		// stored values and their width, and nowhere on the canvas.
		$this->assertHtmlNodeExists(
			$dialog . '//cosray-host[@name="' . self::ROW . '[fields][target][json]"][@tag="cosray-link"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			$dialog
				. '//div[contains(@class, "cms-field")][contains(@style, "span 50")]'
				. '//select[@name="'
				. self::ROW
				. '[fields][style][value][zxx]"]/option[@value="outlined"][@selected]',
			$html,
		);
		$this->assertHtmlNodeExists(
			$dialog . '//label[not(contains(@class, "cms-sr-only"))][contains(., "Target")]',
			$html,
		);
		$this->assertHtmlNodeMissing(
			$block . '/div[@class="cms-blocks-editor-body"]//*[contains(@name, "[fields][target]")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			$block . '//button[@class="cms-blocks-editor-settings"][@data-dialog-open="meta"]',
			$html,
		);
	}

	public function testABlockWithEveryFieldInItsDialogOffersToOpenIt(): void
	{
		$html = $this->editor('settings-spacer', [
			'uid' => 'spacer',
			'type' => SpacerBlock::class,
			'fields' => ['height' => ['value' => ['zxx' => 'l']]],
		]);
		$block = '//div[@data-repeater-row][input[@value="spacer"]]';

		$this->assertHtmlNodeExists(
			$block . '/div[@class="cms-blocks-editor-body"]//button[@data-dialog-open="meta"][contains(., "Spacer")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			$block . '/dialog[@data-dialog="meta"]//select[@name="' . self::ROW . '[fields][height][value][zxx]"]',
			$html,
		);
	}

	public function testAReadOnlyBlockStillShowsItsSettings(): void
	{
		$html = $this->editor(
			'settings-readonly',
			[
				'uid' => 'action',
				'type' => ActionBlock::class,
				'fields' => ['caption' => ['value' => ['zxx' => 'Download']]],
			],
			'fixed',
		);
		$block = '//div[@data-repeater-row][input[@value="action"]]';

		$this->assertHtmlNodeExists(
			$block . '/div[@class="cms-blocks-editor-chrome"]//button[@data-dialog-open="meta"]',
			$html,
		);
		$this->assertHtmlNodeMissing($block . '//*[@data-repeater-remove]', $html);
		$this->assertHtmlNodeExists(
			$block
				. '/dialog[@data-dialog="meta"]//select[@name="content[fixed][value][zxx][0][fields][style][value][zxx]"][@disabled]',
			$html,
		);
	}

	public function testAPluginDrawsItsBlockWithItsOwnTemplate(): void
	{
		$html = $this->editor('settings-callout', [
			'uid' => 'callout',
			'type' => TestCallout::class,
			'fields' => [
				'text' => ['value' => ['zxx' => 'Mind the gap']],
				'tone' => ['value' => ['zxx' => 'loud']],
			],
		]);
		$block = '//div[@data-repeater-row][input[@value="callout"]]';

		// The view shows the text through the standard sub-field markup, so the
		// block is bare like Heading; the tone stays in the dialog.
		$this->assertHtmlNodeExists($block . '[contains(@class, "is-bare")]', $html);
		$this->assertHtmlNodeExists(
			$block
				. '/div[@class="cms-blocks-editor-body"]//div[@class="test-callout"]'
				. '//input[@name="'
				. self::ROW
				. '[fields][text][value][zxx]"][@value="Mind the gap"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			$block . '/dialog[@data-dialog="meta"]//select[@name="' . self::ROW . '[fields][tone][value][zxx]"]',
			$html,
		);
		// The view's CSS can follow the neutral values, kept current by the panel.
		$this->assertHtmlNodeExists(
			$block
				. '/div[@class="cms-blocks-editor-body"][@data-block-values="text tone"][@data-value-tone="loud"]'
				. '[contains(@style, "--value-tone: loud")]',
			$html,
		);
		// A stamped block gets the same view.
		$this->assertHtmlNodeExists(
			'//template[@data-repeater-template="' . TestCallout::class . '"]//div[@class="test-callout"]',
			$html,
		);
	}

	/** @param array<string, mixed> $row */
	private function editor(string $uid, array $row, string $field = 'blocks'): string
	{
		$this->authenticateAs('editor');
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->createTestType('test-settings-document'),
			'content' => json_encode([
				$field => ['type' => Blocks::class, 'value' => ['zxx' => [$row]]],
			]),
		]);
		$response = $this->makeRequest('GET', "/panel/node/{$uid}");
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}
}
