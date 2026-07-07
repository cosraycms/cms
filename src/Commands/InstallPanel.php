<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celemas\Cli\Command;
use Composer\InstalledVersions;
use Cosray\Config;
use JsonException;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class InstallPanel extends Command
{
	protected string $group = 'Panel';
	protected string $prefix = 'panel';
	protected string $name = 'install';
	protected string $description = 'Installs or upgrades the Cosray panel client assets';

	/** Ed25519 public key (base64, raw 32 bytes) matching the CI release signing key. */
	private const string PANEL_PUBKEY = 'AYqozdEV8YlYCgbTVafXab+jvcXAmehXgkv1RLtgDng=';
	private const string RELEASE_BASE_URL = 'https://cosray.dev/releases';
	private const string MANIFEST = 'cosray-panel.json';

	private string $cmsVersion = 'unknown';
	private string $panelReleaseTag = 'nightly';
	private string $panelFileName = 'cosray-panel-nightly.tar.gz';
	private string $panelUrl = 'https://cosray.dev/releases/cosray-panel-nightly.tar.gz';
	private string $panelSignatureUrl = 'https://cosray.dev/releases/cosray-panel-nightly.tar.gz.sig';

	/** @var array<string, string>|null */
	private ?array $options = null;

	public function __construct(
		private readonly Config $config,
	) {}

	public function run(): int
	{
		if ($this->wantsHelp()) {
			$this->help();

			return 0;
		}

		try {
			$cmsVersion =
				$this->option('release') ?? InstalledVersions::getPrettyVersion('cosray/cms') ?? '';
			$this->cmsVersion = $cmsVersion !== '' ? $cmsVersion : 'unknown';
		} catch (Throwable $e) {
			$this->error("Failed to determine installed version: {$e->getMessage()}");

			return 1;
		}

		try {
			$this->preparePanelDownload($this->cmsVersion);
			$target = $this->targetDir();

			$this->info('Installing panel assets: ' . $this->versionLabel());
			$this->info("Target: {$target}");

			$panelArchive = $this->downloadRelease();

			try {
				$this->installArchive($panelArchive, $target);
			} finally {
				$this->removePath($panelArchive);
			}
		} catch (Throwable $e) {
			$this->error($e->getMessage());

			return 1;
		}

		$this->success("Panel assets installed from {$this->panelFileName}");

		return 0;
	}

	public function help(): void
	{
		$this->helpHeader(withOptions: true);
		$this->helpOption(
			'--panel=/cp',
			'Override the configured panel path when the command is not registered with the app Config.',
		);
		$this->helpOption(
			'--public=public',
			'Override the configured public directory. Relative paths resolve from the current working directory.',
		);
		$this->helpOption(
			'--release=0.3.0',
			'Install a specific panel release tag instead of the installed Composer version. Use nightly for the rolling main build.',
		);
		$this->helpOption(
			'--base-url=https://cosray.dev/releases',
			'Override the panel release base URL.',
		);
	}

	private function downloadRelease(): string
	{
		$tempFile = $this->tempFile('cosray_panel_', '.tar.gz');

		$this->info("Downloading {$this->panelFileName} from {$this->panelUrl}...");

		$content = $this->download($this->panelUrl);

		if ($content === false) {
			$this->removePath($tempFile);

			throw new RuntimeException("Failed to download {$this->panelFileName} from {$this->panelUrl}");
		}

		if (file_put_contents($tempFile, $content) === false) {
			$this->removePath($tempFile);

			throw new RuntimeException('Failed to save panel archive to a temporary file');
		}

		try {
			$this->verifySignature($tempFile);
		} catch (Throwable $e) {
			$this->removePath($tempFile);

			throw $e;
		}

		$this->success("Downloaded and verified {$this->panelFileName}");

		return $tempFile;
	}

	/** @return string|false */
	protected function download(string $url): string|false
	{
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => 'User-Agent: Cosray-CMS-Panel-Installer',
				'follow_location' => true,
			],
		]);

		return file_get_contents($url, false, $context);
	}

	private function verifySignature(string $archivePath): void
	{
		$this->info("Verifying {$this->panelFileName} signature from {$this->panelSignatureUrl}...");

		$content = $this->download($this->panelSignatureUrl);

		if ($content === false) {
			throw new RuntimeException("Failed to download signature from {$this->panelSignatureUrl}");
		}

		$signature = base64_decode(trim($content), true);

		if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
			throw new RuntimeException('Signature file does not contain a valid Ed25519 signature');
		}

		$key = base64_decode(self::PANEL_PUBKEY, true);

		if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
			throw new RuntimeException('Embedded panel signing public key is invalid');
		}

		$archive = file_get_contents($archivePath);

		if ($archive === false) {
			throw new RuntimeException("Failed to read downloaded panel archive: {$archivePath}");
		}

		if (!sodium_crypto_sign_verify_detached($signature, $archive, $key)) {
			throw new RuntimeException("Signature verification failed for {$this->panelFileName}");
		}
	}

	private function installArchive(string $archivePath, string $target): void
	{
		$parent = dirname($target);
		$this->ensureDirectory($parent);

		$temp = $parent . '/.' . basename($target) . '-' . bin2hex(random_bytes(8));
		$this->ensureDirectory($temp);

		try {
			$this->extractArchive($archivePath, $temp);
			$this->validatePanel($temp);
			$this->replaceDirectory($temp, $target);
			$temp = '';
		} finally {
			if ($temp !== '') {
				$this->removePath($temp);
			}
		}
	}

	private function extractArchive(string $archivePath, string $destination): void
	{
		try {
			$phar = new PharData($archivePath);
			$phar->extractTo($destination, null, true);
		} catch (Throwable $e) {
			throw new RuntimeException(
				"Failed to extract {$this->panelFileName}: {$e->getMessage()}",
				previous: $e,
			);
		}
	}

	private function validatePanel(string $dir): void
	{
		foreach ([self::MANIFEST, 'panel.css', 'panel.js'] as $file) {
			if (!is_file("{$dir}/{$file}")) {
				throw new RuntimeException("Panel archive is missing {$file}");
			}
		}

		try {
			$manifest = json_decode(
				(string) file_get_contents("{$dir}/" . self::MANIFEST),
				true,
				flags: JSON_THROW_ON_ERROR,
			);
		} catch (JsonException $e) {
			throw new RuntimeException('Panel archive manifest is invalid JSON', previous: $e);
		}

		if (!is_array($manifest)) {
			throw new RuntimeException('Panel archive manifest must be a JSON object');
		}

		if (
			($manifest['name'] ?? null) !== 'cosray-panel'
			|| ($manifest['target'] ?? null) !== 'static'
		) {
			throw new RuntimeException(
				'Panel archive manifest does not describe Cosray static panel assets',
			);
		}
	}

	private function replaceDirectory(string $source, string $target): void
	{
		$backup = '';

		if (file_exists($target) || is_link($target)) {
			$backup = $target . '.bak-' . bin2hex(random_bytes(8));

			if (!rename($target, $backup)) {
				throw new RuntimeException("Failed to move existing panel assets at {$target}");
			}
		}

		if (!rename($source, $target)) {
			if ($backup !== '') {
				rename($backup, $target);
			}

			throw new RuntimeException("Failed to move new panel assets into {$target}");
		}

		if ($backup !== '') {
			$this->removePath($backup);
		}
	}

	private function preparePanelDownload(string $version): void
	{
		$tag = $this->resolvePanelReleaseTag($version);
		$fileTag = str_replace('/', '-', $tag);
		$file = $tag === 'nightly' ? 'cosray-panel-nightly.tar.gz' : "cosray-panel-{$fileTag}.tar.gz";
		$url = $this->releaseBaseUrl() . "/{$file}";

		$this->panelReleaseTag = $tag;
		$this->panelFileName = $file;
		$this->panelUrl = $url;
		$this->panelSignatureUrl = "{$url}.sig";
	}

	private function resolvePanelReleaseTag(string $version): string
	{
		if (
			$version === ''
			|| $version === 'nightly'
			|| $version === 'dev-main'
			|| str_starts_with($version, 'dev-')
		) {
			return 'nightly';
		}

		if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $version) === 1) {
			return $version;
		}

		$this->warn("Unknown version format `{$version}`, falling back to nightly panel release");

		return 'nightly';
	}

	private function targetDir(): string
	{
		return $this->publicPanelDir() . '/static';
	}

	private function publicPanelDir(): string
	{
		$path = trim($this->panelPath(), '/');
		$public = rtrim($this->publicPath(), '/\\');

		return $path === '' ? $public : "{$public}/{$path}";
	}

	private function panelPath(): string
	{
		$panel = $this->option('panel') ?? $this->config->panel->path;
		$panel = trim($panel);

		if ($panel === '' || $panel === '/') {
			return '';
		}

		return str_starts_with($panel, '/') ? $panel : "/{$panel}";
	}

	private function publicPath(): string
	{
		return $this->absolutePath($this->option('public') ?? $this->config->path->public);
	}

	private function releaseBaseUrl(): string
	{
		return rtrim(
			$this->option('base-url') ?? $this->env('COSRAY_PANEL_RELEASE_BASE_URL')
				?? self::RELEASE_BASE_URL,
			'/',
		);
	}

	private function absolutePath(string $path): string
	{
		if (preg_match('~^(?:[A-Za-z]:)?[\\/]~', $path) === 1) {
			return rtrim($path, '/\\');
		}

		$cwd = getcwd();

		if ($cwd === false) {
			throw new RuntimeException('Failed to resolve the current working directory');
		}

		return rtrim($cwd, '/\\') . '/' . trim($path, '/\\');
	}

	private function versionLabel(): string
	{
		return "cosray/cms@{$this->cmsVersion} (panel {$this->panelReleaseTag})";
	}

	private function tempFile(string $prefix, string $suffix): string
	{
		$temp = tempnam(sys_get_temp_dir(), $prefix);

		if ($temp === false) {
			throw new RuntimeException('Failed to create a temporary file');
		}

		$path = $temp . $suffix;

		if (!rename($temp, $path)) {
			$this->removePath($temp);

			throw new RuntimeException('Failed to prepare a temporary file');
		}

		return $path;
	}

	private function ensureDirectory(string $path): void
	{
		if (is_dir($path)) {
			return;
		}

		if (!mkdir($path, 0o775, true) && !is_dir($path)) {
			throw new RuntimeException("Failed to create directory: {$path}");
		}
	}

	private function removePath(string $path): void
	{
		if ($path === '' || !file_exists($path) && !is_link($path)) {
			return;
		}

		if (is_file($path) || is_link($path)) {
			unlink($path);

			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($path);
	}

	private function wantsHelp(): bool
	{
		return in_array('--help', $this->args(), true) || in_array('-h', $this->args(), true);
	}

	private function option(string $name): ?string
	{
		$options = $this->options();

		return $options[$name] ?? null;
	}

	/** @return array<string, string> */
	private function options(): array
	{
		if ($this->options !== null) {
			return $this->options;
		}

		$options = [];

		foreach ($this->args() as $arg) {
			if ($arg === '--help' || $arg === '-h') {
				continue;
			}

			if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
				throw new RuntimeException("Unknown panel install option: {$arg}");
			}

			[$name, $value] = explode('=', substr($arg, 2), 2);

			if (!in_array($name, ['panel', 'public', 'release', 'base-url'], true)) {
				throw new RuntimeException("Unknown panel install option: --{$name}");
			}

			if ($value === '') {
				throw new RuntimeException("Panel install option --{$name} needs a value");
			}

			$options[$name] = $value;
		}

		$this->options = $options;

		return $options;
	}

	/** @return list<string> */
	private function args(): array
	{
		$argv = $_SERVER['argv'] ?? [];
		$command = strtolower((string) ($argv[1] ?? ''));

		if (!in_array($command, [$this->name, $this->prefix() . ':' . $this->name], true)) {
			return [];
		}

		return array_slice($argv, 2);
	}

	private function env(string $key): ?string
	{
		$value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

		return is_string($value) && $value !== '' ? $value : null;
	}
}
