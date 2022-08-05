<?php
/**
 * Model.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.8
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \InitPHP\Database\Exceptions\{ModelCallbacksException,
    ModelException,
    ModelPermissionException,
    ModelRelationsException};



class Model extends DB
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
        if(empty($this->getProperty('table', null))){
            $modelClass = \get_called_class();
            $modelReflection = new \ReflectionClass($modelClass);
            $this->table = \strtolower($modelReflection->getShortName());
            unset($modelReflection);
        }
        if($this->getProperty('useSoftDeletes', true) !== FALSE){
            $deletedField = $this->getProperty('deletedField');
            if(empty($deletedField)){
                throw new ModelException('There must be a delete column to use soft delete.');
            }
        }
        $configuration = $this->getProperty('connection', []);
        $configuration['tableSchema'] = $this->table;
        $configuration['tableSchemaID'] = $this->getProperty('primaryKey', null);
        $configuration['allowedFields'] = $this->getProperty('allowedFields', null);
        $configuration['createdField'] = $this->getProperty('createdField');
        $configuration['updatedField'] = $this->getProperty('updatedField');
        $configuration['deletedField'] = $this->getProperty('deletedField');
        if($configuration['allowedFields'] !== null){
            if(!empty($configuration['createdField'])){
                $configuration['allowedFields'][] = $configuration['createdField'];
            }
            if(!empty($configuration['updatedField'])){
                $configuration['allowedFields'][] = $configuration['updatedField'];
            }
            if(!empty($configuration['deletedField'])){
                $configuration['allowedFields'][] = $configuration['deletedField'];
            }
        }
        $configuration['fetch'] = DB::FETCH_ENTITY;
        $configuration['entity'] = $this->getProperty('entity', Entity::class);
        $configuration['validation'] = [
            'methods'   => $this->getProperty('validation', []),
            'messages'  => $this->getProperty('validationMsg', []),
            'labels'    => $this->getProperty('validationLabels', []),
        ];
        parent::__construct($configuration);
    }

    /**
     * @param string $columnName
     * @return $this
     */
    public final function withPrimaryKey(string $columnName): self
    {
        $clone = clone $this;
        $clone->primaryKey = $columnName;
        $clone->setSchemaID($columnName);
        return $clone;
    }

    /**
     * @see Model::insert()
     * @param array $fields
     * @return array
     */
    public final function create(array $fields)
    {
        return $this->insert($fields);
    }

    /**
     * @param array $data
     * @return array|false
     * @throws Exceptions\ValidationException
     */
    public final function insert(array $data)
    {
        if($this->isWritable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a writable model.');
        }
        if(($data = $this->callbacksFunctionHandler($data, 'beforeInsert')) === FALSE){
            return false;
        }

        // TODO : Validation Operation

        $create = parent::create($data);

        if($create === FALSE){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterInsert');
    }

    /**
     * @inheritDoc
     */
    public final function read(array $selector = [], array $conditions = [], array $parameters = []): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::read($selector, $conditions, $parameters);
    }

    /**
     * @inheritDoc
     */
    public final function readOne(array $selector = [], array $conditions = [], array $parameters = [])
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::readOne($selector, $conditions, $parameters);
    }

    /**
     * @param Entity $entity
     * @return array|false
     */
    public final function save(Entity $entity)
    {
        $data = $entity->getAttributes();
        $primaryKey = $this->getProperty('primaryKey');
        if(!empty($primaryKey) && isset($entity->{$primaryKey})){
            return $this->update($data);
        }
        return $this->insert($data);
    }

    /**
     * @param array $fields
     * @return array|bool
     */
    public final function update(array $fields)
    {
        if($this->isUpdatable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a updatable model.');
        }
        if(($data = $this->callbacksFunctionHandler($fields, 'beforeUpdate')) === FALSE){
            return false;
        }
        $update = parent::update($data);
        if(!$update){
            return false;
        }

        return $data = $this->callbacksFunctionHandler($data, 'afterUpdate');
    }


    /**
     * @param string|int|null $id
     * @return array|false
     */
    public final function delete($id = null)
    {
        if($this->isDeletable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a deletable model.');
        }

        if($id !== null && !empty($this->getSchemaID())){
            $this->getQueryBuilder()->where($this->getSchemaID(), ':' . $this->getSchemaID(), '=');
            $this->getDataMapper()->setParameter(':' . $this->getSchemaID(), $id);
        }

        $query = $this->getQueryBuilder();
        $res = clone $query;
        $resQuery = $res->readQuery();
        $res->reset();
        $parameters = $this->getDataMapper()->getParameters();
        $this->getDataMapper()
            ->asAssoc()
            ->persist($resQuery, $parameters);
        $data = $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
        unset($res);
        if(!empty($parameters)){
            $this->getDataMapper()->setParameters($parameters);
            unset($parameters);
        }

        if(empty($data) || ($data = $this->callbacksFunctionHandler($data, 'beforeDelete')) === FALSE){
            return false;
        }

        $delete = parent::delete();

        if($delete === FALSE){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterDelete');
    }

    /**
     * @param string $model
     * @param string|null $fromColumn
     * @param string|null $targetColumn
     * @param string $joinType
     * @return $this
     */
    public final function relations(string $model, ?string $fromColumn = null, ?string $targetColumn = null, string $joinType = 'INNER'): self
    {
        $from = [
            'tableSchema'   => $this->getSchema(),
            'tableSchemaID' => $this->getSchemaID(),
        ];
        $target = $this->getModelClassSchemaAndSchemaID($model);

        if($fromColumn === null || $fromColumn == '{primaryKey}'){
            if(empty($from['tableSchemaID'])){
                throw new ModelRelationsException('To use relationships, the model must have a primary key column.');
            }
        }else{
            $from['tableSchemaID'] = $fromColumn;
        }
        if($targetColumn === null || $targetColumn == '{primaryKey}'){
            if(empty($target['tableSchemaID'])){
                throw new ModelRelationsException('To use relationships, the model must have a primary key column.');
            }
        }else{
            $target['tableSchemaID'] = $targetColumn;
        }

        $onStmt = $from['tableSchema'] . '.' . $from['tableSchemaID']
            . '='
            . $target['tableSchema'] . '.' . $target['tableSchemaID'];
        $this->getQueryBuilder()->join($target['tableSchema'], $onStmt, $joinType);
        return $this;
    }

    /**
     * @param array $selectors
     * @param array $conditions
     * @param array $parameters
     * @return array
     */
    public final function findBy(array $selectors = [], array $conditions = [], array $parameters = []): array
    {
        return $this->read($selectors, $conditions, $parameters);
    }

    /**
     * @param array $conditions
     * @return array|Entity|object|null
     */
    public final function findOneBy(array $conditions = [])
    {
        return $this->readOne([], $conditions);
    }

    /**
     * @return array|Entity|object|null
     */
    public final function first()
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        $this->getQueryBuilder()->limit(1);
        return $this->findOneBy();
    }

    /**
     * @param $id
     * @return array|Entity|object|null
     */
    public final function find($id = null)
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        $this->getQueryBuilder()->offset(0)->limit(1);
        if($id !== null && !empty($this->getSchemaID())){
            $this->getQueryBuilder()->where($this->getSchemaID(), ':' . $this->getSchemaID(), '=');
            $this->getDataMapper()->setParameter(':'. $this->getSchemaID(), $id);
        }
        return $this->findOneBy();
    }

    /**
     * @inheritDoc
     */
    public final function findAll(int $limit = 100, int $offset = 0): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::findAll($limit, $offset);
    }

    /**
     * @param string $column
     * @return array|Entity[]|object[]
     */
    public function findColumn(string $column): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::read([$column]);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        return parent::read();
    }

    /**
     * @return bool
     */
    public function purgeDeleted(): bool
    {
        if($this->isDeletable() === FALSE){
            return false;
        }
        $this->onlyDeleted();
        return parent::delete();
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->getProperty('writable', true);
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->getProperty('readable', true);
    }

    /**
     * @return bool
     */
    public function isUpdatable(): bool
    {
        return $this->getProperty('updatable', true);
    }

    /**
     * @return bool
     */
    public function isDeletable(): bool
    {
        return $this->getProperty('deletable', true);
    }

    protected final function getProperty($property, $default = null)
    {
        return $this->{$property} ?? $default;
    }

    /**
     * @param array $data
     * @param string $method
     * @return array|false
     */
    private function callbacksFunctionHandler(array $data, string $method)
    {
        if($this->getProperty('allowedCallbacks', false) === FALSE){
            return $data;
        }
        if(empty($this->getProperty($method, null))){
            return $data;
        }
        $callbacks = $this->getProperty($method, null);
        if(!\is_array($callbacks)){
            return $data;
        }
        foreach ($callbacks as $callback) {
            if(\is_string($callback)){
                if(\method_exists($this, $callback) === FALSE){
                    continue;
                }
                $data = \call_user_func_array([$this, $callback], [$data]);
                if(!\is_array($data) && $data !== FALSE){
                    throw new ModelCallbacksException('Callbacks methods must return an array or false.');
                }
                continue;
            }
            if(!\is_callable($callback)){
                throw new ModelCallbacksException('Callbacks methods can only be model methods or callable.');
            }
            $data = \call_user_func_array($callback, [$data]);
            if(!\is_array($data) && $data !== FALSE){
                throw new ModelCallbacksException('Callbacks methods must return an array or false.');
            }
        }
        return $data;
    }

    /**
     * @param Model|string $model
     * @return string[]
     */
    private function getModelClassSchemaAndSchemaID($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if($reflection->isSubclassOf(Model::class) === FALSE){
                throw new ModelRelationsException('The target class must be a subclass of Model.');
            }
            if(\defined('PHP_VERSION_ID') && \PHP_VERSION_ID >= 80000){
                $table = $reflection->getProperty('table');
                $primaryKey = $reflection->getProperty('primaryKey');
                if(($tableSchema = $table->getDefaultValue()) === null){
                    $tableSchema = \strtolower($reflection->getShortName());
                }
                $tableSchemaID = $primaryKey->getDefaultValue();
                return [
                    'tableSchema'   => $tableSchema,
                    'tableSchemaID' => $tableSchemaID
                ];
            }
            if(\is_object($model)){
                return [
                    'tableSchema'   => $model->getSchema(),
                    'tableSchemaID' => $model->getSchemaID(),
                ];
            }
            /** @var Model $instance */
            $instance = $reflection->newInstance();
            return [
                'tableSchema'   => $instance->getSchema(),
                'tableSchemaID' => $instance->getSchemaID(),
            ];
        } catch (\Exception $e) {
            throw new ModelRelationsException($e->getMessage(), (int)$e->getCode());
        }
    }

}
