<?php
/**
 * Model.php
 *
 * This file is part of Database.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.7
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \InitPHP\Database\Exceptions\{ModelCallbacksException,
    ModelException,
    ModelPermissionException,
    ModelRelationsException};
use InitPHP\Validation\Validation;


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

    private const VALIDATION_MSG_KEYS = [
        'integer', 'float', 'numeric', 'string',
        'boolean', 'array', 'mail', 'mailHost', 'url',
        'urlHost', 'empty', 'required', 'min', 'max',
        'length', 'range', 'regex', 'date', 'dateFormat',
        'ip', 'ipv4', 'ipv6', 'repeat', 'equals', 'startWith',
        'endWith', 'in', 'notIn', 'alpha', 'alphaNum',
        'creditCard', 'only', 'strictOnly', 'contains', 'notContains',
        'is_unique', 'allowedFields'
    ];

    private array $errors = [];

    private Validation $_validation;

    private bool $isOnlyDeleted = false;

    private ?string $useSoftDeletesField = null;

    public function __construct()
    {
        $this->table = $this->getSchema();
        if($this->getProperty('useSoftDeletes', true) !== FALSE){
            $deletedField = $this->getProperty('deletedField');
            if(empty($deletedField)){
                throw new ModelException('There must be a delete column to use soft delete.');
            }
            $this->useSoftDeletesField = $deletedField;
        }
        $this->_validation = new Validation();
        $this->validationMsgMergeAndSet();
        $configuration = $this->getProperty('connection', []);
        $configuration['tableSchema'] = $this->table;
        $configuration['tableSchemaID'] = $this->getProperty('primaryKey', null);
        $configuration['allowedFields'] = $this->getProperty('allowedFields', null);
        if($configuration['allowedFields'] !== null){
            $created_at = $this->getProperty('createdField');
            if(!empty($created_at)){
                $configuration['allowedFields'][] = $created_at;
            }
            $updated_at = $this->getProperty('updatedField');
            if(!empty($updated_at)){
                $configuration['allowedFields'][] = $updated_at;
            }
            $deleted_at = $this->getProperty('deletedField');
            if(!empty($deleted_at)){
                $configuration['allowedFields'][] = $deleted_at;
            }
        }
        $configuration['fetch'] = DB::FETCH_ENTITY;
        $configuration['entity'] = $this->getProperty('entity', Entity::class);
        parent::__construct($configuration);
    }

    public function __destruct()
    {
        unset($this->_validation);
    }

    /**
     * @return bool
     */
    public final function isError(): bool
    {
        $errorCode = $this->getDataMapper()->errorCode();
        if(!empty($errorCode) && $errorCode !== '00000'){
            $error = $this->getDataMapper()->errorInfo();
            if(isset($error[2]) && !empty($error[2])){
                $this->errors[] = $errorCode . ' - ' . $error[2];
            }
        }
        return !empty($this->errors);
    }

    /**
     * @return array
     */
    public final function getError(): array
    {
        return $this->errors;
    }

    /**
     * @param string $columnName
     * @return $this
     */
    public final function withPrimaryKey(string $columnName): self
    {
        $clone = clone $this;
        $clone->primaryKey = $columnName;
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
        $updateField = $this->getProperty('updatedField');

        if(!empty($updateField)){
            $data[$updateField] = \date('c');
        }

        $fields = $data;
        $parameters = [];
        if(!empty($this->getProperty('primaryKey')) && isset($fields[$this->getProperty('primaryKey')])){
            $primary_key = $this->getProperty('primaryKey');
            $this->getQueryBuilder()->where($primary_key, ':' . $primary_key);
            $parameters[':' . $primary_key] = $fields[$primary_key];
            unset($fields[$primary_key]);
        }
        foreach ($fields as $key => $value) {
            $parameters[':' . $key] = $value;
            $fields[$key] = ':' . $key;
        }
        $query = $this->getQueryBuilder();

        if(!empty($this->useSoftDeletesField)){
            $query->is($this->useSoftDeletesField, null);
        }
        $query = $query->updateQuery($fields);
        $this->getQueryBuilder()->reset();


        $res = $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($parameters, $this->getParameters()));
        if($res === FALSE || $this->getDataMapper()->numRows() < 1){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterUpdate');
    }

    /**
     * @param array $data
     * @return array|false
     */
    public final function insert(array $data)
    {
        if($this->isWritable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a writable model.');
        }
        if(($data = $this->callbacksFunctionHandler($data, 'beforeInsert')) === FALSE){
            return false;
        }

        $createdField = $this->getProperty('createdField');
        $data = $this->singleInsertDataValid($data, (empty($createdField) ? [] : [$createdField => \date('c')]));
        if($data === FALSE){
            return false;
        }

        $fields = [];
        $parameters = [];
        foreach ($data as $key => $value) {
            $parameters[':' . $key] = $value;
            $fields[$key] = ':' . $key;
        }

        $query = $this->getQueryBuilder()->insertQuery($fields);
        $this->getQueryBuilder()->reset();
        $res = $this->getDataMapper()->persist($query, $this->getDataMapper()->buildQueryParameters($parameters, $this->getParameters()));
        if($res === FALSE || $this->getDataMapper()->numRows() < 1){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterInsert');
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

        $query = $this->getQueryBuilder();

        if(!empty($this->useSoftDeletesField)){
            $query->is($this->useSoftDeletesField, null);
        }

        if($id !== null && !empty($this->getSchemaID())){
            $query = $query->where($this->getSchemaID(), $id);
        }
        $res = clone $query;
        $resQuery = $res->readQuery();
        $res->reset();
        $this->getDataMapper()->asAssoc()
            ->persist($resQuery, $this->getParameters(false));
        $data = $this->getDataMapper()->numRows() > 0 ? $this->getDataMapper()->results() : [];
        unset($res);

        if(empty($data) || ($data = $this->callbacksFunctionHandler($data, 'beforeDelete')) === FALSE){
            return false;
        }

        if(!empty($this->useSoftDeletesField)){
            $query = $query->updateQuery([
                $this->useSoftDeletesField => ':' . $this->useSoftDeletesField,
            ]);
            $this->setParameter(':' . $this->useSoftDeletesField, \date('c'));
        }else{
            $query = $query->deleteQuery();
        }
        $this->getQueryBuilder()->reset();

        $res = $this->getDataMapper()->persist($query, $this->getParameters(true));
        if($res === FALSE || $this->getDataMapper()->numRows() < 1){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterDelete');
    }

    public final function getSchema(): string
    {
        $table = $this->getProperty('table', null);
        if(empty($table)){
            $modelClass = \get_called_class();
            $modelClassSplit = \explode('\\', $modelClass);
            $table = $this->table = \strtolower(\end($modelClassSplit));
        }
        return $table;
    }

    public final function getSchemaID()
    {
        return $this->getProperty('primaryKey');
    }

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


    public final function first()
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        return parent::first();
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
            $this->setParameter(':'. $this->getSchemaID(), $id);
        }
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        return $this->row();
    }

    /**
     * @param string $column
     * @return array|false
     */
    public function findColumn(string $column)
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        $this->getQueryBuilder()->select($column);
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        $this->get();
        if($this->getDataMapper()->numRows() < 1){
            return false;
        }
        $row = $this->getDataMapper()->results();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public final function findAll(int $limit = 100, int $offset = 0): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        return parent::findAll($limit, $offset);
    }

    /**
     * @return array|false
     */
    public function all(): array
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . \get_called_class() . '" is not a readable model.');
        }
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        $this->get();
        return $this->rows();
    }

    /**
     * @return $this
     */
    public function onlyDeleted(): self
    {
        if(!empty($this->useSoftDeletesField)){
            $this->getQueryBuilder()->isNot($this->useSoftDeletesField, null);
        }
        $this->isOnlyDeleted = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function onlyUndeleted(): self
    {
        if(!empty($this->useSoftDeletesField)){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        $this->isOnlyDeleted = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(): \PDOStatement
    {
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        $this->isOnlyDeleted = false;
        return parent::get();
    }

    /**
     * @inheritDoc
     */
    public function read(array $selector = [], array $conditions = [], array $parameters = []): array
    {
        if(!empty($this->useSoftDeletesField) && $this->isOnlyDeleted === FALSE){
            $this->getQueryBuilder()->is($this->useSoftDeletesField, null);
        }
        $this->isOnlyDeleted = false;
        return parent::read($selector, $conditions, $parameters);
    }

    /**
     * @return bool
     */
    public function purgeDeleted(): bool
    {
        if($this->isDeletable() === FALSE){
            return false;
        }
        if(!empty($this->useSoftDeletesField)){
            $query = $this->getQueryBuilder()
                ->isNot($this->useSoftDeletesField, null)
                ->deleteQuery();
            $this->getQueryBuilder()->reset();

            return $this->getDataMapper()->persist($query, []) !== FALSE;
        }
        return false;
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

    protected final function setError(string $column, string $msg, array $context = []): void
    {
        $column = \trim($column);
        if(!isset($context['model'])){
            $context['model'] = \get_called_class();
        }
        $replace = []; $i = 0;
        foreach ($context as $key => $value) {
            if(!\is_string($value)){
                $value = (string)$value;
            }
            $replace['{'.$key.'}'] = $value;
            $replace['{'.$i.'}'] = $value;
            ++$i;
        }
        $msg = \strtr($msg, $replace);
        if(!empty($column)){
            $this->errors[$column] = $msg;
            return;
        }
        $this->errors[] = $msg;
    }

    protected final function getProperty($property, $default = null)
    {
        return $this->{$property} ?? $default;
    }

    /**
     * @param array $fields
     * @param array $add
     * @return false|array
     */
    private function singleInsertDataValid(array $fields, array $add = [])
    {
        $res = [];
        $allowedFields = $this->getProperty('allowedFields');
        foreach ($fields as $column => $value) {
            if(!empty($allowedFields) && !\in_array($column, $allowedFields, true)){
                continue;
            }
            if($this->isValid($column, $value, []) === FALSE){
                continue;
            }
            $res[$column] = $value;
        }
        if(empty($res)){
            return false;
        }
        return empty($add) ? $res : \array_merge($res, $add);
    }

    private function isValid($column, $value, $uniqueWhere = []): bool
    {
        $methods = $this->columnValidationMethods($column);
        if(empty($methods)){
            return true;
        }
        $localeArray = [];
        foreach (self::VALIDATION_MSG_KEYS as $msgKey) {
            $localeArray[$msgKey] = $this->validationMsg[$column][$msgKey] ?? $this->validationMsg[$msgKey];
        }

        $real_value = (\is_string($value) && Helper::str_starts_with($value, ':')) ? ($this->_DBArguments[$value] ?? $value) : $value;

        $validation = $this->_validation
            ->setLocaleArray($localeArray)
            ->setData([$column => $real_value]);
        if(\in_array('is_unique', $methods)){
            $key = \array_search('is_unique', $methods);
            unset($methods[$key]);
            $res = clone $this;
            $res->getQueryBuilder()->reset()
                ->select($column)
                ->where($column, $value, '=');
            if (\is_string($value) && Helper::str_starts_with($value, ':')) {
                $res->setParameter($value, $real_value);
            }
            if(\is_array($uniqueWhere) && !empty($uniqueWhere)){
                foreach ($uniqueWhere as $uKey => $uVal) {
                    $res->getQueryBuilder()->where($uKey, $uVal, '!=');
                }
            }
            $res->getQueryBuilder()->limit(1);
            $res->get();
            if($res->getDataMapper()->numRows() > 0){
                $this->setError($column, ($this->validationMsg[$column]['is_unique'] ?? '{field} must be unique.'), ['field' => $column]);
                return false;
            }
            unset($res);
            if(empty($methods)){
                return true;
            }
        }
        foreach ($methods as $rule) {
            $validation->rule($column, $rule);
        }
        if($validation->validation()){
            return true;
        }
        foreach ($validation->getError() as $err) {
            $this->setError($column, $err);
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

    private function columnValidationMethods(string $column): array
    {
        $methods = $this->validation[$column] ?? [];
        return \is_string($methods) ? \explode('|', $methods) : $methods;
    }

    private function validationMsgMergeAndSet()
    {
        $defaultMsg = [
            'notValidDefault'   => 'The {field} value is not valid.',
            'integer'           => '{field} must be an integer.',
            'float'             => '{field} must be an float.',
            'numeric'           => '{field} must be an numeric.',
            'string'            => '{field} must be an string.',
            'boolean'           => '{field} must be an boolean',
            'array'             => '{field} must be an Array.',
            'mail'              => '{field} must be an E-Mail address.',
            'mailHost'          => '{field} the email must be a {2} mail.',
            'url'               => '{field} must be an URL address.',
            'urlHost'           => 'The host of the {field} url must be {2}.',
            'empty'             => '{field} must be empty.',
            'required'          => '{field} cannot be left blank.',
            'min'               => '{field} must be greater than or equal to {2}.',
            'max'               => '{field} must be no more than {2}.',
            'length'            => 'The {field} length range must be {2}.',
            'range'             => 'The {field} range must be {2}.',
            'regex'             => '{field} must match the {2} pattern.',
            'date'              => '{field} must be a date.',
            'dateFormat'        => '{field} must be a correct date format.',
            'ip'                => '{field} must be the IP Address.',
            'ipv4'              => '{field} must be the IPv4 Address.',
            'ipv6'              => '{field} must be the IPv6 Address.',
            'repeat'            => '{field} must be the same as {field1}',
            'equals'            => '{field} can only be {2}.',
            'startWith'         => '{field} must start with "{2}".',
            'endWith'           => '{field} must end with "{2}".',
            'in'                => '{field} must contain {2}.',
            'notIn'             => '{field} must not contain {2}.',
            'alpha'             => '{field} must contain only alpha characters.',
            'alphaNum'          => '{field} can only be alphanumeric.',
            'creditCard'        => '{field} must be a credit card number.',
            'only'              => 'The {field} value is not valid.',
            'strictOnly'        => 'The {field} value is not valid.',
            'contains'          => '{field} must contain {2}.',
            'notContains'       => '{field} must not contain {2}.',
            'is_unique'         => '{field} must be unique.',
            'allowedFields'     => 'Access is not granted to any of the specified tables.'
        ];
        $this->_validation->labels($this->getProperty('validationLabels', []));
        $this->validationMsg = \array_merge($defaultMsg, $this->getProperty('validationMsg', []));
    }


}