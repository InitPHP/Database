<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitPHP\Database;

use InitORM\ORM\Entity as InitORMEntity;

/**
 * Branded alias of {@see InitORMEntity}. Application entities should extend
 * this class — see the note on {@see Database} for the rationale.
 *
 * Reminder: when a subclass defines a {@code set{Column}Attribute()} mutator,
 * the body MUST write the transformed value back through
 * {@code $this->setAttribute($name, $value)}. A direct
 * {@code $this->column = $value} assignment from inside a class method
 * bypasses {@see InitORMEntity::__set()} and creates a dynamic property
 * instead — deprecated in PHP 8.2+, fatal in a future PHP version.
 */
class Entity extends InitORMEntity
{
}
