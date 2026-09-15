<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\App;
use Cosray\Assets\Asset;
use Cosray\Assets\Ingest;
use Cosray\Assets\Protection;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Storage\Storage;
use Cosray\Tests\End2EndTestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class PrivateAssetsTest extends End2EndTestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/cosray-private-' . bin2hex(random_bytes(6));
		mkdir($this->dir . '/public/assets', 0o700, true);
		mkdir($this->dir . '/public/cache');
		parent::setUp();
	}

	protected function createApp(array $settings = []): App
	{
		return parent::createApp([
			'app.secret' => 'test-secret',
			'access.passwords' => ['staff' => password_hash('test-shared', PASSWORD_BCRYPT)],
			'path.public' => $this->dir . '/public',
			'media.private_dir' => $this->dir . '/private',
			...$settings,
		]);
	}

	protected function tearDown(): void
	{
		new Filesystem(new LocalFilesystemAdapter(dirname($this->dir)))->deleteDirectory(basename($this->dir));
		parent::tearDown();
	}

	protected function createBootstrap(Config $config): \Cosray\Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(\Cosray\Tests\Fixtures\Node\RestrictedPage::class);

		return $bootstrap;
	}

	private function mediaConfig(): Config
	{
		return $this->app->container()->get(Config::class);
	}

	private function png(): string
	{
		$image = imagecreatetruecolor(4, 3);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	public function testEditorUploadsAndPickerCountsInheritReadPermission(): void
	{
		$this->authenticateAs('editor');
		$response = $this->makeRequest('POST', '/media/image', [
			'query' => ['nodeType' => 'restricted-page', 'permission' => 'everyone'],
			'files' => ['file' => $this->factory()->uploadedFile(
				$this->factory()->streamFactory()->createStream($this->png()),
				strlen($this->png()),
				UPLOAD_ERR_OK,
				'scope-media-private.png',
				'image/png',
			)],
		]);
		$this->assertResponseOk($response);
		$upload = $this->getJsonResponse($response);
		$this->assertTrue($upload['ok']);
		$this->assertSame('staff', $upload['permission']);
		$ingest = new Ingest($this->mediaConfig(), $this->db());
		$ingest->ingest("%PDF-1.4\npublic document", 'scope-media-public.pdf', 'file');
		$response = $this->makeRequest('GET', '/media/library', ['query' => [
			'nodeType' => 'restricted-page',
			'q' => 'scope-media-',
		]]);
		$data = $this->getJsonResponse($response);
		$this->assertSame(1, $data['total']);
		$this->assertSame(1, $data['counts']['image']);
		$this->assertSame(0, $data['counts']['document']);
		$this->assertSame([$upload['uid']], array_column($data['assets'], 'uid'));
		$response = $this->makeRequest('GET', '/media/library', ['query' => [
			'nodeType' => 'test-page',
			'q' => 'scope-media-',
		]]);
		$data = $this->getJsonResponse($response);
		$this->assertSame(1, $data['counts']['document']);
		$this->assertSame(0, $data['counts']['image']);
		$this->assertSame('scope-media-public.pdf', $data['assets'][0]['filename']);
	}

	public function testOriginalAndCachedRenditionsRequireAccessOnEveryRequest(): void
	{
		$result = new Ingest($this->mediaConfig(), $this->db())->ingest(
			$this->png(),
			'private.png',
			'image',
			permission: 'staff',
		);
		$asset = Asset::fromRow($result->row, $this->mediaConfig());
		$this->assertFileDoesNotExist($this->dir . '/public/assets/' . $asset->key);
		$this->assertResponseStatus(303, $this->makeRequest('GET', $asset->path()));
		$this->assertResponseStatus(303, $this->makeRequest('GET', $asset->sizePath('thumb')));
		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			'/cache/' . dirname($asset->key) . '/private-thumb.png',
		));

		$this->authenticateAs('editor');
		$response = $this->makeRequest('GET', $asset->path());
		$this->assertResponseOk($response);
		$this->assertSame($this->png(), (string) $response->getBody());
		$this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertResponseOk($this->makeRequest('GET', $asset->sizePath('thumb')));
		$this->defaultAuthToken = null;
		$this->assertResponseStatus(303, $this->makeRequest('GET', $asset->sizePath('thumb')));
		$this->assertFileDoesNotExist($this->dir . '/public/cache/' . dirname($asset->key) . '/private-thumb.png');
	}

	public function testSharedPasswordGrantsDownloadsButNotPanelAccess(): void
	{
		$result = new Ingest($this->mediaConfig(), $this->db())->ingest(
			$this->png(),
			'private.png',
			'image',
			permission: 'staff',
		);
		$asset = Asset::fromRow($result->row, $this->mediaConfig());
		$this->assertResponseStatus(401, $this->makeRequest('GET', $asset->path(), ['headers' => [
			'Accept' => 'application/json',
		]]));
		$login = $this->makeRequest('GET', '/access/staff');
		preg_match('/name="_token" value="([^"]+)"/', (string) $login->getBody(), $matches);
		$this->assertResponseStatus(303, $this->makeRequest('POST', '/access/staff', ['body' => [
			'_token' => $matches[1],
			'password' => 'test-shared',
		]]));
		$this->assertResponseOk($this->makeRequest('GET', $asset->path()));
		$this->assertResponseStatus(303, $this->makeRequest('GET', '/cp'));
	}

	public function testProtectingAnAssetPreservesIdentityAndRemovesPublicBytes(): void
	{
		$ingest = new Ingest($this->mediaConfig(), $this->db());
		$result = $ingest->ingest($this->png(), 'public.png', 'image');
		$before = Asset::fromRow($result->row, $this->mediaConfig());
		$this->assertResponseOk($this->makeRequest('GET', $before->sizePath('thumb')));
		$protection = new Protection($this->mediaConfig(), $this->db());
		$after = $protection->protect($before->uid, 'staff');
		$this->assertSame($before->uid, $after->uid);
		$this->assertSame($after->path(), $protection->protect($before->uid, 'staff')->path());
		$this->assertResponseStatus(404, $this->makeRequest('GET', $before->path()));
		$this->assertResponseStatus(404, $this->makeRequest('GET', $before->sizePath('thumb')));
		$this->assertFileDoesNotExist($this->dir . '/public' . $before->path());
		$this->assertFileDoesNotExist($this->dir . '/public' . $before->sizePath('thumb'));
		$this->assertSame($after->uid, $ingest->ingest($this->png(), 'again.png', 'image', permission: 'staff')->uid());
		$this->expectException(IngestError::class);
		$ingest->ingest($this->png(), 'public-again.png', 'image');
	}

	public function testProtectionCanFinishAfterDatabaseRollback(): void
	{
		$result = new Ingest($this->mediaConfig(), $this->db())->ingest($this->png(), 'recover.png', 'image');
		$this->db()->execute('SAVEPOINT protection_test')->run();
		$protection = new Protection($this->mediaConfig(), $this->db());
		$protection->protect($result->uid(), 'staff');
		$this->db()->execute('ROLLBACK TO SAVEPOINT protection_test')->run();
		$asset = $protection->protect($result->uid(), 'staff');
		$this->assertSame($result->uid(), $asset->uid);
		$this->assertSame($this->png(), new Storage($this->mediaConfig(), 'private')->read($asset->key));
		$this->assertResponseStatus(303, $this->makeRequest('GET', $asset->path()));
	}

	public function testMissingOriginalCannotBeReportedAsSuccessfullyProtected(): void
	{
		$result = new Ingest($this->mediaConfig(), $this->db())->ingest($this->png(), 'missing.png', 'image');
		new Storage($this->mediaConfig())->delete($result->row['key']);
		$this->expectException(\Cosray\Exception\RuntimeException::class);
		new Protection($this->mediaConfig(), $this->db())->protect($result->uid(), 'staff');
	}

	public function testPrivateIngestCannotSilentlyReusePublicBytes(): void
	{
		$ingest = new Ingest($this->mediaConfig(), $this->db());
		$ingest->ingest($this->png(), 'public.png', 'image');
		$this->expectException(IngestError::class);
		$ingest->ingest($this->png(), 'private.png', 'image', permission: 'staff');
	}

	public function testPrivateStorageCannotBeConfiguredBelowThePublicDirectory(): void
	{
		$this->expectException(\Cosray\Exception\RuntimeException::class);
		new Storage($this->mediaConfig()->with('media.private_dir', $this->dir . '/public/private'), 'private');
	}

	public function testDeletingPrivateAssetsAlsoRemovesTheirPrivateRenditions(): void
	{
		$result = new Ingest($this->mediaConfig(), $this->db())->ingest(
			$this->png(),
			'private.png',
			'image',
			permission: 'staff',
		);
		$asset = Asset::fromRow($result->row, $this->mediaConfig());
		$this->authenticateAs('editor');
		$this->assertResponseOk($this->makeRequest('GET', $asset->sizePath('thumb')));
		$this->assertResponseOk($this->makeRequest('DELETE', '/media/' . $asset->uid));
		$this->assertDirectoryDoesNotExist($this->dir . '/private/' . dirname($asset->key));
		$this->assertDirectoryDoesNotExist($this->dir . '/private/.cache/' . dirname($asset->key));
	}
}
