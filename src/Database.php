<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitORM\Database\Database as InitORMDatabase;

/**
 * Branded alias of {@see InitORMDatabase} that lives under the {@code InitPHP}
 * namespace. The implementation is upstream's — this subclass exists so that
 * application code can refer to {@code \InitPHP\Database\Database} (matching
 * the package name on Packagist) without dragging the {@code InitORM}
 * namespace into user-facing code.
 *
 * Do not add behaviour here. Extending {@see InitORMDatabase} directly with
 * package-specific logic would silently diverge the InitPHP and InitORM
 * stacks; if the wrapper ever needs real behaviour, it should be added to
 * upstream {@see InitORMDatabase} (or composed, not inherited) instead.
 */
class Database extends InitORMDatabase
{
}
