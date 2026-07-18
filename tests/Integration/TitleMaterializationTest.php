<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Container\Container;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\NodeWithClassTitleAttribute;
use Cosray\Tests\Fixtures\Node\TestPage;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Title\Rebuild;

/**
 * @internal
 *
 * @coversNothing
 */
final class TitleMaterializationTest extends IntegrationTestCase
{
	public function container(): Container
	{
		$container = parent::container();
		$container->tag(Bootstrap::NODE_TAG)->add('title-field-type', NodeWithClassTitleAttribute::class);
		$container->tag(Bootstrap::NODE_TAG)->add('title-dynamic-type', TestPage::class);

		return $container;
	}

	public function testRebuildCopiesFieldTitleVerbatim(): void
	{
		$type = $this->createTestType('title-field-type');
		$this->createTestNode([
			'uid' => 'title-mat-field',
			'type' => $type,
			'content' => ['heading' => ['type' => 'text', 'value' => ['en' => ' Doc ', 'de' => 'Dok']]],
		]);

		$this->rebuild();

		// Field titles are copied per locale (blanks aside), no fallback baked in.
		// jsonb reorders keys, so compare order-insensitively.
		$this->assertEquals(['en' => 'Doc', 'de' => 'Dok'], $this->titleOf('title-mat-field'));
	}

	public function testRebuildEvaluatesDynamicTitlePerLocale(): void
	{
		$type = $this->createTestType('title-dynamic-type');
		$this->createTestNode([
			'uid' => 'title-mat-dyn',
			'type' => $type,
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Hello', 'de' => 'Hallo']]],
		]);

		$this->rebuild();

		$this->assertEquals(['en' => 'Hello', 'de' => 'Hallo'], $this->titleOf('title-mat-dyn'));
	}

	public function testRebuildCollapsesDynamicTitleToNeutral(): void
	{
		$type = $this->createTestType('title-dynamic-type');
		// Only English is set; German falls back to it, so every locale resolves
		// to the same string and the map collapses to the neutral key.
		$this->createTestNode([
			'uid' => 'title-mat-solo',
			'type' => $type,
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Solo']]],
		]);

		$this->rebuild();

		$this->assertSame(['zxx' => 'Solo'], $this->titleOf('title-mat-solo'));
	}

	public function testRebuildDoesNotRecordHistoryOrBumpChanged(): void
	{
		$type = $this->createTestType('title-dynamic-type');
		$this->createTestNode([
			'uid' => 'title-mat-quiet',
			'type' => $type,
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Quiet']]],
		]);

		$before = $this->changedOf('title-mat-quiet');
		$history = $this->historyCount('title-mat-quiet');

		$this->rebuild();

		$this->assertSame($before, $this->changedOf('title-mat-quiet'));
		$this->assertSame($history, $this->historyCount('title-mat-quiet'));
	}

	private function rebuild(): array
	{
		$context = $this->createContext();
		$cms = new Cms($context, Services::withDefaults());

		return new Rebuild($context, $cms, $this->titleLocales(), $this->conn(), new Types())->run();
	}

	private function titleLocales(): Locales
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('de', title: 'Deutsch', fallback: 'en');

		return $locales;
	}

	/**
	 * @return array<string, string>
	 */
	private function titleOf(string $uid): array
	{
		$title = $this->db()->execute(
			'SELECT title FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one()['title'];

		return json_decode((string) $title, true);
	}

	private function changedOf(string $uid): string
	{
		return (string) $this->db()->execute(
			'SELECT changed FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one()['changed'];
	}

	private function historyCount(string $uid): int
	{
		return (int) $this->db()->execute(
			'SELECT count(*) AS c FROM cms.nodes_history h
				JOIN cms.nodes n ON n.node = h.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->one()['c'];
	}
}
