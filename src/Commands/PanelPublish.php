<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\Client;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

#[Command('panel:publish', 'Publishes panel browser files into the public directory', group: 'Panel')]
final class PanelPublish
{
	public function __construct(
		private readonly Config $config,
		private readonly Client $client,
	) {}

	public function __invoke(Args $args, Io $io): int
	{
		$panel = trim($this->config->panel->path, '/');
		$segments = $panel === '' ? [] : explode('/', $panel);

		foreach ($segments as $segment) {
			if (preg_match('/\A[a-zA-Z0-9_.-]+\z/', $segment) !== 1 || in_array($segment, ['.', '..'], true)) {
				throw new RuntimeException('panel.path must be a local URL path without traversal segments.');
			}
		}

		$version = $this->client->version();

		if ($version === 'dev') {
			throw new RuntimeException(
				'Cannot publish panel assets without a revision. Install Cosray with Composer first.',
			);
		}

		$files = iterator_to_array($this->client->files());
		$sprite = $this->client->sprite();
		$base = rtrim($this->config->path->public, '/');
		$this->directory($base);

		// Do not let a pre-existing symlink redirect publication outside the
		// configured document root, or into application-owned directories.
		foreach ([...$segments, 'assets'] as $segment) {
			$base .= '/' . $segment;
			$this->directory($base);
		}

		$target = $base . '/' . $version;

		if (is_link($target)) {
			throw new RuntimeException("Refusing to publish through symlink {$target}");
		}

		if (file_exists($target)) {
			// Never replace bytes already served with an immutable URL. A
			// matching publication is a harmless repeat of a deployment step.
			foreach ($files as $slug => $source) {
				$published = $target . '/' . $slug;
				if (!is_file($published) || hash_file('sha256', $source) !== hash_file('sha256', $published)) {
					throw new RuntimeException(
						"Published panel revision differs at {$published}; refusing to overwrite it.",
					);
				}
			}

			if (
				!is_file($target . '/' . Client::SPRITE)
				|| file_get_contents($target . '/' . Client::SPRITE) !== $sprite
			) {
				throw new RuntimeException("Published panel revision differs at {$target}; refusing to overwrite it.");
			}

			$io->echoln("Panel assets already published to {$target}");

			return 0;
		}

		$stage = $base . '/.publish-' . bin2hex(random_bytes(8));
		$this->directory($stage);

		try {
			foreach ($files as $slug => $source) {
				$destination = $stage . '/' . $slug;
				$this->directory(dirname($destination));

				if (!copy($source, $destination)) {
					throw new RuntimeException("Cannot copy panel asset {$source} to {$destination}");
				}
			}

			if (file_put_contents($stage . '/' . Client::SPRITE, $sprite) === false) {
				throw new RuntimeException('Cannot write the panel icon sprite.');
			}

			// Publish a complete revision at once; leave older revisions in
			// place for open browser tabs and deployment rollbacks.
			if (!rename($stage, $target)) {
				throw new RuntimeException("Cannot publish panel assets to {$target}");
			}
		} finally {
			if (is_dir($stage)) {
				$paths = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
					RecursiveIteratorIterator::CHILD_FIRST,
				);

				foreach ($paths as $path) {
					$path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
				}

				rmdir($stage);
			}
		}

		$io->echoln('Published ' . (count($files) + 1) . " panel assets to {$target}");

		return 0;
	}

	private function directory(string $path): void
	{
		if (is_link($path)) {
			throw new RuntimeException("Refusing to publish through symlink {$path}");
		}

		if (!is_dir($path) && !mkdir($path, 0o755, recursive: true) && !is_dir($path)) {
			throw new RuntimeException("Cannot create panel asset directory {$path}");
		}
	}
}
