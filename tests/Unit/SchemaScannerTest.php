<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Tool\Message;
use Cosray\I18n\SchemaScanner;
use Cosray\Tests\Fixtures\Node\SchemaScanNode;
use Cosray\Tests\Fixtures\Node\TestDocument;
use Cosray\Tests\TestCase;

final class SchemaScannerTest extends TestCase
{
	/**
	 * @param list<Message> $messages
	 * @return list<string>
	 */
	private function ids(array $messages): array
	{
		$ids = array_map(static fn(Message $message): string => $message->id, $messages);
		sort($ids);

		return $ids;
	}

	public function testExtractsClassBadgeFieldAndOptionStrings(): void
	{
		$scanner = new SchemaScanner([SchemaScanNode::class]);

		$this->assertSame(
			[
				'Arrival day',
				'Beta',
				'Double room',
				'Reservation form',
				'Room type',
				'Single room',
				'The day the guest arrives',
			],
			$this->ids($scanner->scan()),
		);
	}

	public function testPlainStringOptionsAreNotExtracted(): void
	{
		$ids = $this->ids(new SchemaScanner([SchemaScanNode::class])->scan());

		$this->assertNotContains('red', $ids);
		$this->assertNotContains('green', $ids);
		$this->assertNotContains('blue', $ids);
	}

	public function testNavigationLabelsAreEmitted(): void
	{
		$ids = $this->ids(new SchemaScanner([], ['Inhalte', 'Formulare'])->scan());

		$this->assertSame(['Formulare', 'Inhalte'], $ids);
	}

	public function testEmptyStringsAreSkipped(): void
	{
		$ids = $this->ids(new SchemaScanner([], ['', 'Real'])->scan());

		$this->assertSame(['Real'], $ids);
	}

	public function testDuplicatesFoldToOneBareMessage(): void
	{
		// The class label 'Test Document' also arrives as a navigation label twice.
		$scanner = new SchemaScanner([TestDocument::class], ['Test Document', 'Test Document']);
		$messages = $scanner->scan();

		$repeated = array_filter($messages, static fn(Message $m): bool => $m->id === 'Test Document');
		$this->assertCount(1, $repeated);

		foreach ($messages as $message) {
			$this->assertNull($message->domain);
			$this->assertNull($message->plural);
			$this->assertNotSame([], $message->locations);
		}
	}

	public function testWarningsAreEmpty(): void
	{
		$this->assertSame([], new SchemaScanner([SchemaScanNode::class])->warnings());
	}
}
