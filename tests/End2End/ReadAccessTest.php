<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\RestrictedPage;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReadAccessTest extends End2EndTestCase
{
	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(RestrictedPage::class);

		return $bootstrap;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$type = $this->createTestType('restricted-page');
		$node = $this->createTestNode(['uid' => 'restricted-http', 'type' => $type]);
		$this->createTestPath($node, '/restricted', 'en');
	}

	public static function requests(): array
	{
		return [['GET', 'text/html'], ['GET', 'application/json'], ['POST', 'text/html']];
	}

	#[DataProvider('requests')]
	public function testPublicRequestsCannotReadRestrictedContent(string $method, string $accept): void
	{
		$response = $this->makeRequest($method, '/restricted', ['headers' => ['Accept' => $accept]]);
		$this->assertResponseStatus(403, $response);
	}

	public function testAuthorizedPanelPreviewCanReadRestrictedContent(): void
	{
		$this->authenticateAs('editor');
		$this->assertResponseOk($this->makeRequest('GET', '/preview/restricted-http'));
	}
}
