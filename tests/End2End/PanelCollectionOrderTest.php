<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Node\Positions;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArrangedChildrenCollection;
use Cosray\Tests\Fixtures\Collection\TestArrangedCollection;
use Cosray\Tests\Fixtures\Collection\TestArrangedTreeCollection;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Node\TestHierarchyChild;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;
use Cosray\Tests\Fixtures\Node\TestSortableParent;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Psr\Http\Message\ResponseInterface;

final class PanelCollectionOrderTest extends End2EndTestCase
{
	private int $parentType;
	private int $childType;

	protected function setUp(): void
	{
		parent::setUp();

		$this->authenticateAs('editor');
		$this->parentType = $this->createTestType('sortable-test-parent');
		$this->childType = $this->createTestType('test-hierarchy-child');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestSortableParent::class);
		$plugin->node(TestHierarchyParent::class);
		$plugin->node(TestHierarchyChild::class);
		$plugin->collection(TestArrangedChildrenCollection::class);
		$plugin->collection(TestArrangedCollection::class);
		$plugin->collection(TestArrangedTreeCollection::class);
		$plugin->collection(TestHierarchyCollection::class);

		return $plugin;
	}

	public function testArrangedChildrenIgnoreTheColumnSortInTreeAndFocusedViews(): void
	{
		$this->family();
		new Positions($this->db())->orderChildren('station', ['zulu', 'mike']);

		foreach ([['open' => 'station'], ['parent' => 'station']] as $scope) {
			foreach (['asc', 'desc'] as $direction) {
				$rows = $this->rows($this->listing('test-arranged-children', [
					...$scope,
					'sort' => 'title',
					'dir' => $direction,
				]));

				// The unarranged child follows the arranged ones.
				$this->assertSame(['zulu', 'mike', 'alpha'], $this->group($rows, 'station'));
			}
		}
	}

	public function testRowsOfAnArrangedGroupCarryTheirMoves(): void
	{
		$this->family();
		$html = $this->listing('test-arranged-children', ['parent' => 'station']);
		$move = '//tr[@data-uid="%s"]//form[@data-order-move="%s"]/button';

		$this->assertHtmlNodeExists(sprintf($move . '[@disabled]', 'alpha', 'up'), $html);
		$this->assertHtmlNodeExists(sprintf($move . '[not(@disabled)]', 'alpha', 'down'), $html);
		$this->assertHtmlNodeExists(sprintf($move . '[@disabled]', 'zulu', 'down'), $html);
		$this->assertHtmlNodeExists('//tr[@data-uid="mike"]//*[@data-order-grip]', $html);
		$this->assertHtmlNodeExists('//form[@data-order-form]', $html);
		// The focused level is arranged: no column offers its order.
		$this->assertHtmlNodeMissing('//thead//a', $html);

		// The column-sorted top level of the same collection keeps its headers.
		$tree = $this->listing('test-arranged-children', ['open' => 'station']);
		$this->assertHtmlNodeExists('//thead//a', $tree);
		$this->assertHtmlNodeMissing('//tr[@data-uid="station"][@data-group]', $tree);
	}

	public function testMovesOneRowUpOrDownAndReturnsToTheListing(): void
	{
		$this->family();

		$response = $this->move('test-arranged-children', ['node' => 'zulu', 'move' => 'up'], ['parent' => 'station']);

		$this->assertResponseStatus(303, $response);
		$this->assertSame(
			'/panel/collection/test-arranged-children?parent=station&moved=zulu',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame(['alpha', 'zulu', 'mike'], $this->children());

		$this->move('test-arranged-children', ['node' => 'alpha', 'move' => 'down']);
		$this->assertSame(['zulu', 'alpha', 'mike'], $this->children());

		// Past the end nothing changes.
		$this->assertResponseStatus(303, $this->move('test-arranged-children', ['node' => 'zulu', 'move' => 'up']));
		$this->assertSame(['zulu', 'alpha', 'mike'], $this->children());
	}

	public function testDropsPlaceNextToANeighbour(): void
	{
		$this->family();

		$this->move('test-arranged-children', ['node' => 'alpha', 'after' => 'zulu']);
		$this->assertSame(['mike', 'zulu', 'alpha'], $this->children());

		$this->move('test-arranged-children', ['node' => 'zulu', 'before' => 'mike']);
		$this->assertSame(['zulu', 'mike', 'alpha'], $this->children());

		$html = $this->listing('test-arranged-children', ['parent' => 'station', 'moved' => 'zulu']);
		$this->assertHtmlNodeExists('//tr[@data-uid="zulu"][@data-moved]', $html);
	}

	public function testACollectionOrderLeavesTheParentsOrderAlone(): void
	{
		$this->family();
		new Positions($this->db())->orderChildren('station', ['alpha', 'mike', 'zulu']);

		// Unarranged, the flat listing starts from the titles: zulu, mike, alpha.
		$this->move('test-arranged', ['node' => 'alpha', 'before' => 'zulu']);

		$this->assertSame(['alpha', 'zulu', 'mike'], $this->group($this->rows($this->listing('test-arranged')), ''));
		$this->assertSame(['alpha', 'mike', 'zulu'], $this->children());
	}

	public function testAnArrangedTopLevelMovesOnlyRootEntries(): void
	{
		$this->family();
		$this->createNode('second', $this->parentType, 'Second');
		$tree = fn(): array => $this->group(
			$this->rows($this->listing('test-arranged-tree', ['open' => 'station'])),
			'',
		);

		$this->assertSame(['second', 'station'], $tree());

		$this->move('test-arranged-tree', ['node' => 'second', 'move' => 'down']);
		$this->assertSame(['station', 'second'], $tree());
		// The children keep their parent's order.
		$this->assertSame(['alpha', 'mike', 'zulu'], $this->children());
		$this->assertResponseStatus(
			400,
			$this->move('test-arranged-tree', ['node' => 'second', 'before' => 'zulu']),
		);

		// A flat arranged listing moves by rows too.
		$this->move('test-arranged', ['node' => 'alpha', 'move' => 'up']);
		$this->assertSame(['zulu', 'alpha', 'mike'], $this->group($this->rows($this->listing('test-arranged')), ''));
	}

	public function testSearchShowsTheManualOrderWithoutMoves(): void
	{
		$this->family();
		new Positions($this->db())->orderChildren('station', ['zulu', 'mike', 'alpha']);

		$html = $this->listing('test-arranged-children', ['parent' => 'station', 'q' => 'i']);

		$this->assertSame(['mike', 'alpha'], array_keys($this->rows($html)));
		$this->assertHtmlNodeMissing('//tr[@data-group]', $html);
		$this->assertHtmlNodeMissing('//form[@data-order-form]', $html);
	}

	public function testRejectsMovesOutsideAManualOrder(): void
	{
		$this->family();
		$this->createNode('other', $this->parentType, 'Other');
		$this->createNode(
			'elsewhere',
			$this->childType,
			'Elsewhere',
			$this->createNode('two', $this->parentType, 'Two'),
		);
		$this->createNode('plain-root', $this->createTestType('test-hierarchy-parent'), 'Plain');

		foreach ([
			'no node' => ['test-arranged-children', ['move' => 'up']],
			'unknown direction' => ['test-arranged-children', ['node' => 'alpha', 'move' => 'sideways']],
			'two targets' => ['test-arranged-children', ['node' => 'alpha', 'before' => 'mike', 'after' => 'zulu']],
			'foreign neighbour' => ['test-arranged-children', ['node' => 'alpha', 'after' => 'elsewhere']],
			'itself' => ['test-arranged-children', ['node' => 'alpha', 'after' => 'alpha']],
			'unarranged level' => ['test-arranged-children', ['node' => 'other', 'move' => 'up']],
			'not an entry' => ['test-arranged', ['node' => 'station', 'move' => 'up']],
			'unarranged collection' => ['test-hierarchy', ['node' => 'plain-root', 'move' => 'up']],
		] as $case => [$collection, $body]) {
			$this->assertResponseStatus(400, $this->move($collection, $body), $case);
		}

		$this->assertSame(['alpha', 'mike', 'zulu'], $this->children());
	}

	/** A station with three sensors, titled against their uids' order. */
	private function family(): int
	{
		$station = $this->createNode('station', $this->parentType, 'Station');
		$this->createNode('zulu', $this->childType, 'Alpha', $station);
		$this->createNode('mike', $this->childType, 'Bravo', $station);
		$this->createNode('alpha', $this->childType, 'Charlie', $station);
		// Titles order them zulu, mike, alpha; arrange them the other way round.
		new Positions($this->db())->orderChildren('station', ['alpha', 'mike', 'zulu']);

		return $station;
	}

	private function createNode(string $uid, int $type, string $title, ?int $parent = null): int
	{
		return $this->createTestNode([
			'uid' => $uid,
			'type' => $type,
			'parent' => $parent,
			'title' => ['en' => $title],
			'content' => ['title' => ['type' => 'text', 'value' => ['en' => $title]]],
		]);
	}

	private function listing(string $collection, array $query = []): string
	{
		$response = $this->makeRequest('GET', '/panel/collection/' . $collection, ['query' => $query]);
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	private function move(string $collection, array $body, array $query = []): ResponseInterface
	{
		return $this->makeRequest('POST', '/panel/collection/' . $collection . '/position', [
			'query' => $query,
			'body' => $body,
		]);
	}

	/** @return list<string> the station's children in the order templates get them */
	private function children(): array
	{
		return array_keys($this->rows($this->listing('test-arranged-children', ['parent' => 'station'])));
	}

	/** @return array<string, ?string> row uids with their group, in listing order */
	private function rows(string $html): array
	{
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		$rows = [];

		foreach (new DOMXPath($document)->query('//tbody/tr[@data-uid]') ?: [] as $row) {
			if ($row instanceof DOMElement) {
				$rows[$row->getAttribute('data-uid')] = $row->hasAttribute('data-group')
					? $row->getAttribute('data-group')
					: null;
			}
		}

		return $rows;
	}

	/**
	 * @param array<string, ?string> $rows
	 * @return list<string>
	 */
	private function group(array $rows, string $group): array
	{
		return array_keys(array_filter($rows, static fn(?string $member): bool => $member === $group));
	}
}
