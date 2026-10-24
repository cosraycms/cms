<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Quma\Exception\ConnectionFailed;
use Cosray\Api\Problems;
use Cosray\Api\Responses;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\TestCase;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use RuntimeException;
use Throwable;

final class ApiProblemsTest extends TestCase
{
	public function testAnUnreachableDatabaseAnswers503WithRetryAfter(): void
	{
		$logger = new RecordingLogger();

		$response = $this->failWith(ConnectionFailed::from(new PDOException('refused')), $logger);

		$this->assertSame(503, $response->getStatusCode());
		$this->assertSame('30', $response->getHeaderLine('Retry-After'));
		$this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
		$this->assertNotSame([], $logger->records);
	}

	public function testAnUnexpectedFailureAnswers500AndShowsItsMessageOnlyInDebugMode(): void
	{
		$logger = new RecordingLogger();

		$quiet = json_decode((string) $this->failWith(new RuntimeException('secret detail'), $logger)->getBody(), true);
		$debug = json_decode(
			(string) $this->failWith(new RuntimeException('secret detail'), $logger, true)->getBody(),
			true,
		);

		$this->assertSame(500, $quiet['status']);
		$this->assertArrayNotHasKey('detail', $quiet);
		$this->assertSame('secret detail', $debug['detail']);
		$this->assertCount(2, $logger->records);
	}

	private function failWith(Throwable $error, RecordingLogger $logger, bool $debug = false): Response
	{
		$problems = new Problems(new Responses($this->factory()), $logger, $debug);

		return $problems->process($this->psrRequest(), new class($error) implements Handler {
			public function __construct(
				private readonly Throwable $error,
			) {}

			public function handle(Request $request): Response
			{
				throw $this->error;
			}
		});
	}
}
