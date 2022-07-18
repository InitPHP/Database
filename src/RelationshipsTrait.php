<?php
/**
 * RelationshipsTrait.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.13
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \InitPHP\Database\Exception\RelationshipsException;

use InitPHP\Database\Interfaces\ModelInterface;
use function strtolower;

trait RelationshipsTrait
{

    /**
     * @inheritDoc
     */
    public final function relations(string $model, ?string $fromColumn = null, ?string $targetColumn = null, string $joinType = 'INNER'): self
    {
        $targetProperties = $this->_getModelTableNameAndPrimaryKeyColumResolve($model);
        if($fromColumn === null || $fromColumn == '{primaryKey}'){
            if($this->primaryKey === null){
                throw new RelationshipsException('There must be a primary key column to use relationships.');
            }
            $fromColumn = $this->primaryKey;
        }
        if($targetColumn === null || $targetColumn == '{primaryKey}'){
            if($targetProperties['primaryColumn'] === null){
                throw new RelationshipsException('There must be a primary key column to use relationships.');
            }
            $targetColumn = $targetProperties['primaryColumn'];
        }
        $onStmt = $this->table . '.' . $fromColumn
            . '='
            . $targetProperties['table'] . '.' . $targetColumn;
        $this->join($targetProperties['table'], $onStmt, $joinType);
        return $this;
    }

    /**
     * @param string $modelClass
     * @return array
     * @throws RelationshipsException
     */
    private function _getModelTableNameAndPrimaryKeyColumResolve(string $modelClass): array
    {
        try {
            $model = new \ReflectionClass($modelClass);
            if(\PHP_VERSION_ID >= 80000){
                $tableProperty = $model->getProperty('table');
                $primaryProperty = $model->getProperty('primaryKey');
                if(($table = $tableProperty->getDefaultValue()) === null){
                    $table = strtolower($model->getShortName());
                }
                $primaryColumn = $primaryProperty->getDefaultValue();
            }else{
                /** @var ModelInterface $modelInstance */
                $modelInstance = $model->newInstance();
                $table = $modelInstance->getTableName();
                $primaryColumn = $modelInstance->getPrimaryKeyColumnName();
            }
        }catch (\Exception $e) {
            throw new RelationshipsException($e->getMessage());
        }
        return [
            'table'             => $table,
            'primaryColumn'     => $primaryColumn,
        ];
    }

}
