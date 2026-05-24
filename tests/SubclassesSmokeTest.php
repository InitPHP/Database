<?php

/**
 * @package InitPHP\Database
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitPHP\Database;

use InitORM\Database\Database as InitORMDatabase;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\ORM\Entity as InitORMEntity;
use InitORM\ORM\Interfaces\EntityInterface;
use InitORM\ORM\Interfaces\ModelInterface;
use InitORM\ORM\Model as InitORMModel;
use InitPHP\Database\Database;
use InitPHP\Database\Entity;
use InitPHP\Database\Model;
use PHPUnit\Framework\TestCase;

/**
 * Guards the inheritance chain of the three branded aliases. Renaming an
 * upstream class without bumping this package would silently break {@code
 * instanceof} checks in user code; this test catches that at CI time.
 */
final class SubclassesSmokeTest extends TestCase
{
    public function testDatabaseExtendsTheUpstreamImplementation(): void
    {
        self::assertTrue(is_subclass_of(Database::class, InitORMDatabase::class));
        self::assertContains(DatabaseInterface::class, class_implements(Database::class));
    }

    public function testModelExtendsTheUpstreamImplementation(): void
    {
        self::assertTrue(is_subclass_of(Model::class, InitORMModel::class));
        self::assertContains(ModelInterface::class, class_implements(Model::class));
    }

    public function testEntityExtendsTheUpstreamImplementation(): void
    {
        self::assertTrue(is_subclass_of(Entity::class, InitORMEntity::class));
        self::assertContains(EntityInterface::class, class_implements(Entity::class));
    }
}
