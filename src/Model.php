<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitORM\ORM\Model as InitORMModel;

/**
 * Branded alias of {@see InitORMModel}. Application models should extend this
 * class so they keep their {@code use \InitPHP\Database\Model} import even if
 * the underlying ORM package is renamed or restructured upstream.
 *
 * No behaviour lives here on purpose — see the same note on {@see Database}.
 */
abstract class Model extends InitORMModel
{
}
