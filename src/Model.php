<?php
/**
 * Model
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.7
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database;

use InitPHP\Database\Exceptions\{DeletableException,
    ModelCallbacksException,
    ModelException,
    ModelRelationsException,
    ReadableException,
    UpdatableException,
    WritableException};
use InitPHP\Database\Helpers\Parameters;

abstract class Model extends Database
{

    /**
     * @var string[]
     */
    protected array $connection;

    /**
     * Dönüş için kullanılacak Entity sınıfı ya da nesnesi.
     *
     * @var Entity|string
     */
    protected $entity = Entity::class;

    /**
     * Modelin kullanacağı tablo adını tanımlar. Belirtilmez ya da boş bir değer belirtilirse model sınıfınızın adı kullanılır.
     *
     * @var string
     */
    protected string $table;

    /**
     * Tablonuzun PRIMARY KEY sütununun adını tanımlar. Eğer tablonuzda böyle bir sütun yoksa FALSE ya da NULL tanımlayın.
     *
     * @var null|string
     */
    protected ?string $primaryKey = 'id';

    /**
     * Yumuşak silmenin kullanılıp kullanılmayacağını tanımlar. Eğer FALSE ise veri kalıcı olarak silinir. TRUE ise $deletedField doğru tanımlanmış bir sütun adı olmalıdır.
     *
     * @var bool
     */
    protected bool $useSoftDeletes = true;

    /**
     * Verinin eklenme zamanını ISO 8601 formatında tutacak sütun adı.
     *
     * @var string|null
     */
    protected ?string $createdField = 'created_at';

    /**
     * Verinin son güncellenme zamanını ISO 8601 formatında tutacak sütun adı. Bu sütunun varsayılan değeri NULL olmalıdır.
     *
     * @var string|null
     */
    protected ?string $updatedField = 'updated_at';

    /**
     * Yumuşak silme aktifse verinin silinme zamanını ISO 8601 formatında tutacak sütun adı. Bu sütun varsayılan değeri NULL olmalıdır.
     *
     * @var string|null
     */
    protected ?string $deletedField = 'deleted_at';

    /**
     * Ekleme ve güncelleme gibi işlemlerde tanımlanmasına izin verilecek sütun isimlerini tutan dizi.
     *
     * @var string[]
     */
    protected ?array $allowedFields = null;

    /**
     * Ekleme, Silme ve Güncelleme işlemlerinde geri çağrılabilir yöntemlerin kullanılıp kullanılmayacağını tanımlar.
     *
     * @var bool
     */
    protected bool $allowedCallbacks = false;

    /**
     * Insert işlemi öncesinde çalıştırılacak yöntemleri tanımlar. Bu yöntemlere eklenmek istenen veri bir ilişkisel dizi olarak gönderilir ve geriye eklenecek veri dizisini döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $beforeInsert = [];

    /**
     * Insert işlemi yürütüldükten sonra çalıştırılacak yöntemleri tanımlar. Eklenen veriyi ilişkisel bir dizi olarak alır ve yine bu diziyi döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $afterInsert = [];

    /**
     * Update işlemi yürütülmeden önce çalıştırılacak yöntemleri tanımlar. Güncellenecek sütun ve değerleri ilişkisel bir dizi olarak gönderilir ve yine bu dizi döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $beforeUpdate = [];

    /**
     * Update işlemi yürütüldükten sonra çalıştırılacak yöntemleri tanımlar. Güncellenmiş sütun ve değerleri ilişkisel bir dizi olarak gönderilir ve yine bu dizi döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $afterUpdate = [];

    /**
     * Delete işlemi yürülmeden önce çalıştırılacak yöntemleri tanımlar.Etkilenecek satırların çoklu ilişkisel dizisi parametre olarak gönderilir ve yine bu dizi döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $beforeDelete = [];

    /**
     * Delete işlemi yürütüldükten sonra çalıştırılacak yöntemleri tanımlar. Etkilenmiş satırların çoklu ilişkisel dizisi parametre olarak gönderilir ve yine bu dizi döndürmesi gerekir.
     *
     * @var string[]|\Closure[]
     */
    protected array $afterDelete = [];

    /**
     * Bu modelin veriyi okuyabilir mi olduğunu tanımlar.
     *
     * @var bool
     */
    protected bool $readable = true;

    /**
     * Bu modelin bir veri yazabilir mi olduğunu tanımlar.
     *
     * @var bool
     */
    protected bool $writable = true;

    /**
     * Bu modelin bir veri silebilir mi olduğunu tanımlar.
     *
     * @var bool
     */
    protected bool $deletable = true;

    /**
     * Bu modelin bir veriyi güncelleyebilir mi olduğunu tanımlar.
     *
     * @var bool
     */
    protected bool $updatable = true;

    /**
     * Hangi sütunların hangi doğrulama yöntemine uyması gerektiğini tanımlayan dizi.
     *
     * @var array
     */
    protected array $validation = [];

    /**
     * Sütun ve doğrulama yöntemlerine özel oluşacak hata mesajlarını özelleştirmenize/yerelleştirmeniz için kullanılan dizi.
     *
     * @var array
     */
    protected array $validationMsg = [];

    /**
     * Sütun ve doğrulama yöntemlerine özel oluşturulacak hata mesajlarında {field} yerini alacak sütun adı yerine kullanılacak değerleri tanımlayan ilişkisel dizi.
     *
     * @var array
     */
    protected array $validationLabels = [];

    public function __construct()
    {
        $credentials = $this->connection ?? [];
        if(!isset($this->table)){
            $modelClass = \get_called_class();
            $modelReflection = new \ReflectionClass($modelClass);
            $this->table = \strtolower($modelReflection->getShortName());
            unset($modelClass, $modelReflection);
        }
        if($this->useSoftDeletes !== FALSE){
            if(empty($this->deletedField)){
                throw new ModelException('There must be a delete column to use soft delete.');
            }
        }
        $credentials['tableSchema'] = $this->table;
        $credentials['tableSchemaID'] = $this->primaryKey ?? null;
        $credentials['allowedFields'] = $this->allowedFields ?? null;
        $credentials['createdField'] = $this->createdField ?? null;
        $credentials['updatedField'] = $this->updatedField ?? null;
        $credentials['deletedField'] = $this->deletedField ?? null;
        if($credentials['allowedFields'] !== null){
            if($credentials['createdField'] !== null && !\in_array($credentials['createdField'], $credentials['allowedFields'])){
                $credentials['allowedFields'][] = $credentials['createdField'];
            }
            if($credentials['updatedField'] !== null && !\in_array($credentials['updatedField'], $credentials['allowedFields'])){
                $credentials['allowedFields'][] = $credentials['updatedField'];
            }
            if($credentials['deletedField'] !== null && !\in_array($credentials['deletedField'], $credentials['allowedFields'])){
                $credentials['allowedFields'][] = $credentials['deletedField'];
            }
        }
        $credentials['entity'] = $this->entity ?? Entity::class;
        $credentials['validation'] = [
            'methods'   => $this->validation ?? [],
            'messages'  => $this->validationMsg ?? [],
            'labels'    => $this->validationLabels ?? [],
        ];
        $credentials['readable'] = $this->readable ?? true;
        $credentials['updatable'] = $this->updatable ?? true;
        $credentials['deletable'] = $this->deletable ?? true;
        $credentials['writable'] = $this->writable ?? true;
        parent::__construct($credentials);
    }

    final public function withPrimaryKey(string $column): self
    {
        $clone = clone $this;
        $clone->primaryKey = $column;
        $clone->setSchemaID($column);
        return $clone;
    }

    /**
     * @param array $set
     * @return array|false
     */
    final public function create(array $set)
    {
        return $this->insert($set);
    }

    /**
     * @param array $set
     * @return array|false
     */
    final public function createBatch(array $set)
    {
        return $this->insertBatch($set);
    }

    /**
     * @param array $set
     * @return array|false
     */
    final public function insert(array $set)
    {
        if($this->isWritable() === FALSE){
            throw new WritableException('"' . \get_called_class() . '" is not a writable model.');
        }

        $data = $this->isCallbacksFunction('beforeInsert', 'afterInsert') ? $this->callbacksFunctionHandler($set, 'beforeInsert') : $set;

        if($data === FALSE){
            return false;
        }

        if(parent::create($data) === FALSE){
            return false;
        }

        return $this->isCallbacksFunction('afterInsert') ? $this->callbacksFunctionHandler($data, 'afterInsert') : true;
    }

    /**
     * @param array $set
     * @return array|false
     */
    final public function insertBatch(array $set)
    {
        if($this->isUpdatable() === FALSE){
            throw new UpdatableException('"' . \get_called_class() . '" is not a updatable model.');
        }

        if($this->isCallbacksFunction('beforeInsert', 'afterInsert')){
            foreach ($set as &$data) {
                $data = $this->callbacksFunctionHandler($data, 'beforeInsert');
                if($data === FALSE){
                    return false;
                }
            }
        }

        if(parent::createBatch($set) === FALSE){
            return false;
        }

        if($this->isCallbacksFunction('afterInsert')){
            foreach ($set as &$row) {
                $row = $this->callbacksFunctionHandler($row, 'afterInsert');
                if($row === FALSE){
                    return false;
                }
            }
        }

        return $set;
    }

    /**
     * @param Entity $entity
     * @return array|bool
     */
    final public function save(Entity $entity)
    {
        $data = $entity->toArray();
        $schemaID = $this->getSchemaID();
        if($schemaID !== null && isset($data[$schemaID])){
            return $this->update($data);
        }
        return $this->insert($data);
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return Result
     */
    final public function read(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->isReadable() === FALSE){
            throw new ReadableException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::read($selector, $conditions, $parameters);
    }

    /**
     * @param array $selector
     * @param array $conditions
     * @param array $parameters
     * @return Result
     */
    final public function readOne(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->isReadable() === FALSE){
            throw new ReadableException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::readOne($selector, $conditions, $parameters);
    }

    /**
     * @param array $set
     * @return array|bool
     */
    final public function update(array $set)
    {
        if($this->isUpdatable() === FALSE){
            throw new UpdatableException('"' . \get_called_class() . '" is not a updatable model.');
        }
        $data = $this->isCallbacksFunction('beforeUpdate', 'afterUpdate') ? $this->callbacksFunctionHandler($set, 'beforeUpdate') : $set;
        if($data === FALSE){
            return false;
        }
        if(parent::update($data) === FALSE){
            return false;
        }

        return $this->isCallbacksFunction('afterUpdate') ? $this->callbacksFunctionHandler($data, 'afterUpdate') : true;
    }

    /**
     * @param array $set
     * @param string $referenceColumn
     * @return array|false
     */
    final public function updateBatch(array $set, string $referenceColumn)
    {
        if($this->isUpdatable() === FALSE){
            throw new UpdatableException('"' . \get_called_class() . '" is not a updatable model.');
        }

        if($this->isCallbacksFunction('beforeUpdate', 'afterUpdate')){
            foreach ($set as &$data) {
                $data = $this->callbacksFunctionHandler($data, 'beforeUpdate');
                if($data === FALSE){
                    return false;
                }
            }
        }

        if(parent::updateBatch($set, $referenceColumn) === FALSE){
            return false;
        }

        if($this->isCallbacksFunction('afterUpdate')){
            foreach ($set as &$row) {
                $row = $this->callbacksFunctionHandler($row, 'afterUpdate');
                if($row === FALSE){
                    return false;
                }
            }
        }

        return $set;
    }

    /**
     * @param int|string|null $id
     * @return array|bool
     */
    final public function delete($id = null)
    {
        if($this->isDeletable() === FALSE){
            throw new DeletableException('"' . \get_called_class() . '" is not a deletable model.');
        }
        if($id !== null && !empty($this->getSchemaID())){
            $this->where($this->getSchemaID(), $id);
        }

        if ($this->isCallbacksFunction('beforeDelete', 'afterDelete')) {
            $clone = clone $this;
            $parameters = Parameters::get();
            $res = $clone->query($clone->_readQuery(), $parameters);
            $data = $res->asAssoc()->results();
            Parameters::merge($parameters);
            unset($clone, $parameters);

            if($data === null){
                return true;
            }
            $data = $this->callbacksFunctionHandler($data, 'beforeDelete');
            if($data === FALSE){
                return false;
            }
        }

        if(parent::delete() === FALSE){
            return false;
        }

        return $this->isCallbacksFunction('afterDelete') ? $this->callbacksFunctionHandler($data, 'afterDelete') : true;
    }

    final public function purgeDeleted(): bool
    {
        if($this->isDeletable() === FALSE){
            return false;
        }
        $this->onlyDeleted();
        return parent::delete();
    }

    /**
     * @param string|Model $model
     * @param string|null $fromColumn
     * @param string|null $targetColumn
     * @param string $joinType
     * @return $this
     * @throws \ReflectionException
     */
    final public function relation($model, ?string $fromColumn = null, ?string $targetColumn = null, string $joinType = 'INNER'): self
    {
        $from = [
            'tableSchema'   => $this->getSchema(),
            'tableSchemaID' => $this->getSchemaID(),
        ];
        $ref = new \ReflectionClass($model);
        if($ref->isSubclassOf(Model::class) === FALSE){
            throw new ModelRelationsException('The target class must be a subclass of \\InitPHP\\Database\\Model.');
        }
        if(\defined('PHP_VERSION_ID') && \PHP_VERSION_ID >= 80000){
            $target = $ref->getProperty('table');
            $targetPrimaryKey = $ref->getProperty('primaryKey');
            if(($targetSchema = $target->getDefaultValue()) === null){
                $targetSchema = \strtolower($ref->getShortName());
            }
            $targetSchemaID = $targetPrimaryKey->getDefaultValue();
            $target = [
                'tableSchema'   => $targetSchema,
                'tableSchemaID' => $targetSchemaID,
            ];
        }else{
            if(!\is_object($model)){
                $model = $ref->newInstance();
            }
            $target = [
                'tableSchema'   => $model->getSchema(),
                'tableSchemaID' => $model->getSchemaID(),
            ];
        }
        if($fromColumn === null || $fromColumn === '{primaryKey}'){
            if(empty($from['tableSchemaID'])){
                throw new ModelRelationsException('To use relationships, the model must have a primary key column.');
            }
        }else{
            $from['tableSchemaID'] = $fromColumn;
        }
        if($targetColumn === null || $targetColumn === '{primaryKey}'){
            if(empty($target['tableSchemaID'])){
                throw new ModelRelationsException('To use relationships, the model must have a primary key column.');
            }
        }else{
            $target['tableSchemaID'] = $targetColumn;
        }
        $this->join($target['tableSchema'], ($from['tableSchema'] . '.' . $from['tableSchemaID'] . ' = ' . $target['tableSchema'] . '.' . $target['tableSchemaID']), $joinType);
        return $this;
    }

    /**
     * @return bool
     */
    final public function isWritable(): bool
    {
        return $this->writable ?? true;
    }

    /**
     * @return bool
     */
    final public function isReadable(): bool
    {
        return $this->readable ?? true;
    }

    /**
     * @return bool
     */
    final public function isUpdatable(): bool
    {
        return $this->updatable ?? true;
    }

    /**
     * @return bool
     */
    final public function isDeletable(): bool
    {
        return $this->deletable ?? true;
    }

    /**
     * @param string ...$methods
     * @return bool
     */
    private function isCallbacksFunction(string ...$methods): bool
    {
        if(($this->allowedCallbacks ?? false) === FALSE){
            return false;
        }
        foreach ($methods as $method) {
            $callbacks = $this->{$method} ?? null;
            if(!empty($callbacks)){
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $data
     * @param string $method
     * @return array|false
     */
    private function callbacksFunctionHandler(array $data, string $method)
    {
        if(!$this->isCallbacksFunction($method)){
            return $data;
        }
        $callbacks = $this->{$method};

        foreach ($callbacks as $callback) {
            if(\is_string($callback)){
                if(\method_exists($this, $callback) === FALSE){
                    continue;
                }
                $data = \call_user_func_array([$this, $callback], [$data]);
                if($data === FALSE){
                    return false;
                }
                if(!\is_array($data)){
                    throw new ModelCallbacksException('Callbacks methods must return an array or false.');
                }
                continue;
            }
            if(!\is_callable($callback)){
                throw new ModelCallbacksException('Callbacks methods can only be model methods or callable.');
            }
            $data = \call_user_func_array($callback, [$data]);
            if($data === FALSE){
                return false;
            }
            if(!\is_array($data)){
                throw new ModelCallbacksException('Callbacks methods must return an array or false.');
            }
        }

        return $data;
    }

}
