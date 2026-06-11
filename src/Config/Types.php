<?php

declare(strict_types=1);

namespace Cosray\Config;

/**
 * @psalm-type SessionOptions = array{
 *     cookie_httponly: bool,
 *     cookie_secure: bool,
 *     cookie_lifetime: int,
 *     gc_maxlifetime: int,
 *     cache_expire: int,
 *     ...<string, mixed>
 * }
 *
 * @psalm-type MimeMap = array<string, non-empty-list<string>>
 * @psalm-type DbPlaceholders = array<non-empty-string, array<non-empty-string, string>>
 *
 * @psalm-type BuiltinConfig = array{
 *     'app.name': string,
 *     'app.debug': bool,
 *     'app.env': string,
 *     'app.secret': ?string,
 *
 *     'path.root': string,
 *     'path.public': string,
 *     'path.prefix': string,
 *     'path.assets': string,
 *     'path.cache': string,
 *     'path.views': string,
 *     'path.panel': string,
 *     'path.api': ?string,
 *
 *     'panel.theme': null|string|list<string>,
 *     'panel.logo': ?string,
 *
 *     'error.enabled': bool,
 *     'error.renderer': null|class-string<\Celemas\Core\Error\Renderer>|\Celemas\Core\Error\Renderer,
 *     'error.trusted': list<class-string>,
 *     'error.views': null|string|list<string>,
 *     'error.whoops': bool,
 *
 *     'icons.local.paths': string|list<string>,
 *     'icons.iconify.base_url': string,
 *     'icons.iconify.timeout': int,
 *     'icons.iconify.user_agent': string,
 *
 *     'db.dsn': ?string,
 *     'db.sql': string|list<string>,
 *     'db.migrations': string|list<string>,
 *     'db.placeholders': DbPlaceholders,
 *     'db.print': bool,
 *     'db.options': array<string, mixed>,
 *
 *     'session.enabled': bool,
 *     'session.options': SessionOptions,
 *     'session.handler': ?\SessionHandlerInterface,
 *
 *     'media.fileserver': null|'apache'|'nginx',
 *
 *     'upload.mimetypes.file': MimeMap,
 *     'upload.mimetypes.image': MimeMap,
 *     'upload.mimetypes.video': MimeMap,
 *     'upload.maxsize': int,
 *
 *     'password.entropy': float|int,
 *     'password.algorithm': int|string|null
 * }
 *
 * @psalm-type BuiltinConfigInput = array{
 *     'app.name'?: string,
 *     'app.debug'?: bool,
 *     'app.env'?: string,
 *     'app.secret'?: null|string,
 *     'path.root'?: string,
 *     'path.public'?: string,
 *     'path.prefix'?: string,
 *     'path.assets'?: string,
 *     'path.cache'?: string,
 *     'path.views'?: string,
 *     'path.panel'?: string,
 *     'path.api'?: null|string,
 *     'panel.theme'?: null|string|list<string>,
 *     'panel.logo'?: null|string,
 *     'error.enabled'?: bool,
 *     'error.renderer'?: mixed,
 *     'error.trusted'?: list<class-string>,
 *     'error.views'?: null|string|list<string>,
 *     'error.whoops'?: bool,
 *     'icons.local.paths'?: string|list<string>,
 *     'icons.iconify.base_url'?: string,
 *     'icons.iconify.timeout'?: int,
 *     'icons.iconify.user_agent'?: string,
 *     'db.dsn'?: null|string,
 *     'db.sql'?: string|list<string>,
 *     'db.migrations'?: string|list<string>,
 *     'db.placeholders'?: DbPlaceholders,
 *     'db.print'?: bool,
 *     'db.options'?: array<string, mixed>,
 *     'session.enabled'?: bool,
 *     'session.options'?: array<string, mixed>,
 *     'session.handler'?: ?\SessionHandlerInterface,
 *     'media.fileserver'?: null|'apache'|'nginx',
 *     'upload.mimetypes.file'?: MimeMap,
 *     'upload.mimetypes.image'?: MimeMap,
 *     'upload.mimetypes.video'?: MimeMap,
 *     'upload.maxsize'?: int,
 *     'password.entropy'?: float|int,
 *     'password.algorithm'?: int|string|null
 * }
 */
final class Types {}
