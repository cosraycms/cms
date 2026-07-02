<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Celemas\Container\Container;
use Celemas\Quma\Connection;
use Celemas\Quma\Database;
use Celemas\Quma\Delimiters;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Node\Types;
use Cosray\Uid;
use PDO;
use RuntimeException;

/**
 * Base class for integration tests that interact with the database.
 *
 * This class extends TestCase and enables transaction-based test isolation
 * by default, ensuring each test has a clean database state.
 *
 * @internal
 *
 * @coversNothing
 */
class IntegrationTestCase extends TestCase
{
	protected static bool $dbInitialized = false;
	protected static ?Connection $sharedConnection = null;
	protected ?Database $testDb = null;
	protected bool $useTransactions = true;
	private ?NodeContentNormalizer $contentNormalizer = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$dbInitialized) {
			self::initializeTestDatabase();
			self::$dbInitialized = true;
		}
	}

	protected static function initializeTestDatabase(): void
	{
		// Create shared connection for migration check
		$config = new Config(self::root());

		self::$sharedConnection = new Connection(
			self::testDbDsn(),
			self::root() . '/db/sql',
		)
			->placeholders(Delimiters::comments(), $config->db->placeholders)
			->migrations(self::root() . '/db/migrations')
			->fetch(PDO::FETCH_ASSOC);

		$db = new Database(self::$sharedConnection);

		// Check if migrations table exists
		$tableExists =
			$db->execute(
				"SELECT EXISTS (
				SELECT FROM information_schema.tables
				WHERE table_schema = 'public'
				AND table_name = 'migrations'
			) as exists",
			)->one()['exists'] ?? false;

		if (!$tableExists) {
			echo "\n⚠ Test database not initialized. Run: ./run recreate-db && ./run migrate --apply\n\n";

			throw new RuntimeException(
				'Test database not initialized. Run: ./run recreate-db && ./run migrate --apply',
			);
		}

		// Check if cms schema exists (indicates migrations have been run)
		$schemaExists =
			$db->execute(
				"SELECT EXISTS (
				SELECT FROM information_schema.schemata
				WHERE schema_name = 'cms'
			) as exists",
			)->one()['exists'] ?? false;

		if (!$schemaExists) {
			echo "\n⚠ Migrations not applied. Run: ./run migrate --apply\n\n";

			throw new RuntimeException(
				'Migrations not applied to test database. Run: ./run migrate --apply',
			);
		}
	}

	protected function setUp(): void
	{
		parent::setUp();

		// Begin transaction if this test uses them
		if ($this->useTransactions) {
			$this->testDb = new Database($this->conn());
			$this->testDb->begin();
		}
	}

	protected function tearDown(): void
	{
		// Rollback transaction if this test used them
		if ($this->useTransactions && $this->testDb !== null) {
			$this->testDb->rollback();
			$this->testDb = null;
		}

		parent::tearDown();
	}

	public function conn(): Connection
	{
		$config = new Config(self::root());

		return new Connection(
			self::testDbDsn(),
			self::root() . '/db/sql',
		)
			->placeholders(Delimiters::comments(), $config->db->placeholders)
			->migrations(self::root() . '/db/migrations')
			->fetch(PDO::FETCH_ASSOC);
	}

	public function db(): Database
	{
		// If using transactions, return the same database instance
		if ($this->useTransactions && $this->testDb !== null) {
			return $this->testDb;
		}

		return new Database($this->conn());
	}

	public function container(): Container
	{
		$container = new Container();

		// Register test Node classes for fixture types
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-article', \Cosray\Tests\Fixtures\Node\TestArticle::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-home', \Cosray\Tests\Fixtures\Node\TestHome::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-block', \Cosray\Tests\Fixtures\Node\TestBlock::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-widget', \Cosray\Tests\Fixtures\Node\TestWidget::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-document', \Cosray\Tests\Fixtures\Node\TestDocument::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('test-media-document', \Cosray\Tests\Fixtures\Node\TestMediaDocument::class);

		// Register dynamically created test types (reuse TestPage for all page types)
		$container->tag(Bootstrap::NODE_TAG)
			->add('ordered-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('limit-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('hidden-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('routing-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('nested-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('unpublished-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('create-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('crud-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('update-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('delete-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);
		$container->tag(Bootstrap::NODE_TAG)
			->add('renderable-test-page', \Cosray\Tests\Fixtures\Node\TestPage::class);

		return $container;
	}

	/**
	 * Load SQL fixture files into the test database.
	 *
	 * @param string ...$fixtures Fixture names (without .sql extension)
	 */
	protected function loadFixtures(string ...$fixtures): void
	{
		$db = $this->db();

		foreach ($fixtures as $fixture) {
			$path = self::root() . "/tests/Fixtures/data/{$fixture}.sql";

			if (!file_exists($path)) {
				throw new RuntimeException("Fixture file not found: {$path}");
			}

			$sql = file_get_contents($path);
			$db->execute($sql)->run();
		}

		$this->normalizeStoredNodeContent();
	}

	/**
	 * Create a test content type.
	 *
	 * @return int The type ID
	 */
	protected function createTestType(string $handle): int
	{
		$sql = 'INSERT INTO cms.types (handle)
				VALUES (:handle)
				RETURNING type';

		return $this->db()->execute($sql, [
			'handle' => $handle,
		])->one()['type'];
	}

	private function contentNormalizer(): NodeContentNormalizer
	{
		return $this->contentNormalizer ??= new NodeContentNormalizer(
			new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
		);
	}

	private function normalizeStoredNodeContent(): void
	{
		$nodes = $this->db()->execute('SELECT node, content FROM cms.nodes')->all();

		foreach ($nodes as $node) {
			$content = json_decode((string) ($node['content'] ?? '{}'), true);

			if (!is_array($content)) {
				continue;
			}

			$this->db()->execute(
				'UPDATE cms.nodes SET content = :content::jsonb WHERE node = :node',
				[
					'node' => $node['node'],
					'content' => json_encode($this->contentNormalizer()->normalize($content)),
				],
			)->run();
		}
	}

	/**
	 * Create a test node.
	 *
	 * @param array $data Node data (uid, type, content, etc.)
	 * @return int The node ID
	 */
	protected function createTestNode(array $data): int
	{
		$defaults = [
			'uid' => uniqid('test-'),
			'parent' => null,
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'creator' => 1, // System user
			'editor' => 1,
			'created' => 'now()',
			'changed' => 'now()',
			'content' => '{}',
		];

		$data = array_merge($defaults, $data);
		$handle = $data['handle'] ?? null;
		unset($data['handle']);

		// Convert content array to JSON if needed
		if (is_array($data['content'])) {
			$data['content'] = $this->contentNormalizer()->normalize($data['content']);
			$data['content'] = json_encode($data['content']);
		}

		$sql = 'INSERT INTO cms.nodes (uid, parent, published, hidden, locked, type, creator, editor, created, changed, content)
				VALUES (:uid, :parent, :published, :hidden, :locked, :type, :creator, :editor, :created, :changed, :content::jsonb)
				RETURNING node';

		$nodeId = $this->db()->execute($sql, $data)->one()['node'];

		if (is_string($handle) && trim($handle) !== '') {
			$this->db()->execute(
				'INSERT INTO cms.node_handles (node, handle, creator, editor) VALUES (:node, :handle, 1, 1)',
				['node' => $nodeId, 'handle' => trim($handle)],
			)->run();
		}

		return $nodeId;
	}

	/**
	 * Create a test user.
	 *
	 * @return int The user ID
	 */
	protected function createTestUser(array $data): int
	{
		$uid = $data['uid'] ?? uniqid('user-');
		$defaults = [
			'uid' => $uid,
			'username' => $data['username'] ?? $uid,
			'email' => $data['email'] ?? $uid . '@example.com',
			'password' => password_hash('password', PASSWORD_ARGON2ID),
			'rolename' => 'editor',
			'active' => true,
			'data' => ['name' => 'Test User'],
			'creator' => 1,
			'editor' => 1,
		];

		$data = array_merge($defaults, $data);

		if (isset($data['data']) && is_array($data['data'])) {
			$data['data'] = json_encode($data['data']);
		}

		$sql = 'INSERT INTO cms.users (uid, username, email, password, rolename, active, data, creator, editor)
				VALUES (:uid, :username, :email, :password, :rolename, :active, :data::jsonb, :creator, :editor)
				RETURNING usr';

		return $this->db()->execute($sql, $data)->one()['usr'];
	}

	/**
	 * Create a URL path for a node.
	 *
	 * @param int $nodeId The node ID
	 * @param string $path The URL path (e.g., '/about/team')
	 * @param string $locale The locale (default: 'en')
	 */
	protected function createTestPath(int $nodeId, string $path, string $locale = 'en'): void
	{
		$sql = 'INSERT INTO cms.url_paths (node, path, locale, creator, editor)
				VALUES (:node, :path, :locale, 1, 1)';

		$this->db()->execute($sql, [
			'node' => $nodeId,
			'path' => $path,
			'locale' => $locale,
		])->run();
	}

	protected function createContext(): Context
	{
		return new Context(
			$this->db(),
			$this->request(),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	protected function createCms(): Cms
	{
		return new Cms($this->createContext(), Services::withDefaults());
	}
}
