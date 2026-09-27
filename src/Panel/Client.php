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
 * `panel/`, plus the modules other Composer packages ship, such as verba's
 * runtime. Nothing is built, so the files are served as they are under a
 * URL that carries the installed revision, which lets browsers cache every
 * file for good until the next update.
 */
final class Client
{
	public const string SPRITE = 'icons.svg';

	/** The directories below `panel/` a browser may load from. */
	private const array DIRS = ['src', 'styles', 'modules', 'icons'];
	private const array EXTENSIONS = ['js', 'css', 'svg'];

	/** The URL segment for modules that Composer packages ship. */
	private const string COMPOSER = 'composer';

	/**
	 * A static `import … from '…'`, `import '…'` or `export … from '…'`. The
	 * served modules are formatted, unminified source, where statements start
	 * after whitespace or a brace; `import(` and a JSDoc `@import` never match.
	 */
	private const string STATIC_IMPORT = '/(?:^|[\s;{}])(?:import|export)\s+(?:[^\'"`;]*?\sfrom\s*)?[\'"]([^\'"\n]+)[\'"]/';

	public readonly string $dir;
	private ?string $version = null;

	/** @var array{imports: array<string, string>, composer: array<string, string>, scripts: list<string>}|null */
	private ?array $modules = null;

	/** @var array<string, string>|null */
	private ?array $composerDirs = null;

	public function __construct(
		private readonly Config $config,
		?string $dir = null,
	) {
		$this->dir = $dir ?? dirname(__DIR__, 2) . '/panel';
	}

	/**
	 * The URL segment that changes with every installed revision: the start
	 * of the commit Composer installed for Cosray, or a hash of the version
	 * for a package without a commit reference, hashed together with the
	 * revisions of the packages the Composer-shipped modules come from.
	 * Files that change under that commit, in a Git working copy such as a
	 * symlinked path repository or while debugging, get a revision of their
	 * own contents instead.
	 */
	public function version(): string
	{
		return $this->version ??= $this->editable() ? $this->contents() : $this->revision();
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

		if (count($segments) < 2 || !in_array($segments[0], [...self::DIRS, self::COMPOSER], true)) {
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

		if ($segments[0] === self::COMPOSER) {
			return $this->composerFile(implode('/', array_slice($segments, 1)));
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
	 * The import map for the vendored third-party modules and the modules
	 * Composer packages ship, resolved to versioned URLs.
	 *
	 * @return array{imports: array<string, string>}
	 */
	public function importMap(): array
	{
		$imports = array_map(
			fn(string $path): string => $this->url('modules/' . $path),
			$this->modules()['imports'],
		);
		$this->composerDirs();

		foreach ($this->modules()['composer'] as $specifier => $path) {
			$imports[$specifier] = $this->url(self::COMPOSER . '/' . $path);
		}

		return ['imports' => $imports];
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
		$composer = $this->modules()['composer'];
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
					isset($composer[$specifier]) => $this->composerFile($composer[$specifier]) ?? false,
					default => false,
				};
				$path = $target === false ? null : $this->served($target, $root);

				if ($target === false || $path === null || isset($seen[$target])) {
					continue;
				}

				$seen[$target] = true;
				$queue[] = $target;
				$urls[] = $this->url($path);
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

	/** @return array{imports: array<string, string>, composer: array<string, string>, scripts: list<string>} */
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

		$modules = ['imports' => [], 'composer' => [], 'scripts' => []];

		foreach (['imports', 'composer'] as $key) {
			if (is_array($data) && is_array($data[$key] ?? null)) {
				foreach ($data[$key] as $specifier => $path) {
					if (is_string($specifier) && is_string($path)) {
						$modules[$key][$specifier] = $path;
					}
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

	/**
	 * The directory each module a Composer package ships is served from, as
	 * the real path keyed by its path below the `composer` URL segment:
	 * `celema/verba/js/src` for `celema/verba/js/src/index.js`. Only that
	 * directory is served, never the rest of the package.
	 *
	 * @return array<string, string>
	 */
	private function composerDirs(): array
	{
		if ($this->composerDirs !== null) {
			return $this->composerDirs;
		}

		$dirs = [];

		foreach ($this->modules()['composer'] as $specifier => $path) {
			$segments = explode('/', $path);

			if (count($segments) < 3 || array_intersect($segments, ['', '.', '..']) !== []) {
				throw new RuntimeException("Invalid panel module path {$path} for {$specifier}");
			}

			$package = $segments[0] . '/' . $segments[1];

			try {
				$installed = InstalledVersions::getInstallPath($package);
			} catch (OutOfBoundsException) {
				$installed = null;
			}

			$file = $installed === null ? false : realpath($installed . '/' . implode('/', array_slice($segments, 2)));

			if ($file === false || !is_file($file)) {
				throw new RuntimeException(
					"The panel module {$specifier} needs {$path}, which the installed {$package} lacks; "
						. "run `composer update {$package}`",
				);
			}

			$dirs[dirname($path)] = dirname($file);
		}

		return $this->composerDirs = $dirs;
	}

	/** The file a path below the `composer` URL segment names, if served. */
	private function composerFile(string $path): ?string
	{
		if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'js') {
			return null;
		}

		foreach ($this->composerDirs() as $prefix => $dir) {
			if (!str_starts_with($path, $prefix . '/')) {
				continue;
			}

			try {
				return Path::inside($dir, substr($path, strlen($prefix) + 1), checkIsFile: true);
			} catch (RuntimeException) {
				return null;
			}
		}

		return null;
	}

	/** The URL path below the revision a real file is served under, if any. */
	private function served(string $file, string $root): ?string
	{
		if (str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
			return str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));
		}

		foreach ($this->composerDirs() as $prefix => $dir) {
			if (str_starts_with($file, $dir . DIRECTORY_SEPARATOR)) {
				$rest = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($dir) + 1));

				return self::COMPOSER . '/' . $prefix . '/' . $rest;
			}
		}

		return null;
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
		$dirs = [
			...array_map(fn(string $dir): string => $this->dir . '/' . $dir, self::DIRS),
			...array_values($this->composerDirs()),
		];

		foreach ($dirs as $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			$paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
				$dir,
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

	/** Cosray's installed revision, combined with those of the module packages. */
	private function revision(): string
	{
		$revision = self::installed('cosray/cms');
		$packages = [];

		foreach (array_keys($this->composerDirs()) as $prefix) {
			$packages[] = implode('/', array_slice(explode('/', $prefix), 0, 2));
		}

		if ($revision === 'dev' || $packages === []) {
			return $revision;
		}

		$parts = [$revision];

		foreach (array_unique($packages) as $package) {
			$parts[] = $package . '@' . self::installed($package);
		}

		return substr(sha1(implode("\n", $parts)), 0, 12);
	}

	private static function installed(string $package): string
	{
		try {
			$reference = InstalledVersions::getReference($package);
			$version = InstalledVersions::getPrettyVersion($package);
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
