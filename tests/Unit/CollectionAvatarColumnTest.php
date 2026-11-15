<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Tests\TestCase;
use Cosray\User;
use Cosray\User\Avatar;
use DateTimeZone;

final class CollectionAvatarColumnTest extends TestCase
{
	public function testAnAvatarColumnShowsTheUserAsAvatar(): void
	{
		$ann = new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com');
		$table = $this->table(
			[Column::new('Title', 'title'), Column::new('Editor', 'meta.editor')->avatar(true)],
			[['value' => 'Entry'], ['value' => $ann, 'avatar' => true]],
		);
		$cell = $table->rows[0]['cells'][1];

		$this->assertSame('avatar', $table->headers[1]['kind']);
		$this->assertSame($ann->array(), $cell['avatar']);
		$this->assertSame('Ann Lee', $cell['value']);
	}

	public function testWithoutTheFlagTheUserShowsAsName(): void
	{
		$table = $this->table(
			[Column::new('Title', 'title'), Column::new('Editor', 'meta.editor')],
			[['value' => 'Entry'], ['value' => new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com')]],
		);

		$this->assertNull($table->rows[0]['cells'][1]['avatar']);
		$this->assertSame('Ann Lee', $table->rows[0]['cells'][1]['value']);
	}

	public function testAColumnMayHoldAUser(): void
	{
		$user = new User([
			'usr' => 7,
			'uid' => 'max-uid',
			'username' => 'max',
			'email' => 'max@example.com',
			'password' => 'hash',
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
		]);
		$table = $this->table(
			[Column::new('Title', 'title'), Column::new('Owner', static fn(): User => $user)->avatar(true)],
			[['value' => 'Entry'], ['value' => $user, 'avatar' => true]],
		);

		$this->assertSame($user->avatar()->array(), $table->rows[0]['cells'][1]['avatar']);
	}

	public function testTheLeadColumnKeepsItsText(): void
	{
		$table = $this->table(
			[Column::new('Editor', 'meta.editor')->avatar(true)],
			[['value' => new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com'), 'avatar' => true]],
		);
		$cell = $table->rows[0]['cells'][0];

		$this->assertTrue($cell['lead']);
		$this->assertNull($cell['avatar']);
		$this->assertSame('Ann Lee', $cell['value']);
	}

	/**
	 * @param list<Column> $columns
	 * @param list<array<string, mixed>> $values
	 */
	private function table(array $columns, array $values): CollectionTable
	{
		return CollectionTable::from(
			$columns,
			[['uid' => 'entry', 'columns' => $values]],
			new CollectionUrls('/panel', 'content', new CollectionQuery()),
			new CollectionListMeta(),
			'en',
			new DateTimeZone('UTC'),
		);
	}
}
