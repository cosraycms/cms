<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\Scanner;
use Cosray\I18n\AppMessages;
use Cosray\Tests\TestCase;
use Override;

final class AppMessagesTest extends TestCase
{
	/** @var list<string> */
	private array $files = [];

	#[Override]
	protected function tearDown(): void
	{
		foreach ($this->files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		parent::tearDown();
	}

	public function testLeavesOutBareMessagesCosrayTranslates(): void
	{
		$messages = new AppMessages([$this->scanner(
			new Message(null, 'collection:all', null, ['navigation']),
			new Message(null, 'Rooms', null, ['navigation']),
		)], ['de' => $this->catalog(['Rooms' => 'Zimmer'])]);

		$this->assertSame(['Rooms'], $this->ids($messages->scan()));
	}

	public function testKeepsMessagesTheAppShadows(): void
	{
		$messages = new AppMessages([$this->scanner(
			new Message(null, 'collection:all', null, ['navigation']),
		)], [
			'de' => $this->catalog(['collection:all' => 'Alles']),
			'en' => $this->catalog(['collection:all' => null]),
		]);

		$this->assertSame(['collection:all'], $this->ids($messages->scan()));
	}

	public function testAnUntranslatedAppEntryDoesNotShadow(): void
	{
		$messages = new AppMessages([$this->scanner(
			new Message(null, 'collection:all', null, ['navigation']),
		)], ['de' => $this->catalog(['collection:all' => null])]);

		$this->assertSame([], $messages->scan());
	}

	public function testKeepsMessagesWithAnExplicitDomain(): void
	{
		$messages = new AppMessages([$this->scanner(
			new Message('mysite', 'collection:all', null, ['views/page.php:3']),
		)], []);

		$this->assertSame(['collection:all'], $this->ids($messages->scan()));
	}

	public function testMatchesTheMessageContext(): void
	{
		$messages = new AppMessages([$this->scanner(
			new Message(null, 'collection:all', null, ['views/page.php:4'], 'menu'),
		)], []);

		$this->assertSame(['collection:all'], $this->ids($messages->scan()));
	}

	public function testMergesTheMessagesAndWarningsOfAllScanners(): void
	{
		$messages = new AppMessages([
			$this->scanner(new Message(null, 'Rooms', null, ['src/A.php:1'])),
			$this->scanner(new Message(null, 'Prices', null, ['src/B.php:1'])),
		], []);

		$this->assertSame(['Prices', 'Rooms'], $this->ids($messages->scan()));
		$this->assertSame(['src/A.php', 'src/B.php'], $messages->warnings());
	}

	/** Answers the messages and warns once with the first message's file. */
	private function scanner(Message ...$messages): Scanner
	{
		return new class(array_values($messages)) implements Scanner {
			/** @param list<Message> $messages */
			public function __construct(
				private readonly array $messages,
			) {}

			#[Override]
			public function scan(): array
			{
				return $this->messages;
			}

			#[Override]
			public function warnings(): array
			{
				return [explode(':', $this->messages[0]->locations[0])[0]];
			}
		};
	}

	/** @param array<string, string|null> $messages */
	private function catalog(array $messages): string
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'cosray-catalog-');
		file_put_contents($file, '<?php return ' . var_export(['messages' => $messages], true) . ';');
		$this->files[] = $file;

		return $file;
	}

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
}
