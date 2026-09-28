<?php

declare(strict_types=1);

namespace Cosray\Field;

/**
 * The syntaxes a code field can offer: the ones the panel's editor ships
 * (panel/src/elements/code/languages.js). Both lists change together;
 * panel/tests/contract/code-syntaxes.test.ts compares them.
 */
final class CodeSyntaxes
{
	public const string DEFAULT = 'plaintext';

	/** @var list<string> */
	public const array KEYS = [
		'plaintext',
		'apacheconf',
		'bash',
		'clojure',
		'csharp',
		'css',
		'diff',
		'django',
		'docker',
		'fsharp',
		'go',
		'handlebars',
		'html',
		'ini',
		'java',
		'javascript',
		'json',
		'jsx',
		'latte',
		'liquid',
		'lisp',
		'lua',
		'markdown',
		'nginx',
		'nim',
		'ocaml',
		'odin',
		'php',
		'python',
		'ruby',
		'rust',
		'scss',
		'sql',
		'toml',
		'tsx',
		'twig',
		'typescript',
		'xml',
		'yaml',
		'zig',
	];

	/** @var array<string, string> */
	public const array ALIASES = [
		'js' => 'javascript',
		'ts' => 'typescript',
		'md' => 'markdown',
		'yml' => 'yaml',
		'sh' => 'bash',
		'hbs' => 'handlebars',
		'mustache' => 'handlebars',
		'jinja' => 'django',
		'jinja2' => 'django',
		'plain' => 'plaintext',
		'text' => 'plaintext',
	];

	/** The key a syntax name or alias stands for, or null for one the panel lacks. */
	public static function resolve(string $syntax): ?string
	{
		$key = strtolower(trim($syntax));
		$key = self::ALIASES[$key] ?? $key;

		return in_array($key, self::KEYS, true) ? $key : null;
	}
}
