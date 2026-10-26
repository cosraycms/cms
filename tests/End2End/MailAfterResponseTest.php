<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Console\Runtime;
use Cosray\Mailer;
use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailAfterResponseTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->db()->execute('DELETE FROM cms.jobs')->run();
		$this->app = $this->createApp([
			'mail.transports' => ['default' => 'null://null'],
			'mail.from' => 'site@example.com',
		]);
	}

	public function testAQueuedMailIsSentOnceAResponseIsOut(): void
	{
		// Built by the container, as a node or a command gets it.
		$mailer = new Runtime($this->app->container())->get(Mailer::class);
		$mailer->send('receipt', to: 'ann@example.com', data: ['name' => 'Ann']);

		$this->makeRequest('GET', '/');

		$this->assertSame(0, $this->db()->execute('SELECT count(*) AS n FROM cms.jobs')->one()['n']);
		$this->assertContains(
			['info', 'Mail sent to 1 recipients through the default transport'],
			$this->logger->records,
		);
	}
}
