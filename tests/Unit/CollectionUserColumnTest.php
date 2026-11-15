<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Tests\TestCase;
use Cosray\User;
use Cosray\User\Avatar;
use Cosray\UserColumn;
use DateTimeZone;
use stdClass;

final class CollectionUserColumnTest extends TestCase
{
	public function testTitleCanBeGiven(): void
	{
		$this->assertSame('Author', Column::editor('Author')->title);
		$this->assertSame('Author', Column::creator('Author')->title);
	}

	public function testAUserShowsAsAvatarAndName(): void
	{
		$ann = new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com');
		$table = $this->table([Column::text('Title', 'title'), $this->column($ann)]);
		$cell = $table->rows[0]['cells'][1];

		$this->assertSame($ann->array(), $cell['avatar']);
		$this->assertFalse($cell['avatarOnly']);
		$this->assertSame('Ann Lee', $cell['value']);
	}

	public function testAvatarShowsTheAvatarAlone(): void
	{
		$ann = new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com');
		$table = $this->table([Column::text('Title', 'title'), $this->column($ann)->avatar()]);
		$cell = $table->rows[0]['cells'][1];

		$this->assertSame($ann->array(), $cell['avatar']);
		$this->assertTrue($cell['avatarOnly']);
	}

	public function testNameShowsTheNameAlone(): void
	{
		$table = $this->table([
			Column::text('Title', 'title'),
			$this
				->column(new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com'))
				->name()
				->bold(),
		]);
		$cell = $table->rows[0]['cells'][1];

		$this->assertNull($cell['avatar']);
		$this->assertSame('Ann Lee', $cell['value']);
		$this->assertSame('is-bold', $cell['class']);
	}

	public function testAClosureMayReturnAUser(): void
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
		$table = $this->table([Column::text('Title', 'title'), Column::user('Owner', static fn(): User => $user)]);

		$this->assertSame($user->avatar()->array(), $table->rows[0]['cells'][1]['avatar']);
	}

	public function testTheLeadColumnShowsTheNameAlone(): void
	{
		$table = $this->table([$this->column(new Avatar(5, 'Ann Lee', 'ann', 'ann@example.com'))]);
		$cell = $table->rows[0]['cells'][0];

		$this->assertTrue($cell['lead']);
		$this->assertNull($cell['avatar']);
		$this->assertSame('Ann Lee', $cell['value']);
	}

	public function testUserColumnsOfferNoSort(): void
	{
		$table = $this->table([Column::text('Title', 'title')->sort('title'), Column::editor()]);

		$this->assertNull($table->headers[1]['url']);
	}

	private function column(Avatar $avatar): UserColumn
	{
		return Column::user('Editor', static fn(): Avatar => $avatar);
	}

	/** @param list<Column> $columns */
	private function table(array $columns): CollectionTable
	{
		$node = new Wrapper(new stdClass(), [], new Types());

		return CollectionTable::from(
			$columns,
			[[
				'uid' => 'entry',
				'columns' => array_map(
					static fn(Column $column): array => (
						$column instanceof UserColumn
							? $column->get($node)
							: ['value' => 'Entry']
					),
					$columns,
				),
			]],
			new CollectionUrls('/panel', 'content', new CollectionQuery()),
			new CollectionListMeta(),
			'en',
			new DateTimeZone('UTC'),
		);
	}
}
