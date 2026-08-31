<?php

declare(strict_types=1);

namespace Cosray\References;

use Celema\Quma\Database;
use Cosray\Field;

/**
 * Rebuilds both reference indexes from scratch: wipe, then rescan all
 * live nodes and every menu item's references (image icons, asset links,
 * and the node a `node` or `children` item points at).
 * Everything in the indexes is derived, so a rebuild is always safe; it
 * is the recovery path after restores, imports, or content migrations.
 */
final class Rebuild
{
	private readonly Scanner $scanner;
	private readonly Sync $sync;

	public function __construct(
		private readonly Database $db,
		?Field\Index $index = null,
	) {
		$this->scanner = new Scanner($index);
		$this->sync = new Sync($db);
	}

	/** @return array{owners: int, assets: int, nodes: int, skipped: int} */
	public function run(): array
	{
		$this->db->references->wipe()->run();

		$owners = 0;
		$scanned = 0;

		foreach ($this->db->references->nodeContents()->lazy() as $row) {
			$content = json_decode((string) $row['content'], true);
			$refs = $this->scanner->scan(is_array($content) ? $content : []);

			if ($refs['assets'] === [] && $refs['nodes'] === []) {
				continue;
			}

			$this->sync->replace('node', (string) $row['uid'], $refs);
			$owners++;
			$scanned += count($refs['assets']) + count($refs['nodes']);
		}

		// Merged per item before writing: `Sync::replace()` is a full replace
		// per owner, so two passes would have the second wipe the first.
		$menus = [];

		foreach ($this->db->references->menuAssets()->lazy() as $row) {
			$menus[(string) $row['item']]['assets'][] = (string) $row['uid'];
		}

		foreach ($this->db->references->menuNodes()->lazy() as $row) {
			$menus[(string) $row['item']]['nodes'][] = (string) $row['uid'];
		}

		foreach ($menus as $item => $refs) {
			$refs += ['assets' => [], 'nodes' => []];
			$this->sync->replace('menu', (string) $item, $refs);
			$owners++;
			$scanned += count($refs['assets']) + count($refs['nodes']);
		}

		$counts = $this->db->references->counts()->one();
		$assets = (int) $counts['assets'];
		$nodes = (int) $counts['nodes'];

		return [
			'owners' => $owners,
			'assets' => $assets,
			'nodes' => $nodes,
			'skipped' => $scanned - $assets - $nodes,
		];
	}
}
