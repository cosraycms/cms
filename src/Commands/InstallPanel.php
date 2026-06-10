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
	protected string $description = 'Installs or upgrades the admin panel frontend app';
	protected string $prefix;
	protected string $panelPath;
	protected string $publicPath;
	protected string $indexPath;
	private string $cmsVersion = 'unknown';
	private string $panelReleaseTag = 'nightly';
	private string $panelFileName = 'panel-nightly.tar.gz';
	private string $panelUrl = 'https://cosray.dev/releases/panel-nightly.tar.gz';
	private string $panelChecksumUrl = 'https://cosray.dev/releases/panel-nightly.tar.gz.sha256';

	protected const string DEFAULT_PATH = '/cms';
	private const string RELEASE_BASE_URL = 'https://cosray.dev/releases';

	public function __construct(
		private Config $config,
	) {
		$this->prefix = $this->config->path->prefix;
		$this->panelPath = $this->config->panel->path;
		$this->publicPath = $this->config->path->public . $this->panelPath;
		$this->indexPath = $this->publicPath . '/index.html';
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

		if ($this->panelPath !== self::DEFAULT_PATH) {
			$this->echoln(
				'Changing panel path from `' . self::DEFAULT_PATH . "` to `{$this->prefix}{$this->panelPath}`:",
			);

			if ($this->updatePanelPath() !== 0) {
				$this->error('Panel installed, but path update failed');

				return 1;
			}
		}

		$this->success("Panel installed from {$this->panelFileName}");

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

		if (!$this->verifyChecksum($tempFile)) {
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

	private function verifyChecksum(string $archivePath): bool
	{
		$this->info("Verifying {$this->panelFileName} checksum from {$this->panelChecksumUrl}...");

		$content = $this->download($this->panelChecksumUrl);

		if ($content === false) {
			$this->error("Failed to download checksum from {$this->panelChecksumUrl}");

			return false;
		}

		$expected = $this->expectedChecksum($content);

		if ($expected === '') {
			$this->error("Checksum file does not contain a valid SHA-256 entry for {$this->panelFileName}");

			return false;
		}

		$actual = hash_file('sha256', $archivePath);

		if ($actual === false) {
			$this->error("Failed to hash downloaded panel archive: {$archivePath}");

			return false;
		}

		if (!hash_equals($expected, $actual)) {
			$this->error("Checksum mismatch for {$this->panelFileName}");
			$this->error("Expected: {$expected}");
			$this->error("Actual:   {$actual}");

			return false;
		}

		$this->success("Verified {$this->panelFileName} checksum");

		return true;
	}

	private function expectedChecksum(string $content): string
	{
		foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
			$parts = preg_split('/\s+/', trim($line), 2);

			if ($parts === false || count($parts) !== 2) {
				continue;
			}

			$hash = strtolower($parts[0]);
			$file = ltrim(trim($parts[1]), '*');

			if ($file === $this->panelFileName && preg_match('/^[a-f0-9]{64}$/', $hash) === 1) {
				return $hash;
			}
		}

		return '';
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
		$this->panelChecksumUrl = "{$url}.sha256";
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

	private function updatePanelPath(): int
	{
		$files = $this->findFiles();

		foreach ($files as $file) {
			$result = $this->replace($file);

			if ($result !== 0) {
				return $result;
			}
		}

		return 0;
	}

	private function findFiles()
	{
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->publicPath));
		$files = [];

		foreach ($iterator as $file) {
			if (!($file->isFile() && in_array($file->getExtension(), ['js', 'css', 'html'], true))) {
				continue;
			}

			$content = file_get_contents($file->getPathname());

			if (str_contains($content, self::DEFAULT_PATH)) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function replace(string $file): int
	{
		if (!file_exists($file)) {
			$this->error('File does not exist: ' . $this->removeCwdFromPath($file));

			return 1;
		}

		$content = file_get_contents($file);
		$updatedContent = str_replace(self::DEFAULT_PATH, $this->prefix . $this->panelPath, $content);

		if ($content === $updatedContent) {
			$this->warn('No changes were made to the panel path: ' . $this->removeCwdFromPath($file));

			return 0;
		}

		file_put_contents($file, $updatedContent);
		$this->success('Panel path updated successfully: ' . $this->removeCwdFromPath($file));

		return 0;
	}

	private function versionLabel(): string
	{
		return "cosray/cms@{$this->cmsVersion} (panel {$this->panelReleaseTag})";
	}

	private function removeCwdFromPath($path)
	{
		$cwd = realpath(getcwd());
		$absolutePath = realpath($path);

		if ($absolutePath && str_starts_with($absolutePath, $cwd)) {
			return substr($absolutePath, strlen($cwd) + 1); // +1 to remove the slash
		}

		return $path;
	}
}
