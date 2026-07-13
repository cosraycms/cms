<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celemas\Cli\Command;
use PDO;
use PDOException;

class RecreateDb extends Command
{
	protected string $group = 'Database';
	protected string $name = 'recreate-db';
	protected string $description = 'Drop and recreate the test database';

	public function __construct(
		private string $database,
		private string $username,
		#[\SensitiveParameter]
		private string $password,
		private string $host = 'localhost',
	) {}

	public function run(): int|string
	{
		echo "Recreating database '{$this->database}'...\n\n";

		try {
			// Connect to PostgreSQL without specifying a database (connect to 'postgres' db)
			$dsn = "pgsql:host={$this->host};dbname=postgres";
			$pdo = new PDO($dsn, $this->username, $this->password, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			]);

			// Terminate existing connections to the target database
			echo "Terminating existing connections...\n";
			$sql = 'SELECT pg_terminate_backend(pg_stat_activity.pid)
					FROM pg_stat_activity
					WHERE pg_stat_activity.datname = :dbname
					AND pid <> pg_backend_pid()';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(['dbname' => $this->database]);

			// Drop database if exists
			echo "Dropping database if exists...\n";
			$pdo->exec("DROP DATABASE IF EXISTS {$this->database}");

			// Create database
			echo "Creating database...\n";
			$pdo->exec("CREATE DATABASE {$this->database} OWNER {$this->username}");

			echo "\n✓ Database '{$this->database}' successfully recreated\n";
			echo "✓ Owner: {$this->username}\n\n";
			echo "Next steps:\n";
			echo "  php run db:migrations --namespace install --apply    # Initialize the schema\n";
			echo "  php run db:migrations --apply                        # Apply system updates\n\n";

			return 0;
		} catch (PDOException $e) {
			echo "\n✗ Error: " . $e->getMessage() . "\n\n";

			if (str_contains($e->getMessage(), 'authentication failed')) {
				echo "Make sure the user '{$this->username}' exists and has the correct password.\n";
				echo "You may need to create the user first:\n";
				echo "  sudo -u postgres createuser --createdb {$this->username}\n";
				echo
					"  sudo -u postgres psql -c \"ALTER USER {$this->username} WITH PASSWORD '<password>';\"\n\n"
				;
			} elseif (str_contains($e->getMessage(), 'permission denied')) {
				echo "The user '{$this->username}' needs CREATEDB privileges:\n";
				echo "  sudo -u postgres psql -c \"ALTER USER {$this->username} CREATEDB;\"\n\n";
			}

			return 1;
		}
	}
}
