<?php

use function Cosray\escape;

$defaultCollectionIcon = <<<'SVG'
	<svg class="icon-default" viewBox="0 0 16 16" fill="currentColor" focusable="false"><path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11Zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7ZM0 6a1.5 1.5 0 0 1 1.5-1.5h13A1.5 1.5 0 0 1 16 6v7a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 13V6Zm1.5-.5A.5.5 0 0 0 1 6v7a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5V6a.5.5 0 0 0-.5-.5h-13Z" /></svg>
	SVG;

?>
<?php if (count($collections) > 0): ?>
<ul class="list level-<?= $level ?>">
<?php foreach ($collections as $item): ?>
	<li class="item">
	<?php if ($this->unwrap($item) instanceof \Cosray\NavLink): ?>
		<?php

		$link = $this->unwrap($item);
		$iconMeta = $this->unwrap($item->meta->icon);
		$icon = $iconMeta === null ? $defaultCollectionIcon : $this->unwrap($renderIcon($iconMeta));
		?>
		<a
			class="link"
			data-nav
			style="--depth: <?= $level ?>"
			href="<?= $link->url ?>"
			hx-target="#main"
			<?= $link->activePrefix === null ? '' : 'data-nav-prefix="' . escape($link->activePrefix) . '"' ?>
			<?= $link->active((string) $this->unwrap($currentPath)) ? 'aria-current="page"' : '' ?>>
			<span class="label">
				<?php if ($icon !== ''): ?>
					<span class="icon" aria-hidden="true"><?= $icon ?></span>
				<?php endif ?>
				<span><?= $item->meta->label ?></span>
			</span>
			<?php if (trim((string) $item->meta->badge) !== ''): ?>
				<span class="badge"><?= $item->meta->badge ?></span>
			<?php endif ?>
		</a>
	<?php elseif ($item->slug() !== null): ?>
		<?php

		$href = $panelPath . '/collection/' . $item->slug();
		// Node, create, and paths URLs all live below the collection URL, so
		// the entry stays marked while the user works inside the collection.
		$prefix = $href . '/';
		$active = (string) $currentPath === $href || str_starts_with((string) $currentPath, $prefix);
		$iconMeta = $this->unwrap($item->meta->icon);
		$icon = $iconMeta === null ? $defaultCollectionIcon : $this->unwrap($renderIcon($iconMeta));
		?>
		<a
			class="link"
			data-nav
			style="--depth: <?= $level ?>"
			href="<?= $href ?>"
			hx-target="#main"
			data-nav-prefix="<?= escape($prefix) ?>"
			<?= $active ? 'aria-current="page"' : '' ?>>
			<span class="label">
				<?php if ($icon !== ''): ?>
					<span class="icon" aria-hidden="true"><?= $icon ?></span>
				<?php endif ?>
				<span><?= $item->meta->label ?></span>
			</span>
			<?php if (trim((string) $item->meta->badge) !== ''): ?>
				<span class="badge"><?= $item->meta->badge ?></span>
			<?php endif ?>
		</a>
	<?php else: ?>
		<?php

		$iconMeta = $this->unwrap($item->meta->icon);
		$icon = $iconMeta === null ? '' : $this->unwrap($renderIcon($iconMeta));
		?>
		<div
			class="section"
			style="--depth: <?= $level ?>">
			<span class="title">
				<?php if ($icon !== ''): ?>
					<span class="icon" aria-hidden="true"><?= $icon ?></span>
				<?php endif ?>
				<span><?= $item->meta->label ?></span>
			</span>
			<?php $this->insert('component/collections', [
				'collections' => $item->children(),
				'level' => $level + 1,
			]) ?>
		</div>
	<?php endif ?>
	</li>
<?php endforeach ?>
</ul>
<?php endif ?>
