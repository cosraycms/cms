<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('app');
}

$collectionPath = $panelPath . '/collection/' . rawurlencode((string) $slug);
$total = (int) $total;
$offset = (int) $offset;
$limit = (int) $limit;
$q = (string) $q;
$sort = (string) $sort;
$dir = (string) $dir;
$parent = $parent === null ? null : (string) $parent;
$header = $header instanceof Traversable ? iterator_to_array($header) : (array) $header;
$nodes = $nodes instanceof Traversable ? iterator_to_array($nodes) : (array) $nodes;
$sorts = $sorts instanceof Traversable ? iterator_to_array($sorts) : (array) $sorts;
$sorts = array_values(array_filter(
	array_map(static fn(mixed $sort): string => trim((string) $sort), $sorts),
	static fn(string $sort): bool => $sort !== '',
));
$blueprints = $blueprints instanceof Traversable ? iterator_to_array($blueprints) : (array) $blueprints;
$blueprints = array_values(array_filter(
	array_map(static function (mixed $blueprint): array {
		$blueprint = $blueprint instanceof Traversable ? iterator_to_array($blueprint) : (array) $blueprint;

		return [
			'slug' => trim((string) ($blueprint['slug'] ?? '')),
			'name' => trim((string) ($blueprint['name'] ?? '')),
		];
	}, $blueprints),
	static fn(array $blueprint): bool => $blueprint['slug'] !== '' && $blueprint['name'] !== '',
));
$blueprintSlugs = array_column($blueprints, 'slug');
$pageCount = $limit > 0 ? max(1, (int) ceil($total / $limit)) : 1;
$currentPage = $limit > 0 ? min($pageCount, (int) floor($offset / $limit) + 1) : 1;
$rowCount = count($nodes);
$rangeStart = $total === 0 ? 0 : min($offset + 1, $total);
$rangeEnd = min($offset + $rowCount, $total);

$queryUrl = static function (array $overrides = []) use (
	$collectionPath,
	$q,
	$sort,
	$dir,
	$limit,
	$parent,
): string {
	$params = [
		'q' => $q,
		'sort' => $sort,
		'dir' => $dir,
		'limit' => $limit,
		'parent' => $parent,
	];
	$params = array_merge($params, $overrides);
	$params = array_filter(
		$params,
		static fn(mixed $value): bool => $value !== null && $value !== '',
	);

	if (($params['limit'] ?? null) === 50) {
		unset($params['limit']);
	}

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

	return $query === '' ? $collectionPath : $collectionPath . '?' . $query;
};

$editorUrl = static function (string $uid) use (
	$panelPath,
	$slug,
	$q,
	$sort,
	$dir,
	$offset,
	$limit,
	$parent,
): string {
	$params = [
		'q' => $q,
		'sort' => $sort,
		'dir' => $dir,
		'offset' => $offset,
		'limit' => $limit,
		'parent' => $parent,
	];
	$params = array_filter(
		$params,
		static fn(mixed $value): bool => $value !== null && $value !== '',
	);

	if (($params['offset'] ?? null) === 0) {
		unset($params['offset']);
	}

	if (($params['limit'] ?? null) === 50) {
		unset($params['limit']);
	}

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	$path = $panelPath . '/collection/' . rawurlencode((string) $slug) . '/' . rawurlencode($uid);

	return $query === '' ? $path : $path . '?' . $query;
};

$createUrl = static function (string $type, ?string $parentUid = null) use (
	$collectionPath,
	$q,
	$sort,
	$dir,
	$offset,
	$limit,
	$parent,
): string {
	$params = [
		'q' => $q,
		'sort' => $sort,
		'dir' => $dir,
		'offset' => $offset,
		'limit' => $limit,
		'parent' => $parentUid ?? $parent,
	];
	$params = array_filter(
		$params,
		static fn(mixed $value): bool => $value !== null && $value !== '',
	);

	if (($params['offset'] ?? null) === 0) {
		unset($params['offset']);
	}

	if (($params['limit'] ?? null) === 50) {
		unset($params['limit']);
	}

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	$path = $collectionPath . '/create/' . rawurlencode($type);

	return $query === '' ? $path : $path . '?' . $query;
};

$displayValue = static function (mixed $value, bool $date = false): string {
	if ($date && is_string($value) && $value !== '') {
		try {
			$value = new DateTimeImmutable($value)->format('d.m.Y H:i');
		} catch (Throwable) {
			// Keep original value when it cannot be parsed as datetime.
		}
	}

	if (is_bool($value)) {
		return $value ? 'Yes' : 'No';
	}

	if (is_scalar($value)) {
		return (string) $value;
	}

	if (is_object($value) && method_exists($value, '__toString')) {
		return (string) $value;
	}

	return '';
};

$sortForHeader = static function (string $label) use ($sorts): ?string {
	$normalized = strtolower($label);
	$candidates = [];

	if (str_contains($normalized, 'bearbeitet') || str_contains($normalized, 'changed')) {
		$candidates[] = 'changed';
	}

	if (str_contains($normalized, 'erstellt') || str_contains($normalized, 'created')) {
		$candidates[] = 'created';
	}

	if (str_contains($normalized, 'titel') || str_contains($normalized, 'title')) {
		$candidates[] = 'title';
	}

	if (str_contains($normalized, 'editor')) {
		$candidates[] = 'editor';
	}

	if (str_contains($normalized, 'typ') || str_contains($normalized, 'type')) {
		$candidates[] = 'type';
	}

	if (str_contains($normalized, 'uid')) {
		$candidates[] = 'uid';
	}

	foreach (array_unique($candidates) as $candidate) {
		if (in_array($candidate, $sorts, true)) {
			return $candidate;
		}
	}

	return null;
};

$statusBadges = static function (mixed $node) use (
	$showPublished,
	$showHidden,
	$showLocked,
): array {
	$badges = [];

	if ($showPublished) {
		$published = (bool) ($node['published'] ?? false);
		$badges[] = [
			'kind' => $published ? 'published' : 'draft',
			'label' => $published ? 'Published' : 'Draft',
		];
	}

	if ($showHidden && (bool) ($node['hidden'] ?? false)) {
		$badges[] = [
			'kind' => 'hidden',
			'label' => 'Hidden',
		];
	}

	if ($showLocked && (bool) ($node['locked'] ?? false)) {
		$badges[] = [
			'kind' => 'locked',
			'label' => 'Locked',
		];
	}

	return $badges;
};
?>

<div id="main" class="page collection-page">
	<header class="topbar">
		<form
			class="search"
			method="get"
			action="<?= escape($collectionPath) ?>"
			hx-target="#main">
			<label class="sr-only" for="collection-search">Search <?= escape((string) $name) ?></label>
			<span class="search-icon" aria-hidden="true">⌕</span>
			<input
				id="collection-search"
				name="q"
				type="search"
				value="<?= escape($q) ?>"
				placeholder="Search entries …" />
			<?php if ($sort !== ''): ?>
				<input type="hidden" name="sort" value="<?= escape($sort) ?>" />
			<?php endif ?>
			<?php if ($dir !== ''): ?>
				<input type="hidden" name="dir" value="<?= escape($dir) ?>" />
			<?php endif ?>
			<?php if ($limit !== 50): ?>
				<input type="hidden" name="limit" value="<?= $limit ?>" />
			<?php endif ?>
			<?php if ($parent !== null): ?>
				<input type="hidden" name="parent" value="<?= escape($parent) ?>" />
			<?php endif ?>
		</form>

		<div class="topbar-actions">
			<?php if ($q !== ''): ?>
				<a class="btn btn-ghost" href="<?= escape($queryUrl([
				'q' => '',
				'offset' => '',
			])) ?>" hx-target="#main">Clear search</a>
			<?php endif ?>
			<?php foreach ($blueprints as $blueprint): ?>
				<a
					class="btn btn-primary"
					href="<?= escape($createUrl($blueprint['slug'])) ?>"
					hx-target="#main">
					New <?= escape($blueprint['name']) ?>
				</a>
			<?php endforeach ?>
		</div>
	</header>

	<section class="content">
		<div class="page-head">
			<?php if ($parent !== null): ?>
				<nav class="breadcrumb" aria-label="Breadcrumb">
					<a href="<?= escape($queryUrl([
				'parent' => '',
				'offset' => '',
			])) ?>" hx-target="#main"><?= escape((string) $name) ?></a>
					<span aria-hidden="true">/</span>
					<span><?= escape($parent) ?></span>
				</nav>
			<?php endif ?>
			<h1><?= escape((string) $name) ?></h1>
			<span class="count-pill"><?= $total ?> <?= $total === 1 ? 'entry' : 'entries' ?></span>
		</div>

		<div class="collection-panel">
			<?php if (count($nodes) === 0): ?>
				<div class="collection-empty">
					<div class="empty-icon" aria-hidden="true">⌁</div>
					<strong>No entries found.</strong>
					<?php if ($q !== ''): ?>
						<p>Try a different search or clear the current filter.</p>
					<?php else: ?>
						<p>This collection does not contain entries yet.</p>
					<?php endif ?>
				</div>
			<?php else: ?>
				<div class="tablewrap">
					<table class="collection-list">
						<thead>
							<tr>
								<?php foreach ($header as $label): ?>
									<?php

									$label = (string) $label;
									$sortKey = $sortForHeader($label);
									$isSorted = $sortKey !== null && $sortKey === $sort;
									$nextDir = $isSorted && $dir === 'asc' ? 'desc' : 'asc';
									$sortClass = $isSorted ? ' is-sorted is-' . $dir : '';
									?>
									<th class="<?= $sortKey === null ? '' : 'is-sortable' ?><?= $sortClass ?>">
										<?php if ($sortKey === null): ?>
											<span class="th-inner"><?= escape($label) ?></span>
										<?php else: ?>
											<a
												class="th-inner"
												href="<?= escape($queryUrl(['sort' => $sortKey, 'dir' => $nextDir, 'offset' => ''])) ?>"
												hx-target="#main">
												<?= escape($label) ?>
												<span class="sort-ind" aria-hidden="true">⌃</span>
											</a>
										<?php endif ?>
									</th>
								<?php endforeach ?>
								<th class="col-status">Status</th>
								<?php if ($showChildren): ?>
									<th class="col-children">Children</th>
								<?php endif ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($nodes as $node): ?>
								<tr class="collection-row" data-uid="<?= escape((string) $node['uid']) ?>">
									<?php foreach ($node['columns'] as $index => $column): ?>
										<?php

										$label = (string) ($header[$index] ?? 'Column ' . ((int) $index + 1));
										$value = $displayValue($column['value'] ?? '', (bool) ($column['date'] ?? false));
										$classes = ['collection-cell'];

										if ((bool) ($column['bold'] ?? false)) {
											$classes[] = 'is-bold';
										}

										if ((bool) ($column['italic'] ?? false)) {
											$classes[] = 'is-italic';
										}

										if ((bool) ($column['badge'] ?? false)) {
											$classes[] = 'is-badge';
										}
										?>
										<td class="<?= implode(' ', $classes) ?>" data-label="<?= escape($label) ?>">
											<?php if ($index === 0): ?>
												<a
													class="collection-value collection-edit-link"
													href="<?= escape($editorUrl((string) $node['uid'])) ?>"
													hx-target="#main">
													<?= escape($value) ?>
												</a>
											<?php else: ?>
												<span class="collection-value"><?= escape($value) ?></span>
											<?php endif ?>
										</td>
									<?php endforeach ?>
									<td class="collection-cell col-status" data-label="Status">
										<div class="status-list">
											<?php foreach ($statusBadges($node) as $badge): ?>
												<span class="status status-<?= escape($badge['kind']) ?>"><?= escape(
												$badge['label'],
											) ?></span>
											<?php endforeach ?>
										</div>
									</td>
									<?php if ($showChildren): ?>
										<?php

										$childBlueprints = $node['childBlueprints'] ?? [];
										$childBlueprints = $childBlueprints instanceof Traversable
											? iterator_to_array($childBlueprints)
											: (array) $childBlueprints;
										$childBlueprints = array_values(array_filter(
											array_map(static function (mixed $blueprint) use ($blueprintSlugs): array {
												$blueprint = $blueprint instanceof Traversable
													? iterator_to_array($blueprint)
													: (array) $blueprint;
												$slug = trim((string) ($blueprint['slug'] ?? ''));

												return [
													'slug' => in_array($slug, $blueprintSlugs, true) ? $slug : '',
													'name' => trim((string) ($blueprint['name'] ?? '')),
												];
											}, $childBlueprints),
											static fn(array $blueprint): bool => $blueprint['slug'] !== '' && $blueprint['name'] !== '',
										));
										?>
										<td class="collection-cell col-children" data-label="Children">
											<div class="child-actions">
												<?php if ((bool) ($node['hasChildren'] ?? false)): ?>
													<a
														class="child-link"
														href="<?= escape($queryUrl(['parent' => (string) $node['uid'], 'offset' => ''])) ?>"
														hx-target="#main">
														Open children
													</a>
												<?php endif ?>
												<?php foreach ($childBlueprints as $blueprint): ?>
													<a
														class="child-link"
														href="<?= escape($createUrl($blueprint['slug'], (string) $node['uid'])) ?>"
														hx-target="#main">
														Add <?= escape($blueprint['name']) ?>
													</a>
												<?php endforeach ?>
												<?php if (!(bool) ($node['hasChildren'] ?? false) && count($childBlueprints) === 0): ?>
													<span class="child-muted">—</span>
												<?php endif ?>
											</div>
										</td>
									<?php endif ?>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>

			<footer class="list-foot">
				<span class="fcount">Showing <?= $rangeStart ?>–<?= $rangeEnd ?> of <?= $total ?></span>
				<nav class="pagination" aria-label="Pagination">
					<?php if ($offset > 0): ?>
						<a class="page-link" href="<?= escape($queryUrl(['offset' => max(
						0,
						$offset - $limit,
					)])) ?>" hx-target="#main">Previous</a>
					<?php else: ?>
						<span class="page-link is-disabled">Previous</span>
					<?php endif ?>

					<span class="page-status">Page <?= $currentPage ?> of <?= $pageCount ?></span>

					<?php if (($offset + $limit) < $total): ?>
						<a class="page-link" href="<?= escape($queryUrl([
						'offset' => $offset + $limit,
					])) ?>" hx-target="#main">Next</a>
					<?php else: ?>
						<span class="page-link is-disabled">Next</span>
					<?php endif ?>
				</nav>
			</footer>
		</div>
	</section>
</div>
