<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Sire\Shape;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Locales;
use Cosray\Tests\TestCase;
use Cosray\Validation\Login;
use Cosray\Validation\Shapes;
use Cosray\Validation\Validators;

final class ValidationMessagesTest extends TestCase
{
	public function testTranslatesEveryMessageSireProduces(): void
	{
		Verba::activate(new Translator('en', new Locales()->catalogs()));

		try {
			$keys = array_keys(Validators::messages());
		} finally {
			Verba::deactivate();
		}

		sort($keys);

		$this->assertSame(array_keys(new Shape()->defaultMessages()), $keys);
	}

	public function testKeepsSireMessagesWithoutTranslation(): void
	{
		Verba::activate(new Translator('tlh', new Locales()->catalogs()));

		try {
			$shape = Shapes::create();
			$shape->add('title', 'string')->rules('required')->label('Titre');

			$this->assertSame('Titre is required', $shape->validate(['title' => ''])->first('title'));
		} finally {
			Verba::deactivate();
		}
	}

	public function testMessagesFollowTheActiveLanguage(): void
	{
		Verba::activate(new Translator('de', new Locales()->catalogs()));

		try {
			$shape = Shapes::create();
			$shape->add('title', 'string')->rules('required')->label('Titel');
			$shape->add('teaser', 'string')->rules('minlen:20')->label('Anreißer');
			$shape->add('tags', 'list')->rules('maxitems:2')->label('Schlagwörter');
			$result = $shape->validate(['title' => '', 'teaser' => 'Kurz', 'tags' => ['a', 'b', 'c']]);

			$this->assertSame('Titel ist erforderlich', $result->first('title'));
			$this->assertSame('Anreißer muss mindestens 20 Zeichen lang sein', $result->first('teaser'));
			$this->assertSame('Enthält mehr als die maximal erlaubten 2 Elemente', $result->first('tags'));
			$this->assertSame(
				'Passwort ist erforderlich',
				new Login()->validate(['login' => 'editor'])->first('password'),
			);
		} finally {
			Verba::deactivate();
		}
	}
}
