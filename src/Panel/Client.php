<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Composer\InstalledVersions;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;
use FilesystemIterator;
use JsonException;
use OutOfBoundsException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The panel's browser-side files as they ship in the package: plain ES
 * modules, stylesheets, icons and the committed third-party modules under
 * `panel/`. Nothing is built, so the package tree is served as is under a
 * URL that carries the installed revision, which lets browsers cache every
 * file for good until the next update.
 */
final class Client
{
	public const string SPRITE = 'icons.svg';

	/** The directories below `panel/` a browser may load from. */
	private const array DIRS = ['src', 'styles', 'modules', 'icons'];
	private const array EXTENSIONS = ['js', 'css', 'svg'];

	/**
	 * A static `import … from '…'`, `import '…'` or `export … from '…'`. The
	 * served modules are formatted, unminified source, where statements start
	 * after whitespace or a brace; `import(` and a JSDoc `@import` never match.
	 */
	private const string STATIC_IMPORT = '/(?:^|[\s;{}])(?:import|export)\s+(?:[^\'"`;]*?\sfrom\s*)?[\'"]([^\'"\n]+)[\'"]/';

	public readonly string $dir;
	private ?string $version = null;

	/** @var array{imports: array<string, string>, scripts: list<string>}|null */
	private ?array $modules = null;

	public function __construct(
		private readonly Config $config,
		?string $dir = null,
	) {
		$this->dir = $dir ?? dirname(__DIR__, 2) . '/panel';
	}

	/**
	 * The URL segment that changes with every installed Cosray revision:
	 * the start of the commit Composer installed, or a hash of the version
	 * for packages without a commit reference. Files that change under that
	 * commit, in a Git working copy such as a symlinked path repository or
	 * while debugging, get a revision of their own contents instead.
	 */
	public function version(): string
	{
		return $this->version ??= $this->editable() ? $this->contents() : self::revision();
	}

	public function url(string $path = ''): string
	{
		return rtrim($this->config->panel->path, '/') . '/assets/' . $this->version() . '/' . ltrim($path, '/');
	}

	/**
	 * Whether a response for a URL carrying `$version` may be cached forever.
	 * Only the current revision qualifies, so a page rendered before an update
	 * or an edit never pins new content under its old URL.
	 */
	public function immutable(string $version): bool
	{
		return $version === $this->version() && $version !== 'dev';
	}

	/**
	 * The file a URL path below the version segment names, or null when it is
	 * missing or outside the servable directories and file types.
	 */
	public function file(string $slug): ?string
	{
		$segments = explode('/', $slug);

		if (count($segments) < 2 || !in_array($segments[0], self::DIRS, true)) {
			return null;
		}

		foreach ($segments as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return null;
			}
		}

		if (!in_array(strtolower(pathinfo($slug, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
			return null;
		}

		try {
			return Path::inside(
				$this->dir . '/' . $segments[0],
				implode('/', array_slice($segments, 1)),
				checkIsFile: true,
			);
		} catch (RuntimeException) {
			return null;
		}
	}

	/**
	 * The import map for the vendored third-party modules, resolved to
	 * versioned URLs.
	 *
	 * @return array{imports: array<string, string>}
	 */
	public function importMap(): array
	{
		return [
			'imports' => array_map(
				fn(string $path): string => $this->url('modules/' . $path),
				$this->modules()['imports'],
			),
		];
	}

	/**
	 * Vendored classic scripts, which define globals rather than exports.
	 *
	 * @return list<string>
	 */
	public function scripts(): array
	{
		return array_map(
			fn(string $path): string => $this->url('modules/' . $path),
			$this->modules()['scripts'],
		);
	}

	/**
	 * Every module `$entry` imports statically, directly or through others, for
	 * `<link rel="modulepreload">`: the browser then fetches the graph at once
	 * instead of one import level after the other. Dynamic imports, such as
	 * the element controls, load on demand and stay out.
	 *
	 * @return list<string>
	 */
	public function preloads(string $entry = 'src/panel.js'): array
	{
		$root = realpath($this->dir);
		$start = $root === false ? false : realpath($root . '/' . $entry);

		if ($root === false || $start === false) {
			return [];
		}

		$imports = $this->modules()['imports'];
		$seen = [$start => true];
		$queue = [$start];
		$urls = [];

		while ($queue !== []) {
			$file = array_shift($queue);
			$matches = [];
			preg_match_all(self::STATIC_IMPORT, (string) file_get_contents($file), $matches);

			foreach ($matches[1] as $specifier) {
				$target = match (true) {
					str_starts_with($specifier, './'), str_starts_with($specifier, '../') => realpath(
						dirname($file) . '/' . $specifier,
					),
					isset($imports[$specifier]) => realpath($root . '/modules/' . $imports[$specifier]),
					default => false,
				};

				if (
					$target === false
					|| isset($seen[$target])
					|| !str_starts_with($target, $root . DIRECTORY_SEPARATOR)
				) {
					continue;
				}

				$seen[$target] = true;
				$queue[] = $target;
				$urls[] = $this->url(str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($root) + 1)));
			}
		}

		return $urls;
	}

	/**
	 * All panel icons as one SVG of symbols, so script-built markup can
	 * reference any icon with `<use href="…/icons.svg#name">`.
	 */
	public function sprite(): string
	{
		$symbols = [];
		$files = glob($this->dir . '/icons/*.svg') ?: [];
		sort($files);

		foreach ($files as $file) {
			$name = basename($file, '.svg');

			if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $name) !== 1) {
				continue;
			}

			$parts = [];

			if (preg_match('~<svg\b([^>]*)>(.*)</svg>~s', (string) file_get_contents($file), $parts) !== 1) {
				continue;
			}

			// The symbol keeps the attributes that shape its drawing; size and
			// classes belong to each use.
			$attributes = '';

			foreach (['viewBox', 'fill'] as $attribute) {
				$match = [];

				if (preg_match('/\s' . $attribute . '="([^"]*)"/', $parts[1], $match) === 1) {
					$attributes .= " {$attribute}=\"{$match[1]}\"";
				}
			}

			$symbols[] = "<symbol id=\"{$name}\"{$attributes}>" . trim($parts[2]) . '</symbol>';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg">' . implode('', $symbols) . "</svg>\n";
	}

	/** @return array{imports: array<string, string>, scripts: list<string>} */
	private function modules(): array
	{
		if ($this->modules !== null) {
			return $this->modules;
		}

		$file = $this->dir . '/modules/importmap.json';
		$data = [];

		if (is_file($file)) {
			try {
				$data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new RuntimeException("Invalid panel import map {$file}: {$e->getMessage()}", previous: $e);
			}
		}

		$modules = ['imports' => [], 'scripts' => []];

		if (is_array($data) && is_array($data['imports'] ?? null)) {
			foreach ($data['imports'] as $specifier => $path) {
				if (is_string($specifier) && is_string($path)) {
					$modules['imports'][$specifier] = $path;
				}
			}
		}

		if (is_array($data) && is_array($data['scripts'] ?? null)) {
			foreach ($data['scripts'] as $path) {
				if (is_string($path)) {
					$modules['scripts'][] = $path;
				}
			}
		}

		return $this->modules = $modules;
	}

	/** Whether the files may change without Composer installing anything. */
	private function editable(): bool
	{
		return $this->config->debug() || file_exists(dirname($this->dir) . '/.git');
	}

	/**
	 * A hash of every servable file's path, size and modification time: any
	 * edit, addition or removal yields new URLs. Modification times count
	 * whole seconds, so a file changed within the last two also adds its
	 * contents; two saves in one second would otherwise share a revision.
	 */
	private function contents(): string
	{
		$files = [];
		$recent = time() - 2;

		foreach (self::DIRS as $dir) {
			if (!is_dir($this->dir . '/' . $dir)) {
				continue;
			}

			$paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
				$this->dir . '/' . $dir,
				FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME,
			));

			foreach ($paths as $path) {
				$stat = stat((string) $path);

				if ($stat === false) {
					continue;
				}

				$file = "{$path}:{$stat['size']}:{$stat['mtime']}";
				$files[] = $stat['mtime'] >= $recent ? $file . ':' . hash_file('xxh128', (string) $path) : $file;
			}
		}

		sort($files);

		return substr(sha1(implode("\n", $files)), 0, 12);
	}

	private static function revision(): string
	{
		try {
			$reference = InstalledVersions::getReference('cosray/cms');
			$version = InstalledVersions::getPrettyVersion('cosray/cms');
		} catch (OutOfBoundsException) {
			return 'dev';
		}

		if ($reference !== null && preg_match('/\A[0-9a-f]{12,}\z/i', $reference) === 1) {
			return strtolower(substr($reference, 0, 12));
		}

		if ($version === null || $version === '') {
			return 'dev';
		}

		return substr(sha1($version . '@' . ($reference ?? '')), 0, 12);
	}
}
