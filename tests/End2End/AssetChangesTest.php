<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * Changing and deleting assets over the API, and who may do that in the panel.
 *
 * @internal
 *
 * @covers \Cosray\Api\Controller\Assets
 * @covers \Cosray\Controller\Panel\Media
 */
final class AssetChangesTest extends End2EndTestCase
{
	private const string UID = 'assetchanges1';

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api', 'panel.path' => '/panel']);
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, width, height, hash, meta, creator)
			VALUES (:uid, 'local', 'as/assetchanges1/pic.png', 'pic.png', 'image/png', 1, 10, 10, 'changes', :meta, 1)",
			[
				'uid' => self::UID,
				'meta' => json_encode([
					'alt' => ['en' => 'Stays'],
					'attribution' => 'Someone',
					'keep' => 'not ours',
				]),
			],
		)->run();
	}

	#[Override]
	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->policy()->role('viewer', 'Viewer');
		$plugin->policy()->allow('role:viewer', 'panel');

		return $plugin;
	}

	public function testAPatchChangesWhatItNamesAndLeavesTheRest(): void
	{
		$this->authenticateAs('editor');

		$asset = $this->json($this->patch(['attribution' => 'Someone else', 'focal' => ['x' => 0.2, 'y' => 0.4]]));

		$this->assertSame('Someone else', $asset['attribution']);
		$this->assertSame(['x' => 0.2, 'y' => 0.4], $asset['focal']);
		$this->assertSame('Stays', $asset['alt']);
		$this->assertSame('not ours', $this->meta()['keep']);
	}

	public function testNullClearsAValue(): void
	{
		$this->authenticateAs('editor');

		$asset = $this->json($this->patch(['attribution' => null, 'alt' => null]));

		$this->assertNull($asset['attribution']);
		$this->assertArrayNotHasKey('alt', $this->meta());
	}

	public function testAPatchKeepsACaptionItDoesNotName(): void
	{
		$this->authenticateAs('editor');
		$doc = [
			'type' => 'doc',
			'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Words']]]],
		];
		$this->json($this->patch(['caption' => ['en' => $doc]]));

		$asset = $this->json($this->patch(['attribution' => 'Later']));

		$this->assertSame(['html' => 'Words'], $asset['caption']);
	}

	public function testAPatchNeedsAnObject(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('PATCH', '/api/assets/' . self::UID, [
			'body' => '["no"]',
			'headers' => ['Content-Type' => 'application/json'],
		]);

		$this->assertSame(400, $response->getStatusCode());
	}

	public function testAnUnknownAssetIsNotFound(): void
	{
		$this->authenticateAs('editor');

		$this->assertSame(404, $this->makeRequest('PATCH', '/api/assets/nosuchasset00', [
			'body' => '{}',
			'headers' => ['Content-Type' => 'application/json'],
		])->getStatusCode());
		$this->assertSame(404, $this->makeRequest('DELETE', '/api/assets/nosuchasset00')->getStatusCode());
	}

	public function testDeletingAnAssetNothingUses(): void
	{
		$this->authenticateAs('editor');

		$this->assertSame(204, $this->makeRequest('DELETE', '/api/assets/' . self::UID)->getStatusCode());
		$this->assertSame(404, $this->makeRequest('GET', '/api/assets/' . self::UID)->getStatusCode());
	}

	public function testDeletingAnAssetInUseNamesWhatUsesIt(): void
	{
		$this->authenticateAs('editor');
		$this->db()->execute(
			"INSERT INTO cms.asset_references (owner_type, owner_uid, asset_uid) VALUES ('node', 'some-owner', :uid)",
			['uid' => self::UID],
		)->run();

		$response = $this->makeRequest('DELETE', '/api/assets/' . self::UID);

		$this->assertSame(409, $response->getStatusCode());
		$this->assertSame('some-owner', json_decode((string) $response->getBody(), true)['usedBy'][0]['ownerUid']);
		$this->assertSame(200, $this->makeRequest('GET', '/api/assets/' . self::UID)->getStatusCode());
	}

	public function testChangingAndDeletingTakeEditRights(): void
	{
		$this->authenticateAs('viewer');

		$this->assertSame(403, $this->patch(['attribution' => 'No'])->getStatusCode());
		$this->assertSame(403, $this->makeRequest('DELETE', '/api/assets/' . self::UID)->getStatusCode());
		$this->assertSame('Someone', $this->meta()['attribution']);
	}

	public function testThePanelAsksForTheSameRights(): void
	{
		$this->authenticateAs('viewer');

		$this->assertSame(
			403,
			$this->makeRequest('POST', '/panel/media/' . self::UID, ['form' => []])->getStatusCode(),
		);
		$this->assertSame(
			403,
			$this->makeRequest('POST', '/panel/media/' . self::UID . '/delete', ['form' => []])->getStatusCode(),
		);
		$this->assertSame(
			403,
			$this->makeRequest('POST', '/media/image', ['form' => []])->getStatusCode(),
		);
		$this->assertSame('Someone', $this->meta()['attribution']);
	}

	/** @param array<string, mixed> $body */
	private function patch(array $body): ResponseInterface
	{
		return $this->makeRequest('PATCH', '/api/assets/' . self::UID, [
			'body' => json_encode($body),
			'headers' => ['Content-Type' => 'application/json'],
		]);
	}

	/** @return array<string, mixed> */
	private function json(ResponseInterface $response): array
	{
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

		return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
	}

	/** @return array<string, mixed> */
	private function meta(): array
	{
		return json_decode(
			(string) $this->db()->execute('SELECT meta FROM cms.assets WHERE uid = :uid', [
				'uid' => self::UID,
			])->one()['meta'],
			true,
		);
	}
}
