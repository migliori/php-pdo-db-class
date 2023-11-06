<?php

declare(strict_types=1);

use database\DB;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
    protected $db;

    protected function setUp(): void
    {
        require_once 'src/database/db-connect.php';
        require_once 'src/database/DB.php';
        $this->db = new DB(false, PDO_DRIVER, DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
    }

    protected function tearDown(): void
    {
        $this->db = null;
    }

    public function testConnection()
    {
        $this->assertInstanceOf(PDO::class, $this->db->getPdo());
    }

    public function testExcecuteSqlInsert()
    {
        $sql = "INSERT INTO productlines (id, name, description) VALUES (null, 'bikes', 'Lorem ipsum')";
        $result = $this->db->execute($sql);
        $this->assertIsNumeric($result);

        return $this->db;
    }

    #[Depends('testExcecuteSqlInsert')]
    public function testExcecuteSqlDeleteWithPlaceholders(\database\DB $db)
    {
        $sql = 'DELETE FROM productlines WHERE name = :name AND description = :description';
        $values = array('name' => 'bikes', 'description' => 'Lorem ipsum');

        $result = $db->execute($sql, $values);
        $this->assertTrue($result);
    }

    public function testQuery()
    {
        $sql = "SELECT * FROM customers WHERE country = 'Indonesia'";
        $result = $this->db->query($sql);
        $this->assertTrue($result);
    }

    public function testQueryDebug()
    {
        $sql = "SELECT * FROM customers WHERE country = 'Indonesia'";

        $this->expectOutputRegex('`<div class="db-dump-debug"(.*)+`');

        $result = $this->db->query($sql, array(), true);
        $this->assertTrue($result);
    }

    public function testQueryWithPlaceholders()
    {
        $sql = "SELECT id, first_name, last_name FROM customers WHERE country = :country";
        $values = array('country' => 'Indonesia');
        $result = $this->db->query($sql, $values);
        $this->assertTrue($result);
    }

    public function testQueryRow()
    {
        $sql = 'SELECT first_name, last_name, email FROM customers WHERE id = :id LIMIT 1';
        $result = $this->db->queryRow($sql, array('id' => 5));
        $this->assertIsObject($result);
        $this->assertIsString($result->last_name);
        $this->assertIsString($result->first_name);
        $this->assertIsString($result->email);
    }

    public function testQueryValue()
    {
        $sql = 'SELECT last_name FROM customers WHERE id = 10 LIMIT 1';
        $result = $this->db->queryValue($sql);
        $this->assertIsString($result);
    }

    #[Depends('testSelect')]
    public function testFetch(\database\DB $db)
    {
        $row = $db->fetch();
        $this->assertIsObject($row);
        $this->assertObjectHasProperty('id', $row);
        $this->assertObjectHasProperty('first_name', $row);
        $this->assertObjectHasProperty('last_name', $row);
    }

    #[Depends('testSelect')]
    public function testFetchAll(\database\DB $db)
    {
        $rows = $db->fetchAll();
        $this->assertIsArray($rows);
    }

    public function testSelect()
    {
        $values = array('id', 'first_name', 'last_name');
        $where = array('country' => 'Indonesia');
        $result = $this->db->select('customers', $values, $where);
        $this->assertTrue($result);

        return $this->db;
    }

    public function testSelectWithComplexWhere()
    {
        $values = array('id', 'first_name', 'last_name');
        $where = array(
            'zip_code IS NOT NULL',
            'id >' => 10,
            'last_name LIKE' => '%Ge%'
        );
        $result = $this->db->select('customers', $values, $where);
        $this->assertTrue($result);
    }

    public function testSelectSorting()
    {
        $values = array('id', 'first_name', 'last_name');
        $where = array('country' => 'Indonesia');
        $extras = array('order_by' => 'id DESC');
        $result = $this->db->select('customers', $values, $where, $extras);
        $this->assertTrue($result);
    }

    public function testSelectRow()
    {
        $result = $this->db->selectRow('customers', '*', array('id' => 12));
        $this->assertIsObject($result);
    }

    public function testSelectCount()
    {
        $result = $this->db->selectCount('customers');
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('rows_count', $result);
    }

    public function testSelectValue()
    {
        $result = $this->db->selectValue('customers', 'email', array('id' => 32));
        $this->assertIsString($result);
    }

    public function testInsert()
    {
        $data = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'phone' => '081234567890',
            'address' => '123 Main Street',
            'city' => 'San Francisco',
            'country' => 'CA'
        );

        $result = $this->db->insert('customers', $data);
        $this->assertIsNumeric($result);
    }

    public function testDelete()
    {
        $where = array(
            'id' => 3,
        );

        $this->expectOutputRegex('`<div class="db-dump-debug"(.*)+`');

        $result = $this->db->delete('payments', $where, true);
        $this->assertTrue($result);
    }

    public function testGetPdo()
    {
        $pdo = $this->db->getPdo();
        $this->assertInstanceOf('PDO', $pdo);
    }

    public function testGetPdoDriver()
    {
        $driver = $this->db->getPdoDriver();
        $this->assertContains($driver, array('mysql', 'pgsql', 'oci', 'firebird'));
    }

    public function testGetTables()
    {
        $tables = $this->db->getTables();
        $this->assertIsArray($tables);
        $this->assertContains('customers', $tables);
    }

    public function testGetColumns()
    {
        $columns = $this->db->getColumns('customers');
        $this->assertIsArray($columns);
    }

    public function testGetColumnsNames()
    {
        $columns = $this->db->getColumnsNames('customers');
        $this->assertIsArray($columns);
    }

    public function testInTransaction()
    {
        $this->assertFalse($this->db->inTransaction());

        $this->db->transactionBegin();
        $this->assertTrue($this->db->inTransaction());

        $this->db->transactionCommit();
        $this->assertFalse($this->db->inTransaction());
    }

    public function testTransactionBegin()
    {
        $this->assertFalse($this->db->inTransaction());

        $result = $this->db->transactionBegin();
        $this->assertTrue($result);
        $this->assertTrue($this->db->inTransaction());
    }

    public function testTransactionCommit()
    {
        $this->assertFalse($this->db->inTransaction());

        $this->db->transactionBegin();
        $result = $this->db->transactionCommit();
        $this->assertTrue($result);
        $this->assertFalse($this->db->inTransaction());
    }

    public function testConvertQueryToSimpleArray()
    {
        $array = array(
            array('id' => 1, 'name' => 'John'),
            array('id' => 2, 'name' => 'Jane'),
        );

        $result = $this->db->convertQueryToSimpleArray($array, 'name');
        $this->assertEquals(array('John', 'Jane'), $result);

        $result = $this->db->convertQueryToSimpleArray($array, 'name', 'id');
        $this->assertEquals(array(1 => 'John', 2 => 'Jane'), $result);
    }
}
