<?php
/**
 * Model.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.2
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database;

use \InitPHP\Database\Exception\{ModelException, ModelPermissionException};
use InitPHP\Database\Interfaces\{ConnectionInterface, ModelInterface, EntityInterface};
use \InitPHP\Validation\Validation;

use const COUNT_RECURSIVE;

use function get_called_class;
use function explode;
use function strtolower;
use function end;
use function count;
use function date;
use function trim;
use function is_string;
use function strtr;
use function in_array;
use function array_search;
use function is_array;
use function method_exists;
use function call_user_func_array;
use function array_merge;

class Model extends DB implements ModelInterface
{
    use RelationshipsTrait;

    /**
     * @var string[]
     */
    protected array $connection;

    /**
     * Dönüş için kullanılacak Entity sınıfı ya da nesnesi.
     *
     * @var EntityInterface|string
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

    protected array $errors = [];

    private static Validation $_DBValidation;

    public function __construct()
    {
        if(empty($this->getProperty('table'))){
            $modelClass = get_called_class();
            $modelClassSplit = explode('\\', $modelClass);
            $this->table = strtolower(end($modelClassSplit));
        }
        if($this->getProperty('useSoftDeletes', true) !== FALSE && empty($this->getProperty('deletedField'))){
            throw new ModelException('There must be a delete column to use soft delete.');
        }
        if(!isset(self::$_DBValidation)){
            self::$_DBValidation = new Validation();
        }
        $this->validationMsgMergeAndSet();
        parent::__construct($this->getProperty('connection', []));
    }

    /**
     * @inheritDoc
     */
    public final function isError(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @inheritDoc
     */
    public final function getError(): array
    {
        return $this->errors;
    }

    /**
     * @inheritDoc
     */
    public final function withPrimaryKey(string $columnName): self
    {
        $clone = clone $this;
        $clone->primaryKey = $columnName;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public final function create(array $data)
    {
        return $this->insert($data);
    }

    /**
     * @inheritDoc
     */
    public final function save(EntityInterface $entity)
    {
        $data = $entity->getAttributes();
        $primaryKey = $this->getProperty('primaryKey');
        if(!empty($primaryKey) && isset($entity->{$primaryKey})){
            return $this->update($data, $entity->{$primaryKey});
        }
        return $this->insert($data);
    }

    /**
     * @inheritDoc
     */
    public final function insert(array $data)
    {
        if($this->isWritable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a writable model.');
        }
        $data = $this->callbacksFunctionHandler($data, 'beforeInsert');
        if(!empty($this->getProperty('allowedFields', null))){
            $createdField = $this->getProperty('createdField');
            if(!empty($createdField) && !in_array($createdField, $this->allowedFields)){
                $this->allowedFields[] = $this->getProperty('createdField');
            }
        }
        if(count($data) !== count($data, COUNT_RECURSIVE)){
            $rows = $data;
            $data = [];
            foreach ($rows as $row) {
                if(($row = $this->singleInsertDataProcess($row)) === FALSE){
                    return false;
                }
                $data[] = $row;
            }
            unset($rows);
        }else{
            if(($data = $this->singleInsertDataProcess($data)) === FALSE){
                return false;
            }
        }
        $sql = $this->from($this->getProperty('table'))->insertStatementBuild($data);
        $this->clear();
        if($this->query($sql) === FALSE){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterInsert');
    }


    /**
     * @inheritDoc
     */
    public final function update(array $data, $id = null)
    {
        if($this->isUpdatable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a updatable model.');
        }
        $data = $this->callbacksFunctionHandler($data, 'beforeUpdate');
        $where = $id !== null && !empty($this->getProperty('primaryKey')) ? [$this->getProperty('primaryKey') => $id] : [];
        foreach ($data as $key => $value) {
            if($this->isValid($key, $value, $where) === FALSE){
                return false;
            }
        }
        if(!empty($this->getProperty('allowedFields', null))){
            $updateField = $this->getProperty('updatedField');
            if(!empty($updateField) && !in_array($updateField, $this->allowedFields)){
                $this->allowedFields[] = $this->getProperty('updatedField');
            }
            $data[$updateField] = date('c');
        }
        $sql = $this->from($this->getProperty('table'))->updateStatementBuild($data);
        $this->clear();
        if($this->query($sql) === FALSE){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterUpdate');
    }

    /**
     * @inheritDoc
     */
    public final function delete($id = null)
    {
        if($this->isDeletable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a deletable model.');
        }
        $res = $this->from($this->table);
        if($id !== null && !empty($this->getProperty('primaryKey'))){
            $res->where($this->getProperty('primaryKey'), $id, '=');
        }
        $clone = clone $res;
        $res->asAssoc()->get();
        $data = $res->rows();
        $data = $this->callbacksFunctionHandler($data, 'beforeDelete');

        if(!empty($this->getProperty('allowedFields', null))){
            $deletedField = $this->getProperty('deletedField');
            if(!empty($deletedField) && !in_array($deletedField, $this->allowedFields)){
                $this->allowedFields[] = $this->getProperty('deletedField');
            }
        }

        if($this->getProperty('useSoftDeletes', true) !== FALSE){
            $sql = $this->updateStatementBuild([$this->getProperty('deletedField') => date('c')]);
        }else{
            $sql = $this->deleteStatementBuild();
        }
        $this->clear();
        if($this->query($sql) === FALSE){
            return false;
        }
        return $data = $this->callbacksFunctionHandler($data, 'afterDelete');
    }

    /**
     * @inheritDoc
     */
    public final function first()
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a readable model.');
        }
        $res = $this->offset(0)
            ->limit(1);
        $res->get();
        $row = $res->row();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public final function find($id = null)
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a readable model.');
        }
        $res = $this->offset(0)->limit(1);
        if($id !== null && !empty($this->getProperty('primaryKey'))){
            $res->where($this->getProperty('primaryKey'), $id, '=');
        }
        $res->get();
        $row = $res->row();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public function findColumn(string $column)
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a readable model.');
        }
        $res = $this->select($column);
        $res->get();
        if($res->numRows() < 1){
            return false;
        }
        $row = $res->rows();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public function findAll(int $limit = 100, int $offset = 0)
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a readable model.');
        }
        $res = $this->offset($offset)
            ->limit($limit);
        $res->get();
        if($res->numRows() < 1){
            return false;
        }
        $row = $res->rows();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public function all()
    {
        if($this->isReadable() === FALSE){
            throw new ModelPermissionException('"' . get_called_class() . '" is not a readable model.');
        }
        $this->get();
        $row = $this->rows();
        return !empty($row) ? $row : false;
    }

    /**
     * @inheritDoc
     */
    public function onlyDeleted(): self
    {
        if(!empty($this->getProperty('useSoftDeletes')) && !empty($this->getProperty('deletedField'))){
            $this->isNot($this->getProperty('deletedField'), null);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function onlyUndeleted(): self
    {
        if(!empty($this->getProperty('useSoftDeletes')) && !empty($this->getProperty('deletedField'))){
            $this->is($this->getProperty('deletedField'), null);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function purgeDeleted(): bool
    {
        if($this->isDeletable() === FALSE){
            return false;
        }
        if(!empty($this->getProperty('useSoftDeletes')) && !empty($this->getProperty('deletedField'))){
            $sql = $this->isNot($this->getProperty('deletedField'), null)->deleteStatementBuild();
            $this->clear();
            if($sql === ''){
                return false;
            }
            if($this->query($sql) === FALSE){
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return $this->getProperty('writable', true);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->getProperty('readable', true);
    }

    /**
     * @inheritDoc
     */
    public function isUpdatable(): bool
    {
        return $this->getProperty('updatable', true);
    }

    /**
     * @inheritDoc
     */
    public function isDeletable(): bool
    {
        return $this->getProperty('deletable', true);
    }

    protected final function setError(string $column, string $msg, array $context = []): void
    {
        $column = trim($column);
        if(!isset($context['model'])){
            $context['model'] = get_called_class();
        }
        $replace = []; $i = 0;
        foreach ($context as $key => $value) {
            if(!is_string($value)){
                $value = (string)$value;
            }
            $replace['{'.$key.'}'] = $value;
            $replace['{'.$i.'}'] = $value;
            ++$i;
        }
        $msg = strtr($msg, $replace);
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

    private function singleInsertDataProcess($data)
    {
        $res = [];
        foreach ($data as $key => $value) {
            if(!empty($this->allowedFields) && in_array($key, $this->allowedFields, true) === FALSE){
                continue;
            }
            if($this->isValid($key, $value, []) === FALSE){
                return false;
            }
            $res[$key] = $value;
        }
        $data = $res;
        unset($res);
        if(empty($data)){
            return false;
        }
        if(!empty($this->createdField)){
            $data[$this->createdField] = date('c');
        }
        return $data;
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
        $validation = self::$_DBValidation
            ->setLocaleArray($localeArray)
            ->setData([$column => $value]);
        if(in_array('is_unique', $methods)){
            $key = array_search('is_unique', $methods);
            unset($methods[$key]);
            $res = clone $this;
            $res->clear()
                ->select($column)
                ->where($column, $value, '=');
            if(is_array($uniqueWhere) && !empty($uniqueWhere)){
                foreach ($uniqueWhere as $uKey => $uVal) {
                    $res->where($uKey, $uVal, '!=');
                }
            }
            $res->limit(1)->get();
            if($res->numRows() > 0){
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

    private function callbacksFunctionHandler(array $data, string $method)
    {
        if($this->getProperty('allowedCallbacks', true) === FALSE){
            return $data;
        }
        if(empty($this->getProperty($method, null))){
            return $data;
        }
        $callbacks = $this->getProperty($method, null);
        if(!is_array($callbacks)){
            return $data;
        }
        foreach ($callbacks as $callback) {
            if(is_string($callback)){
                if(method_exists($this, $callback) === FALSE){
                    continue;
                }
                $data = call_user_func_array([$this, $callback], [$data]);
                continue;
            }
            if(!is_callable($callback)){
                continue;
            }
            $data = call_user_func_array($callback, [$data]);
        }
        return $data;
    }

    private function columnValidationMethods(string $column): array
    {
        $methods = $this->validation[$column] ?? [];
        return is_string($methods) ? explode('|', $methods) : $methods;
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
        static::$_DBValidation->labels($this->getProperty('validationLabels', []));
        $this->validationMsg = array_merge($defaultMsg, $this->getProperty('validationMsg', []));
    }

}
