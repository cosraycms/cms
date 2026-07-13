<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Uid as UidGenerator;
use Cosray\Util\Password;

use function Cosray\env;

final class Defaults
{
	/** @return array<string, mixed> */
	public static function values(string $root, Env $env): array
	{
		return array_merge(
			self::app($env),
			self::auth($env),
			self::paths($root),
			self::panel(),
			self::error(),
			self::icons(),
			self::database(),
			self::session($env),
			self::media(),
			self::richtext(),
			self::upload(),
			self::uid(),
			self::password(),
		);
	}

	/** @return array<string, mixed> */
	private static function app(Env $env): array
	{
		return [
			'app.name' => env('APP_NAME', 'cosray'),
			'app.debug' => $env->bool('APP_DEBUG', false),
			'app.env' => env('APP_ENV', ''),
			'app.secret' => env('APP_SECRET', null),
			'app.timezone' => env('APP_TIMEZONE', 'UTC'),
			'plugins' => [],
		];
	}

	/** @return array<string, mixed> */
	private static function auth(Env $env): array
	{
		return [
			'auth.remember_lifetime' => $env->int('AUTH_REMEMBER_LIFETIME', 60 * 60 * 24 * 30),
		];
	}

	/** @return array<string, mixed> */
	private static function paths(string $root): array
	{
		return [
			'path.root' => $root,
			'path.public' => $root . '/public',
			'path.prefix' => '',
			'path.assets' => '/assets',
			'path.cache' => '/cache',
			'path.views' => '/views',
			'path.panel' => '/cp',
		];
	}

	/** @return array<string, mixed> */
	private static function panel(): array
	{
		return [
			'panel.theme' => [],
			'panel.logo' => '/images/logo.png',
			// Default panel UI language; NULL negotiates from the browser.
			'panel.locale' => null,
		];
	}

	/** @return array<string, mixed> */
	private static function error(): array
	{
		return [
			'error.enabled' => true,
			'error.renderer' => null,
			'error.trusted' => [],
			'error.views' => null,
			'error.whoops' => true,
		];
	}

	/** @return array<string, mixed> */
	private static function icons(): array
	{
		return [
			'icons.local.paths' => [],
			'icons.iconify.base_url' => 'https://api.iconify.design',
			'icons.iconify.timeout' => 5,
			'icons.iconify.user_agent' => 'cosray/cms',
		];
	}

	/** @return array<string, mixed> */
	private static function database(): array
	{
		return [
			'db.dsn' => env('DATABASE_URL', null),
			'db.sql' => [],
			'db.migrations' => [],
			'db.placeholders' => [
				'all' => [
					'cms.prefix' => 'cms_',
				],
				'pgsql' => [
					'cms.prefix' => 'cms.',
				],
			],
			'db.print' => false,
			'db.options' => [],
		];
	}

	/** @return array<string, mixed> */
	private static function session(Env $env): array
	{
		return [
			'session.enabled' => $env->bool('SITE_SESSION_ENABLED', false),
			'session.options' => [
				'cookie_httponly' => true,
				'cookie_samesite' => 'Lax',
				'cookie_secure' => $env->bool('SESSION_COOKIE_SECURE', true),
				'cookie_lifetime' => $env->int('SESSION_COOKIE_LIFETIME', 0),
				'gc_maxlifetime' => $env->int('SESSION_IDLE_TIMEOUT', 60 * 60 * 8),
				'cache_expire' => 3600,
			],
			'session.handler' => null,
		];
	}

	/** @return array<string, mixed> */
	private static function media(): array
	{
		return [
			'media.fileserver' => null,
			'media.sizes' => [],
		];
	}

	/** @return array<string, mixed> */
	private static function richtext(): array
	{
		return [
			'richtext.classes' => [],
			'richtext.styles' => [],
		];
	}

	/** @return array<string, mixed> */
	private static function upload(): array
	{
		return [
			'upload.mimetypes.file' => [
				'application/pdf' => ['pdf'],
			],
			'upload.mimetypes.image' => [
				'image/gif' => ['gif'],
				'image/jpeg' => ['jpeg', 'jpg', 'jfif'],
				'image/png' => ['png'],
				'image/webp' => ['webp'],
				'image/svg+xml' => ['svg'],
			],
			'upload.mimetypes.video' => [
				'video/mp4' => ['mp4'],
				'video/ogg' => ['ogg'],
			],
			'upload.maxsize' => 10 * 1024 * 1024,
		];
	}

	/** @return array<string, mixed> */
	private static function uid(): array
	{
		return [
			'uid.alphabet' => UidGenerator::ALPHABET_LOWERCASE_WORD_SAFE,
			'uid.length' => 13,
		];
	}

	/** @return array<string, mixed> */
	private static function password(): array
	{
		return [
			'password.entropy' => Password::DEFAULT_PASSWORD_ENTROPY,
			'password.algorithm' => null,
		];
	}
}
