<?php
/**
 * ModelInterface.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Database\Interfaces;

use InitPHP\Database\Exception\ModelPermissionException;

interface ModelInterface extends DBInterface
{

    /**
     * Doğrulama vs. hata var mı?
     *
     * Oluşan hata varsa bunlar ModelInterface::getError() yöntemi ile alınabilir.
     *
     * @return bool
     */
    public function isError(): bool;

    /**
     * Doğrulama vs. hatalarını tutan hata dizisini verir.
     *
     * @return array
     */
    public function getError(): array;

    /**
     * Bir ya da daha fazla satırı ekler. ModelInterface::insert() yönteminin diğer adıdır.
     *
     * @uses ModelInterface::insert()
     * @param array $data
     * @return array|false
     */
    public function create(array $data);

    /**
     * Bir Entity nesnesini kullanarak veriyi ekler ya da günceller.
     *
     * @uses ModelInterface::update()
     * @uses ModelInterface::insert()
     * @param EntityInterface $entity
     * @return array|false
     */
    public function save(EntityInterface $entity);

    /**
     * Bir ya da daha fazla satırı ekler.
     *
     * @used-by ModelInterface::save()
     * @used-by ModelInterface::create()
     * @param array $data
     * @return array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function insert(array $data);

    /**
     * Bir ya da daha fazla satırda güncelleme yapar.
     *
     * @param array $data
     * @param null|int|string $id
     * @return array|false
     * @throws ModelPermissionException <p>Model'in veri güncelleme izni yoksa.</p>
     */
    public function update(array $data, $id = null);

    /**
     * Bir ya da daha fazla veriyi siler.
     *
     * Eğer yumuşak silme kullanılmıyorsa; verileri kalıcı olarak sileceğini unutmayın.
     *
     * @param null|int|string $id <p>Varsa PRIMARY KEY sütunun değeri</p>
     * @return array|false
     * @throws ModelPermissionException <p>Model'in silme izni yoksa.</p>
     */
    public function delete($id = null);

    /**
     * İlk satırı döndürür.
     *
     * @return EntityInterface|object|array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function first();

    /**
     * Bir veriyi arar ve döndürür.
     *
     * @param null|int|string $id <p>Varsa PRIMARY KEY sütununun değeri.</p>
     * @return EntityInterface|object|array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function find($id = null);

    /**
     * Sadece belli bir sütunu seçer ve sonucu döndürür. Bu Select ile sütun seçer.
     *
     * @param string $column
     * @return EntityInterface[]|object[]|array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function findColumn(string $column);

    /**
     * Belli aralıktaki verileri çeker ve döndürür.
     *
     * @param int $limit
     * @param int $offset
     * @return EntityInterface[]|object[]|array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function findAll(int $limit = 100, int $offset = 0);

    /**
     * Tüm satırları çeker ve döndürür.
     *
     * @return EntityInterface[]|object[]|array|false
     * @throws ModelPermissionException <p>Model'in veri okuma izni yoksa.</p>
     */
    public function all();

    /**
     * Sadece yumuşak silme ile silinmiş verileri seçer.
     *
     * @return ModelInterface
     */
    public function onlyDeleted(): ModelInterface;

    /**
     * Sadece yumuşak silme ile silinmemiş verileri seçer.
     *
     * @return ModelInterface
     */
    public function onlyUndeleted(): ModelInterface;

    /**
     * Yumuşak silme ile silinmiş verileri seçer ve kalıcı olarak siler.
     *
     * @return bool
     */
    public function purgeDeleted(): bool;

    /**
     * Model'in yeni veri oluşturma yetkisi var mı?
     *
     * @used-by ModelInterface::insert()
     * @return bool
     */
    public function isWritable(): bool;

    /**
     * Model'in veri okumaya yetkisi var mı?
     *
     * @used-by ModelInterface::first()
     * @used-by ModelInterface::find()
     * @used-by ModelInterface::findAll()
     * @used-by ModelInterface::findColumn()
     * @used-by ModelInterface::all()
     * @return bool
     */
    public function isReadable(): bool;

    /**
     * Model'in günceleme yetkisi var mı?
     *
     * @used-by ModelInterface::update()
     * @return bool
     */
    public function isUpdatable(): bool;

    /**
     * Model veri silme yetkisi var mı?
     *
     * @used-by ModelInterface::delete()
     * @return bool
     */
    public function isDeletable(): bool;

}
