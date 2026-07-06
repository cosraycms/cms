<aside class="cms-sidebar">
	<header class="header">
		<?php $this->insert('component/logo') ?>
	</header>

	<nav class="navigation" aria-label="Panel navigation">
		<div class="scroll">
			<ul class="nav-list level-0">
				<li class="nav-item">
					<a
						class="nav-link"
						href="<?= $panelPath ?>"
						hx-target="#main"
						<?= (string) $currentPath === (string) $panelPath ? 'aria-current="page"' : '' ?>>
						Dashboard
					</a>
				</li>
				<li class="nav-item">
					<a
						class="nav-link"
						href="<?= $panelPath ?>/media"
						hx-target="#main"
						<?= (string) $currentPath === (string) $panelPath . '/media' ? 'aria-current="page"' : '' ?>>
						Medien
					</a>
				</li>
			</ul>

			<?php $this->insert('component/collections', ['level' => 0]) ?>
		</div>
	</nav>

	<footer class="footer">
		<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
			<button class="action" type="submit">Logout</button>
		</form>
	</footer>
</aside>
