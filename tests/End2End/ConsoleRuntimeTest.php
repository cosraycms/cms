<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Console\Runtime;
use Cosray\Exception\RuntimeException;
use Cosray\Queue;
use Cosray\Queue\Runner;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Queue\LocaleJob;
use Cosray\Tests\Fixtures\RecordingLogger;

/**
 * The request-free scope of console commands and queued jobs: its
 * translator is active while it works, and the one before is back after.
 *
 * @internal
 *
 * @coversNothing
 */
final class ConsoleRuntimeTest extends End2EndTestCase
{
	public function testARuntimeTranslatesOnlyWhileItRuns(): void
	{
		$before = new Translator('xx', []);
		Verba::activate($before);
		$runtime = new Runtime($this->app->container());

		$this->assertSame($before, Verba::translator());
		$this->assertSame(
			$runtime->translator(),
			$runtime->run(static fn(): ?Translator => Verba::translator()),
		);
		$this->assertSame($before, Verba::translator());
	}

	public function testARuntimeRestoresTheTranslatorWhenItsWorkFails(): void
	{
		Verba::deactivate();
		$runtime = new Runtime($this->app->container());

		try {
			$runtime->run(static fn(): never => throw new RuntimeException('Failed'));
			$this->fail('Expected the work to fail');
		} catch (RuntimeException) {
			$this->assertNull(Verba::translator());
		}
	}

	public function testAScopedRunnerHandlesJobsInTheDefaultLocaleAndLeavesNoTranslator(): void
	{
		Verba::deactivate();
		LocaleJob::$locale = null;
		$this->db()->execute('DELETE FROM cms.jobs')->run();
		new Queue($this->db())->push(LocaleJob::class);

		Runner::scoped($this->db(), new RecordingLogger(), $this->app->container())->run();

		$this->assertSame(new Runtime($this->app->container())->translator()->locale, LocaleJob::$locale);
		$this->assertNull(Verba::translator());
	}
}
