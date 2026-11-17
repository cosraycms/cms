<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Quma\Environment;
use Celema\Verba\Verba;
use Cosray\Actor;
use Cosray\MigrationFactory;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\ContentMigration;
use Cosray\Tests\Fixtures\Node\TestDocument;

/**
 * PHP migrations change content with the services console commands get,
 * on the migration's own connection, so their writes commit or roll back
 * with the migrations.
 *
 * @internal
 *
 * @coversNothing
 */
final class MigrationContentTest extends End2EndTestCase
{
	public function testAMigrationWritesContentInsideItsTransaction(): void
	{
		$env = new Environment($this->testConnection);
		$migration = $this->migration($env);
		$env->db->begin();

		try {
			$migration->writer->create(
				$migration->writer->prepare(TestDocument::class, ['title' => 'Before'])->uid('migrated'),
			);
			$migration->api->patch(
				$migration->cms->node->byUid('migrated', published: null),
				['content' => ['title' => ['value' => ['zxx' => 'After']]]],
				Actor::system(),
			);

			$this->assertSame('After', $migration->cms->node->byUid('migrated', published: null)?->title());
			$this->assertCount(1, $env->db->execute("SELECT node FROM cms.nodes WHERE uid = 'migrated'")->all());
		} finally {
			$env->db->rollback();
		}

		$this->assertSame([], $env->db->execute("SELECT node FROM cms.nodes WHERE uid = 'migrated'")->all());
	}

	public function testAMigrationTranslatesInTheDefaultLocale(): void
	{
		Verba::deactivate();
		$migration = $this->migration(new Environment($this->testConnection));

		try {
			$this->assertSame($migration->context->translator(), Verba::translator());
		} finally {
			Verba::deactivate();
		}
	}

	private function migration(Environment $env): ContentMigration
	{
		$migration = new MigrationFactory($this->app->container())->create(ContentMigration::class, $env);
		$this->assertInstanceOf(ContentMigration::class, $migration);

		return $migration;
	}
}
