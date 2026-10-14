<?php
declare(strict_types=1);

namespace Bulbula\Database;

use PDO;

/** Contract for small, versioned, framework-free database changes. */
interface Migration
{
    public function description(): string;
    public function up(PDO $pdo, string $driver): void;
    public function down(PDO $pdo, string $driver): void;
}
