<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celemas\Cli\Command;
use Composer\InstalledVersions;
use Cosray\Config;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class InstallPanel extends Command
{
	protected string $group = 'Admin';
	protected string $name = 'install-panel';
	protected string $description = 'Installs or upgrades the legacy Svelte admin panel app';
	protected string $publicPath;
	private string $cmsVersion = 'unknown';
	private string $panelReleaseTag = 'nightly';
	private string $panelFileName = 'panel-nightly.tar.gz';
	private string $panelUrl = 'https://cosray.dev/releases/panel-nightly.tar.gz';
	private string $panelSignatureUrl = 'https://cosray.dev/releases/panel-nightly.tar.gz.sig';

	private const string PANEL_PATH = '/panel';
	private const string RELEASE_BASE_URL = 'https://cosray.dev/releases';

	/** Ed25519 public key (base64, raw 32 bytes) matching the CI release signing key */
	private const string PANEL_PUBKEY = 'AYqozdEV8YlYCgbTVafXab+jvcXAmehXgkv1RLtgDng=';

	public function __construct(Config $config)
	{
		$this->publicPath = $config->path->public . self::PANEL_PATH;
	}

	public function run(): int
	{
		try {
			$cmsVersion = InstalledVersions::getPrettyVersion('cosray/cms') ?? '';
			$this->cmsVersion = $cmsVersion !== '' ? $cmsVersion : 'unknown';
		} catch (Throwable $e) {
			$this->error("Failed to determine installed version: {$e->getMessage()}");

			return 1;
		}

		$this->preparePanelDownload($cmsVersion);

		$this->info('Installing admin panel version: ' . $this->versionLabel());

		$panelArchive = $this->downloadRelease();

		if ($panelArchive === '') {
			return 1;
		}

		$this->removeDirectory($this->publicPath);

		if (!$this->extractArchive($panelArchive, $this->publicPath)) {
			return 1;
		}

		$this->success("Legacy panel installed from {$this->panelFileName}");

		return 0;
	}

	private function removeDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$this->info("Removing existing panel directory at {$path}...");

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				if (!rmdir($file->getPathname())) {
					$this->error("Failed to remove directory: {$file->getPathname()}");

					return;
				}
			} else {
				if (!unlink($file->getPathname())) {
					$this->error("Failed to remove file: {$file->getPathname()}");

					return;
				}
			}
		}

		if (!rmdir($path)) {
			$this->error("Failed to remove root directory: {$path}");

			return;
		}

		$this->success('Removed existing panel directory');
	}

	private function extractArchive(string $archivePath, string $destination): bool
	{
		$this->info("Extracting {$this->panelFileName} to {$destination}...");

		$tarGzPath = null;

		try {
			// Rename the archive to have a .tar.gz extension (required by PharData)
			$tarGzPath = $archivePath . '.tar.gz';

			if (!rename($archivePath, $tarGzPath)) {
				throw new RuntimeException('Failed to rename archive');
			}

			// Open the .tar.gz archive
			$phar = new PharData($tarGzPath);

			// Ensure destination directory exists
			if (!is_dir($destination) && !mkdir($destination, 0o775, true)) {
				throw new RuntimeException("Failed to create destination directory: {$destination}");
			}

			// Extract all files to destination
			$phar->extractTo($destination, null, true);

			return true;
		} catch (Throwable $e) {
			$this->error("Failed to extract archive: {$e->getMessage()}");

			return false;

			// Clean up on error if archive was renamed
		} finally {
			if ($tarGzPath !== null) {
				// @unlink($tarGzPath);
			}
		}
	}

	private function downloadRelease(): string
	{
		$tempFile = tempnam(sys_get_temp_dir(), 'cms_panel_');

		if ($tempFile === false) {
			$this->error('Failed to create temp file for panel archive');

			return '';
		}

		$this->info("Downloading {$this->panelFileName} from {$this->panelUrl}...");

		$content = $this->download($this->panelUrl);

		if ($content === false) {
			$this->error("Failed to download {$this->panelFileName} from {$this->panelUrl}");
			$this->removeTempFile($tempFile);

			return '';
		}

		if (file_put_contents($tempFile, $content) === false) {
			$this->error('Failed to save panel archive to temp file');
			$this->removeTempFile($tempFile);

			return '';
		}

		if (!$this->verifySignature($tempFile)) {
			$this->removeTempFile($tempFile);

			return '';
		}

		$this->success("Downloaded {$this->panelFileName} to {$tempFile}");

		return $tempFile;
	}

	private function removeTempFile(string $path): void
	{
		if (file_exists($path) && !unlink($path)) {
			$this->warn("Failed to remove temporary file: {$path}");
		}
	}

	private function download(string $url): string|false
	{
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => 'User-Agent: Cosray-CMS-Installer',
				'follow_location' => true,
			],
		]);

		return file_get_contents($url, false, $context);
	}

	private function verifySignature(string $archivePath): bool
	{
		$this->info("Verifying {$this->panelFileName} signature from {$this->panelSignatureUrl}...");

		$content = $this->download($this->panelSignatureUrl);

		if ($content === false) {
			$this->error("Failed to download signature from {$this->panelSignatureUrl}");

			return false;
		}

		$signature = base64_decode(trim($content), true);

		if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
			$this->error('Signature file does not contain a valid Ed25519 signature');

			return false;
		}

		$key = base64_decode(self::PANEL_PUBKEY, true);

		if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
			$this->error('Embedded panel signing public key is invalid');

			return false;
		}

		$archive = file_get_contents($archivePath);

		if ($archive === false) {
			$this->error("Failed to read downloaded panel archive: {$archivePath}");

			return false;
		}

		if (!sodium_crypto_sign_verify_detached($signature, $archive, $key)) {
			$this->error("Signature verification failed for {$this->panelFileName}");
			$this->error('The archive was not signed by the Cosray release key');

			return false;
		}

		$this->success("Verified {$this->panelFileName} signature");

		return true;
	}

	private function preparePanelDownload(string $version): void
	{
		$tag = $this->resolvePanelReleaseTag($version);
		$fileTag = str_replace('/', '-', $tag);
		$file = $tag === 'nightly' ? 'panel-nightly.tar.gz' : "panel-{$fileTag}.tar.gz";
		$url = self::RELEASE_BASE_URL . "/{$file}";

		$this->panelReleaseTag = $tag;
		$this->panelFileName = $file;
		$this->panelUrl = $url;
		$this->panelSignatureUrl = "{$url}.sig";
	}

	private function resolvePanelReleaseTag(string $version): string
	{
		if ($version === '' || $version === 'dev-main' || str_starts_with($version, 'dev-')) {
			return 'nightly';
		}

		if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $version) === 1) {
			return $version;
		}

		$this->warn("Unknown version format `{$version}`, falling back to nightly panel release");

		return 'nightly';
	}

	private function versionLabel(): string
	{
		return "cosray/cms@{$this->cmsVersion} (panel {$this->panelReleaseTag})";
	}
}
