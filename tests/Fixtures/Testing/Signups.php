<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Testing;

use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Quma\Database;
use Cosray\Mailer;
use Cosray\Queue;
use Cosray\Tests\Fixtures\Queue\RecordingJob;
use Cosray\Util\Form;
use RuntimeException;
use Throwable;

/** Handles signups the way a site's form handler would. */
final class Signups
{
	public function __construct(
		private readonly Request $request,
		private readonly Database $db,
		private readonly Queue $queue,
		private readonly Mailer $mailer,
		private readonly Factory $factory,
	) {}

	/** Records the signup and mails the sender in one transaction; `fail` makes it fail inside. */
	public function store(): Response
	{
		$form = Form::body($this->request);
		$email = (string) ($form['email'] ?? '');
		$this->db->begin();

		try {
			$this->queue->push(RecordingJob::class, ['email' => $email]);
			$this->mailer->send('greeting', to: $email);

			if (($form['fail'] ?? '') === '1') {
				throw new RuntimeException('The signup failed');
			}

			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollback();

			throw $e;
		}

		return Response::create($this->factory)->html('stored');
	}

	/** Records the signup and leaves the transaction open. */
	public function abandon(): Response
	{
		$this->db->begin();
		$this->queue->push(RecordingJob::class, ['email' => 'abandoned@example.com']);

		return Response::create($this->factory)->html('abandoned');
	}

	/** Runs a failing statement outside a transaction of its own. */
	public function break(): Response
	{
		$this->db->execute('SELECT 1 / 0')->one();

		return Response::create($this->factory)->html('unreachable');
	}
}
