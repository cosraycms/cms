<?php

use function Cosray\escape;

$this->layout('layer/main');

$menus = (array) $this->unwrap($menus);
$notice = $this->unwrap($notice ?? null);
$createUrl = (string) $createUrl;

$confirm = static function (array $menu): string {
	if ($menu['items'] === 0) {
		return __('menu:confirm-delete-empty', ['menu' => $menu['menu']]);
	}

	return __n(
		'menu:confirm-delete',
		'menu:confirm-delete-plural',
		$menu['items'],
		['menu' => $menu['menu']],
	);
};
?>

<div class="page cms-menus">
	<header class="head">
		<div class="line">
			<h1><?= escape(__('menu:menus')) ?></h1>
			<span class="cms-count"><?= escape(__n(
				'menu:count',
				'menu:count-plural',
				count($menus),
			)) ?></span>
		</div>

		<div class="actions">
			<a class="cms-button primary" href="<?= escape($createUrl) ?>"><?= escape(
				__('menu:new'),
			) ?></a>
		</div>
	</header>

	<div class="body">
		<?php if (is_string($notice)): ?>
			<div class="cms-notice" role="status">
				<p><?= escape($notice) ?></p>
			</div>
		<?php endif ?>

		<div class="card">
			<?php if (count($menus) === 0): ?>
				<div class="empty">
					<div class="icon" aria-hidden="true">☰</div>
					<strong><?= escape(__('menu:empty')) ?></strong>
					<p><?= escape(__('menu:empty-help')) ?></p>
				</div>
			<?php else: ?>
				<div class="scroll">
					<table
						class="cms-list"
						role="table"
						style="--columns: minmax(10rem, 1fr) minmax(12rem, 2fr) max-content max-content">
						<thead role="rowgroup">
							<tr role="row">
								<th role="columnheader"><span class="inner"><?= escape(__('menu:handle')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('menu:description')) ?></span></th>
								<th role="columnheader"><span class="inner"><?= escape(__('menu:items')) ?></span></th>
								<th class="col-actions" role="columnheader"></th>
							</tr>
						</thead>
						<tbody role="rowgroup">
							<?php foreach ($menus as $menu): ?>
								<tr role="row">
									<td role="cell">
										<a class="handle" href="<?= escape($menu['editUrl']) ?>"><?= escape(
											$menu['menu'],
										) ?></a>
									</td>
									<td role="cell"><?= escape($menu['description']) ?></td>
									<td role="cell"><?= escape(__n(
										'menu:item-count',
										'menu:item-count-plural',
										$menu['items'],
									)) ?></td>
									<td class="col-actions" role="cell">
										<a class="cms-button secondary" href="<?= escape($menu['editUrl']) ?>"><?= escape(
											__('menu:edit'),
										) ?></a>
										<form
											method="post"
											action="<?= escape($menu['deleteUrl']) ?>"
											hx-confirm="<?= escape($confirm($menu)) ?>">
											<button type="submit" class="cms-button danger"><?= escape(
												__('menu:delete'),
											) ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			<?php endif ?>
		</div>
	</div>
</div>
