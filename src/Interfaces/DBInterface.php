<?php
/**
 * DBInterface.php
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

namespace InitPHP\Database\Interfaces;

use InitPHP\Database\Exception\QueryExecuteException;

interface DBInterface extends QueryBuilderInterface
{

    /**
     * DB::get() ya da DB::query() yöntemlerinin son işleminden etkilenen satır sayısını verir.
     *
     * @param null|DBInterface|\PDOStatement $dbOrPDOStatement
     * @return int
     */
    public function numRows($dbOrPDOStatement = null): int;

    /**
     * DB::query() ya da DB::exec() ile yürütülmüş son SQL cümlesini verir.
     *
     * @return string|null
     */
    public function lastSQL(): ?string;

    /**
     * Son DBInterface::query() ile yürütülmüş son SQL cümlesinden varsa son eklenen PRIMARY KEY değeri.
     *
     * @return int|null
     */
    public function insertId(): ?int;

    /**
     * Kendisinden sonraki ilk sonuç için dönüş tipini \PDO::FETCH_ASSOC olarak değiştirir.
     *
     * @return DBInterface
     */
    public function asAssoc(): DBInterface;

    /**
     * Kendisinden sonraki ilk sonuç için dönüş tipini \PDO::FETCH_BOTH olarak değiştirir.
     *
     * @return DBInterface
     */
    public function asArray(): DBInterface;

    /**
     * Kendisinden sonraki ilk sonuç için dönüş tipini \PDO::FETCH_OBJ olarak değiştirir.
     *
     * @return DBInterface
     */
    public function asObject(): DBInterface;

    /**
     * Transaction işlemlerinin son durumunu verir.
     *
     * @return bool <p>Transaction sırasında bir hata oluştuysa FALSE, herşey yolunda ise TRUE verir.</p>
     */
    public function transactionStatus(): bool;

    /**
     * \PDO üzerinde Transaction sürecini başlatır.
     *
     * @param bool $testMode <p>$testMode aktif ise (TRUE) işlemler her durumda (DB::transactionComplete() tarafından) geri alınır.</p>
     * @return DBInterface
     */
    public function transactionStart(bool $testMode = false): DBInterface;

    /**
     * Bir PDO transaction sürecini sona erdirir.
     *
     * İşlemlerden en az biri başarısız olursa ya da $testMode açıksa süreç sırasındaki işlemler geri alınır.
     *
     * @return DBInterface
     */
    public function transactionComplete(): DBInterface;

    /**
     * DB::get() ile yürütülmüş sorgudan etkilenen son satırı döndürür.
     *
     * Bu \PDOStatement::fetchAll() eş değeridir.
     *
     * @return object[]|EntityInterface[]|array|null
     */
    public function rows();

    /**
     * DB::get() ile yürütülmüş sorgudan etkilenen son satırı döndürür.
     *
     * Bu \PDOStatement::fetch() eş değeridir.
     *
     * @return object|EntityInterface|array|null
     */
    public function row();

    /**
     * DB::get() ile yürütülmüş \PDOStatement için \PDOStatement::fetchColumn() sonucunu verir.
     *
     * @link https://www.php.net/manual/tr/pdostatement.fetchcolumn.php
     * @param int $column
     * @return mixed
     */
    public function column(int $column = 0);

    /**
     * Varsa SQL cümlesi içindeki bir parametreyi tanımlar/ekler.
     *
     * Belirtilen parametreler DB::get() yöntemi ile sorgu yürütülürken \PDO::execute() işlevine aktarılır.
     *
     * @param string $name
     * @param string|int $value
     * @return DBInterface
     */
    public function setParameter(string $name, $value): DBInterface;

    /**
     * Varsa SQL cümlesi içindeki parametreleri tanımlar. Bu tüm parametreleri tek seferde tanımlar yani kendisinden önce tanımlanmış parametreleri yok sayar. Tek tek tanımlama yapmak için DBInterface::setParameter() yöntemini kullanın.
     *
     * Belirtilen parametreler DB::get() yöntemi ile sorgu yürütülürken \PDO::execute() işlevine aktarılır.
     *
     * @param array $arguments
     * @return DBInterface
     */
    public function setParams(array $arguments): DBInterface;

    /**
     * QueryBuilder ile kurulmuş SQL cümlesini kurar, yürütür ve sınıfa yükler.
     *
     * @param string|null $table <p>Belirtilirse; QueryBuilder::from() işlevine gönderilir.</p>
     * @return \PDOStatement|false
     */
    public function get(?string $table = null);

    /**
     * Farklı bir \PDOStatement nesneli DB sınıfının örneğini verir.
     *
     * @param DBInterface|\PDOStatement $dbOrPDOStatement
     * @return DBInterface
     * @throws \InvalidArgumentException <p>$dbOrPDOStatement parametresi farklı bir veri tipinde ise.</p>
     */
    public function fromGet($dbOrPDOStatement): DBInterface;

    /**
     * QueryBuilder sıfırlanmadan SQL cümlesinin kurar, yürütür ve etkilelen satır sayısını döndürür.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Bir SQL sorgu cümlesini yürütür ve sonucu \PDOStatement nesnesi olarak döndürür.
     *
     * @used-by DBInterface::get()
     * @param string $sql <p>SQL Statement</p>
     * @param array|null $parameters <p>Varsa, PDO::execute() yöntemine gönderilecek parametre dizisi.</p>
     * @return \PDOStatement|false
     * @throws QueryExecuteException <p>SQL sorgusu yürütülürken \PDOException istisnası fırlatırlırsa.</p>
     */
    public function query(string $sql, ?array $parameters = null);


    /**
     * SQL sorgu cümlesini yürütür ve etkilenen satır sayısını döndürür.
     *
     * @used-by DBInterface::count()
     * @param string $sql <p>SQL Statement</p>
     * @return int
     * @throws QueryExecuteException <p>SQL sorgusu yürütülürken \PDOException istisnası fırlatırlırsa.</p>
     */
    public function exec(string $sql): int;

}