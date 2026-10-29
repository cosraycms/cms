<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Uid as UidGenerator;

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
			self::panel($root),
			self::api(),
			self::error(),
			self::icons(),
			self::database(),
			self::session($env),
			self::media(),
			self::richtext(),
			self::upload(),
			self::uid(),
			self::password($env),
			self::queue($env),
			self::mail($env),
		);
	}

	/** @return array<string, mixed> */
	private static function app(Env $env): array
	{
		return [
			'app.name' => env('APP_NAME', 'cosray'),
			'app.title' => env('APP_TITLE', null),
			'app.debug' => $env->bool('APP_DEBUG', false),
			'app.env' => env('APP_ENV', ''),
			'app.secret' => env('APP_SECRET', null),
			'app.timezone' => env('APP_TIMEZONE', 'UTC'),
			'app.url_prefix' => '',
			// The absolute URL for links in mails; never derived from a request.
			'app.url' => env('APP_URL', null),
			// Reverse proxies whose X-Forwarded-For names the client, comma-separated in TRUSTED_PROXIES.
			'app.trusted_proxies' => $env->string('TRUSTED_PROXIES') ?? [],
			'plugins' => [],
			'webhooks' => [],
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
			'path.assets' => '/assets',
			'path.cache' => '/cache',
			'path.views' => '/views',
		];
	}

	/** @return array<string, mixed> */
	private static function panel(string $root): array
	{
		return [
			'panel.path' => '/panel',
			// Keep the client outside the public directory so a physical
			// directory cannot shadow the panel route in the web server.
			'panel.theme' => [],
			'panel.logo' => '/images/logo.png',
			'panel.dashboard' => true,
			// Default panel UI language; NULL negotiates from the browser.
			'panel.locale' => null,
			// Origins that may frame the panel's embedded editor.
			'panel.embed' => [],
		];
	}

	/** @return array<string, mixed> */
	private static function api(): array
	{
		return [
			'api.path' => null,
			// Origins whose browser scripts may call the API; '*' allows any.
			'api.origins' => [],
			// Seconds shared caches may keep anonymous reads; 0 asks them to revalidate.
			'api.max_age' => 0,
			// A headless frontend's preview URL with {uid}, {token} and {locale}.
			'api.preview' => null,
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
			// Keep the connection between requests of a worker, rolled back to
			// a clean state; false disconnects after every request.
			'db.reuse' => true,
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
			'richtext.tools' => [],
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
				'image/avif' => ['avif'],
				'image/gif' => ['gif'],
				'image/jpeg' => ['jpeg', 'jpg', 'jpe', 'jfif'],
				'image/png' => ['png'],
				'image/svg+xml' => ['svg'],
				'image/webp' => ['webp'],
			],
			'upload.mimetypes.video' => [
				'video/mp4' => ['mp4', 'm4v'],
				'video/ogg' => ['ogv', 'ogg'],
				'video/webm' => ['webm'],
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
	private static function password(Env $env): array
	{
		return [
			// Off by default: it sends the start of each new password's SHA-1 to a third party.
			'password.breach_check' => $env->bool('PASSWORD_BREACH_CHECK', false),
		];
	}

	/** @return array<string, mixed> */
	private static function queue(Env $env): array
	{
		return [
			// Run due jobs after each response; turn off where cron or a daemon runs them.
			'queue.after_response' => $env->bool('QUEUE_AFTER_RESPONSE', true),
		];
	}

	/** @return array<string, mixed> */
	private static function mail(Env $env): array
	{
		$dsn = $env->string('MAILER_DSN');

		return [
			// Symfony Mailer DSNs by name, the first is the default; MAILER_DSN names `default`.
			'mail.transports' => $dsn !== null && $dsn !== '' ? ['default' => $dsn] : [],
			'mail.from' => $env->string('MAILER_FROM'),
			// Addresses that get every mail instead of its recipients, comma-separated in MAILER_REDIRECT.
			'mail.redirect' => $env->string('MAILER_REDIRECT') ?? [],
			// Template directories; empty means `mail` in the views directory.
			'mail.views' => [],
		];
	}
}
