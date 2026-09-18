<?php

use function Cosray\escape;

?>
<?php if (count($collections) > 0): ?>
<ul class="list level-<?= $level ?>">
<?php foreach ($collections as $item): ?>
	<?php $label = escape(__((string) $this->unwrap($item->meta->label))) ?>
	<li class="item">
	<?php if ($this->unwrap($item) instanceof \Cosray\NavLink): ?>
		<?php

		$link = $this->unwrap($item);
		$iconMeta = $this->unwrap($item->meta->icon);
		?>
		<a
			class="link"
			style="--depth: <?= $level ?>"
			href="<?= $link->url ?>"
			<?= $link->active((string) $this->unwrap($currentPath)) ? 'aria-current="page"' : '' ?>>
			<span class="label">
				<?php $this->insert('component/collection-icon', [
					'iconMeta' => $iconMeta,
					'default' => true,
				]) ?>
				<span><?= $label ?></span>
			</span>
			<?php if (trim((string) $item->meta->badge) !== ''): ?>
				<span class="badge"><?= $item->meta->badge ?></span>
			<?php endif ?>
		</a>
	<?php elseif ($item->slug() !== null): ?>
		<?php

		$href = $panelPath . '/collection/' . $item->slug();
		$active =
			(string) $currentPath === $href
			|| (string) ($activeCollection ?? '') === (string) $item->slug();
		$iconMeta = $this->unwrap($item->meta->icon);
		?>
		<a
			class="link"
			style="--depth: <?= $level ?>"
			href="<?= $href ?>"
			<?= $active ? 'aria-current="page"' : '' ?>>
			<span class="label">
				<?php $this->insert('component/collection-icon', [
					'iconMeta' => $iconMeta,
					'default' => true,
				]) ?>
				<span><?= $label ?></span>
			</span>
			<?php if (trim((string) $item->meta->badge) !== ''): ?>
				<span class="badge"><?= $item->meta->badge ?></span>
			<?php endif ?>
		</a>
	<?php else: ?>
		<?php

		$iconMeta = $this->unwrap($item->meta->icon);
		?>
		<div
			class="section"
			style="--depth: <?= $level ?>">
			<span class="title">
				<?php $this->insert('component/collection-icon', ['iconMeta' => $iconMeta]) ?>
				<span><?= $label ?></span>
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
