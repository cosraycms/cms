<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000020_RichtextHtmlToJson;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Field\RichText;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\Normalizer;

/**
 * One-shot richtext content migration: every legacy HTML richtext
 * value (fields and blocks alike) becomes a structured document in
 * the cosray richtext format (docs/richtext-format.md).
 *
 * The HTML parses through the panel's own editor schema via the
 * bundled node converter (panel/static/tools/richtext-convert.mjs,
 * jsdom + prosemirror-model from panel/node_modules — run `pnpm
 * install && pnpm run build` in panel/ first). Internal links resolve
 * to `link.node` via url_paths, uid-form asset URLs to `link.asset` /
 * inline `image` nodes via the catalog layout. A report lands in
 * `richtext-migration-report.json` at the project root.
 *
 * Undeclared paragraph classes reset to `default` and undeclared text
 * styles lose their mark (the text stays), so migrated content always
 * passes the writer-strict save — declare the classes and styles an
 * app wants to KEEP in `richtext.classes` / `richtext.styles` BEFORE
 * running this migration; the report counts what was dropped.
 */
final class Migration implements Contract\Migration
{
	/** @var list<array{table: string, key: string, where: string}> */
	private const array CONTENT_TABLES = [
		[
			'table' => '/*:cms.prefix:*/nodes',
			'key' => 'node',
			'where' => 'node = :node',
		],
		[
			'table' => '/*:cms.prefix:*/drafts',
			'key' => 'node',
			'where' => 'node = :node',
		],
	];

	/** @var array<string, string> Unit id to source HTML. */
	private array $units = [];

	/** @var array<string, null|array> Unit id to converted document. */
	private array $docs = [];

	/** @var array<string, string> Active URL path to node uid. */
	private array $pathMap = [];

	private array $report = [
		'rows' => 0,
		'converted' => 0,
		'emptied' => 0,
		'nodeLinks' => 0,
		'assetLinks' => 0,
		'undeclaredClasses' => [],
		'undeclaredStyles' => [],
		'unresolvedLinks' => [],
		'droppedImages' => [],
		'errors' => [],
	];

	private string $assetsBase = '/assets';

	/** @var null|array<string, string> Legacy asset path to uid. */
	private ?array $legacyMap = null;

	public function __construct(
		private readonly Config $config,
	) {}

	public function run(Environment $env): void
	{
		$this->assetsBase =
			$this->config->path->prefix . '/' . trim((string) $this->config->get('path.assets'), '/');
		$this->pathMap = $this->loadPathMap($env);

		$rows = [];

		foreach (self::CONTENT_TABLES as $table) {
			$rows[$table['table']] = $env->db->execute($this->sql($env, "
				SELECT {$table['key']}, content::text AS content
				FROM {$table['table']}
			"))->all();

			foreach ($rows[$table['table']] as $row) {
				$content = json_decode((string) $row['content'], true);

				if (is_array($content)) {
					$this->collect($content, "{$table['table']}:{$row[$table['key']]}");
				}
			}
		}

		if ($this->units !== []) {
			$this->docs = $this->convert();
		}

		$this->disableContentTriggers($env);

		try {
			foreach (self::CONTENT_TABLES as $table) {
				foreach ($rows[$table['table']] as $row) {
					$this->transformRow($env, $table, $row);
				}
			}
		} finally {
			$this->enableContentTriggers($env);
		}

		$this->writeReport();
	}

	/** @param array{table: string, key: string, where: string} $table */
	private function transformRow(Environment $env, array $table, array $row): void
	{
		$content = json_decode((string) $row['content'], true);

		if (!is_array($content)) {
			return;
		}

		$this->report['rows']++;
		$transformed = $this->transform($content, "{$table['table']}:{$row[$table['key']]}");
		$encoded = json_encode($transformed, JSON_THROW_ON_ERROR);

		if ($encoded === (string) $row['content']) {
			return;
		}

		$env->db->execute($this->sql($env, "
			UPDATE {$table['table']}
			SET content = :content::jsonb
			WHERE {$table['where']}
		"), [
			'node' => (int) $row[$table['key']],
			'content' => $encoded,
		])->run();
	}

	/** Pass 1: gather every legacy richtext HTML unit below $data. */
	private function collect(array $data, string $prefix): void
	{
		if ($this->isLegacyRichtext($data)) {
			foreach ($data['value'] as $locale => $html) {
				if (is_string($html) && trim($html) !== '') {
					$this->units["{$prefix}:{$locale}"] = $this->tagImages($html);
				}
			}

			return;
		}

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$this->collect($value, "{$prefix}.{$key}");
			}
		}
	}

	/** Pass 2: replace legacy values with converted documents. */
	private function transform(array $data, string $prefix): array
	{
		if ($this->isLegacyRichtext($data)) {
			$normalizer = new Normalizer();
			$value = [];

			foreach ($data['value'] as $locale => $html) {
				$doc = $this->docs["{$prefix}:{$locale}"] ?? null;

				if (is_array($doc)) {
					$doc = $this->resolveRefs($doc);
				}

				$doc = is_array($doc) ? $normalizer->normalize($doc) : null;
				$value[$locale] = $doc;
				$doc === null ? $this->report['emptied']++ : $this->report['converted']++;
			}

			return $this->envelope($data, $value);
		}

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = $this->transform($value, "{$prefix}.{$key}");
			}
		}

		return $data;
	}

	private function isLegacyRichtext(array $data): bool
	{
		$type = $data['type'] ?? null;

		return is_string($type)
			&& in_array($type, [RichText::class, 'richtext', 'html'], true)
			&& !isset($data['format'])
			&& is_array($data['value'] ?? null);
	}

	/** Rebuild the entry with format/version placed before value. */
	private function envelope(array $data, array $value): array
	{
		$result = [];

		foreach ($data as $key => $entry) {
			if ($key === 'format' || $key === 'version') {
				continue;
			}

			if ($key === 'value') {
				$result['format'] = Envelope::FORMAT;
				$result['version'] = Envelope::VERSION;
				$result['value'] = $value;

				continue;
			}

			$result[$key] = $entry;
		}

		return $result;
	}

	/**
	 * Resolve reference carriers and report undeclared classes:
	 * internal hrefs become `link.node`, uid-form asset URLs become
	 * `link.asset`, no-op `cms-text-base` style marks drop out.
	 */
	private function resolveRefs(array $node): array
	{
		if (is_array($node['marks'] ?? null)) {
			$marks = [];

			foreach ($node['marks'] as $mark) {
				$mark = is_array($mark) ? $this->resolveMark($mark) : $mark;

				if ($mark !== null) {
					$marks[] = $mark;
				}
			}

			if ($marks === []) {
				unset($node['marks']);
			} else {
				$node['marks'] = $marks;
			}
		}

		if (($node['type'] ?? null) === 'paragraph') {
			$class = $node['attrs']['class'] ?? null;

			if (
				is_string($class)
				&& $class !== 'default'
				&& !isset($this->config->richtext->classes[$class])
			) {
				// Undeclared classes (Word-paste junk like MsoNormal, or
				// ladders the app chose not to keep) reset to default so
				// migrated content always passes the writer-strict save.
				// Declare wanted classes in richtext.classes BEFORE running.
				$this->count('undeclaredClasses', $class);
				$node['attrs']['class'] = 'default';
			}
		}

		if (is_array($node['content'] ?? null)) {
			foreach ($node['content'] as $i => $child) {
				if (is_array($child)) {
					$node['content'][$i] = $this->resolveRefs($child);
				}
			}
		}

		return $node;
	}

	private function resolveMark(array $mark): ?array
	{
		$type = $mark['type'] ?? null;

		if ($type === 'style') {
			$class = $mark['attrs']['class'] ?? null;

			if ($class === 'cms-text-base') {
				return null;
			}

			if (is_string($class) && !isset($this->config->richtext->styles[$class])) {
				// Undeclared text styles (Word-paste spans, dropped ladders)
				// lose the mark, never the text. Declare wanted styles in
				// richtext.styles BEFORE running.
				$this->count('undeclaredStyles', $class);

				return null;
			}

			return $mark;
		}

		if ($type !== 'link') {
			return $mark;
		}

		$href = $mark['attrs']['href'] ?? null;

		if (!is_string($href) || $href === '') {
			return $mark;
		}

		$uid = $this->assetUidFromUrl($href);

		if ($uid !== null) {
			unset($mark['attrs']['href']);
			$mark['attrs']['asset'] = $uid;
			$this->report['assetLinks']++;

			return $mark;
		}

		$path = rawurldecode((string) (parse_url($href, PHP_URL_PATH) ?? ''));
		// Legacy content links pages with trailing slashes; url_paths
		// stores them without.
		$node = $this->pathMap[$path] ?? $this->pathMap[rtrim($path, '/')] ?? null;

		if ($node !== null) {
			unset($mark['attrs']['href']);
			$mark['attrs']['node'] = $node;
			$this->report['nodeLinks']++;

			return $mark;
		}

		if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
			$this->report['unresolvedLinks'][] = $href;
		}

		return $mark;
	}

	/**
	 * Tag legacy inline images with their asset uid so the editor
	 * schema's `img[data-uid]` rule keeps them; untaggable images are
	 * dropped by the parse and reported.
	 */
	private function tagImages(string $html): string
	{
		return preg_replace_callback('#<img\b[^>]*>#i', function (array $m): string {
			$tag = $m[0];

			if (str_contains($tag, 'data-uid=')) {
				return $tag;
			}

			if (!preg_match('#\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $src)) {
				return $tag;
			}

			$url = $src[2] !== '' ? $src[2] : ($src[3] ?? '');
			$uid = $this->assetUidFromUrl($url);

			if ($uid === null) {
				$this->report['droppedImages'][] = $url;

				return $tag;
			}

			return substr_replace($tag, '<img data-uid="' . htmlspecialchars($uid) . '"', 0, 4);
		}, $html) ?? $html;
	}

	/**
	 * Extract the asset uid from a uid-form URL below the assets base;
	 * pre-catalog owner-scoped URLs resolve through the legacy map the
	 * catalog migration dumped (asset-legacy-map.json).
	 */
	private function assetUidFromUrl(string $url): ?string
	{
		$path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? ''));

		if (!str_starts_with($path, $this->assetsBase . '/')) {
			return null;
		}

		$key = substr($path, strlen($this->assetsBase) + 1);
		$parts = explode('/', $key);

		if (count($parts) === 3 && $parts[1] !== '' && str_starts_with($parts[1], $parts[0])) {
			return $parts[1];
		}

		return $this->legacyMap()[$key] ?? null;
	}

	/** @return array<string, string> */
	private function legacyMap(): array
	{
		if ($this->legacyMap === null) {
			$file = $this->config->get('path.root') . '/asset-legacy-map.json';
			$map = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
			$this->legacyMap = is_array($map) ? $map : [];
		}

		return $this->legacyMap;
	}

	/** @return array<string, null|array> */
	private function convert(): array
	{
		$panel = dirname(__DIR__, 3) . '/panel';
		$script = $panel . '/static/tools/richtext-convert.mjs';

		if (!is_file($script)) {
			throw new RuntimeException(
				"Richtext converter missing at {$script} — run `pnpm install && pnpm run build` in panel/ first.",
			);
		}

		$in = tempnam(sys_get_temp_dir(), 'richtext-in-');
		$out = tempnam(sys_get_temp_dir(), 'richtext-out-');

		try {
			$handle = fopen($in, 'w');

			foreach ($this->units as $id => $html) {
				fwrite($handle, json_encode(['id' => $id, 'html' => $html], JSON_THROW_ON_ERROR) . "\n");
			}

			fclose($handle);

			$process = proc_open(
				['node', $script],
				[0 => ['file', $in, 'r'], 1 => ['file', $out, 'w'], 2 => ['pipe', 'w']],
				$pipes,
				$panel,
			);

			if (!is_resource($process)) {
				throw new RuntimeException('Could not start the richtext converter (is node installed?).');
			}

			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);
			$status = proc_close($process);

			if ($status !== 0) {
				throw new RuntimeException("Richtext converter failed (exit {$status}): {$stderr}");
			}

			$docs = [];

			foreach (file($out, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				$result = json_decode($line, true);

				if (!is_array($result) || !is_string($result['id'] ?? null)) {
					continue;
				}

				if (isset($result['error'])) {
					$this->report['errors'][] = "{$result['id']}: {$result['error']}";

					continue;
				}

				$docs[$result['id']] = is_array($result['doc'] ?? null) ? $result['doc'] : null;
			}

			return $docs;
		} finally {
			@unlink($in);
			@unlink($out);
		}
	}

	private function count(string $bucket, string $key): void
	{
		$this->report[$bucket][$key] = ($this->report[$bucket][$key] ?? 0) + 1;
	}

	private function writeReport(): void
	{
		$this->report['unresolvedLinks'] = array_values(array_unique($this->report['unresolvedLinks']));
		$this->report['droppedImages'] = array_values(array_unique($this->report['droppedImages']));
		$path = $this->config->get('path.root') . '/richtext-migration-report.json';
		file_put_contents(
			$path,
			json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
		);
		echo "Richtext migration report written to {$path}\n";
	}

	/** @return array<string, string> */
	private function loadPathMap(Environment $env): array
	{
		$map = [];
		$rows = $env->db->execute($this->sql($env, '
			SELECT up.path, n.uid
			FROM /*:cms.prefix:*/url_paths up
			JOIN /*:cms.prefix:*/nodes n ON n.node = up.node
			WHERE up.inactive IS NULL
		'))->all();

		foreach ($rows as $row) {
			$map[(string) $row['path']] = (string) $row['uid'];
		}

		return $map;
	}

	private function disableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			SQL))->run();
	}

	private function enableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			SQL))->run();
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->config->placeholders?->compileSql($sql, __FILE__) ?? $sql;
	}
}

return Migration::class;
