<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Role
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name, description
             FROM roles
             WHERE deleted_at IS NULL
             ORDER BY name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
