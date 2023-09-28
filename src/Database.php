<?php

namespace Scaliter;

use Scaliter\Request;

class Database
{

    public static function db_conn(array $db_conn, string $db_name = NULL)
    {
        $db_name = $db_name == NULL ? uniqid() . md5(mt_rand()) : md5($db_name);
        Request::$db[$db_name] = new \mysqli($db_conn['HOST'], $db_conn['USER'], $db_conn['PASS'], $db_conn['NAME']);
        return Request::$db[$db_name];
    }
    public static function connect()
    {
        // $_ENV['conn'] = new \mysqli(getSCons('DB_HOST'), getSCons('DB_USER'), getSCons('DB_PASS'), getSCons('DB_NAME'));
        $DB_HOST = Request::env("DB_HOST");
        $DB_USER = Request::env("DB_USER");
        $DB_PASS = Request::env("DB_PASS");
        $DB_NAME = Request::env("DB_NAME");

        // $DB_CONN = new \mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        // $DB_CONN->set_charset("utf8mb4");

        self::db_conn([
            'HOST' => $DB_HOST, 'USER' => $DB_USER, 'PASS' => $DB_PASS, 'NAME' => $DB_NAME
        ])->set_charset("utf8mb4");
    }
    public static function disconnect()
    {
        foreach (Request::$db as $db)
            $db->close();
    }

    private object $conn;
    private $where, $limit, $table, $order = null;

    public static function table(string $table, string $db_name = NULL): self
    {
        $db_name = $db_name == NULL ? array_key_first(Request::$db) : md5($db_name);
        return new self($table, $db_name);
    }
    public function dump()
    {
        var_dump($this);
        die;
    }
    public function __construct($table = null, string $db_name = NULL)
    {
        $this->table = $table;
        $this->conn = Request::$db[$db_name];
    }
    public function escape($s)
    {
        if ($s == null) return null;
        return $this->conn->real_escape_string($s);
    }
    public static function query($query)
    {
        $query = trim($query);
        try {
            return Request::$server['DB_CONN']->query($query);
        } catch (\Exception $e) {
            // Response::error('Request failed');
            Response::error($e->getMessage(), ['query' => $query]);
            // die($e->getMessage() . "<br><i>[$query]</i>");
        }
    }
    private function __query(string $query)
    {
        $query = trim($query);

        try {
            $result = $this->conn->query($query);
        } catch (\Exception $e) {
            // Response::error('Request failed');
            // Response::error($e->getMessage(), ['query' => $query]);
            die($e->getMessage() . "<br><i>[$query]</i>");
        }

        switch ($query):
            case str_starts_with($query, 'INSERT INTO'):
                return (int) $this->conn->insert_id;
            case str_starts_with($query, 'UPDATE'):
                return (int) $this->conn->affected_rows;
            default:
                return $result;
        endswitch;
    }
    private function __where()
    {
        return empty($this->where) || $this->where == null ? '' : 'WHERE ' . $this->where;
    }
    private function __limit()
    {
        return empty($this->limit) || $this->limit == null ? '' : ' LIMIT ' . $this->limit[0] . ', ' . $this->limit[1];
    }
    private function __order()
    {
        return empty($this->order) || $this->order == null ? '' : ' ORDER BY ' . $this->order;
    }
    private function __select($query)
    {
        return $this->__query($query)->fetch_all(MYSQLI_ASSOC);
    }
    public function limit(int $results, int $page = 1)
    {
        $this->limit = [(($page <= 0 ? 1 : $page) - 1) * $results, $results];
        return $this;
    }
    public function order(string $by, string $order = 'ASC')
    {
        $this->order = (empty($this->order) ? '' : $this->order . ', ') . $by . ' ' . $order;
        return $this;
    }
    public function where(array $where, string $symbol = '=', bool $quotes = true, bool $brackets = false, string $indicator = 'AND', string $pre_indicator = 'AND')
    {
        $this->where = empty($this->where) ? '' : "$this->where $pre_indicator ";
        $__make = '';
        foreach ($where as $key => $val)
            $__make .= $quotes ? "$key $symbol '$val' $indicator " : "$key $symbol $val $indicator ";
        $__make = rtrim($__make, " $indicator ");
        $this->where .= $brackets ? "($__make)" : $__make;
        return $this;
    }
    public function insert(array $insert)
    {
        $keys = $vals = '';
        foreach ($insert as $key => $val) {
            $val = $this->escape($val);
            $keys .= "$key, ";
            $vals .= "'$val', ";
        }
        $keys = rtrim($keys, ', ');
        $vals = rtrim($vals, ', ');
        return $this->__query("INSERT INTO $this->table ($keys) VALUES ($vals)");
    }
    public function update(array $update, bool $quotes = true, bool $value = false)
    {
        $sql = '';
        foreach ($update as $key => $val) {
            $val = $this->escape($val);
            if ($value)
                $sql .= $val >= 0 ? "$key = $key + $val, " : "$key = $key - " . abs($val) . ", ";
            elseif ($quotes)
                $sql .= "$key = '$val', ";
            else
                $sql .= "$key = $val, ";
        }
        $sql = rtrim($sql, ', ');
        return $this->__query("UPDATE $this->table SET $sql " . $this->__where() . $this->__order() . $this->__limit());
    }
    public function select(string|array $select = '*')
    {
        $select = is_array($select) && count($select) ? implode(', ', $select) : $select;
        return $this->__query("SELECT $select FROM $this->table " . $this->__where() . $this->__order() . $this->__limit())->fetch_all(MYSQLI_ASSOC);
    }
    public function fetch(string|array $fetch = '*')
    {
        $result = $this->__query('SELECT ' . (is_array($fetch) ? implode(', ', $fetch) : $fetch) . ' FROM ' . $this->table . ' ' . $this->__where() . $this->__order() . ' LIMIT 1')->fetch_array(MYSQLI_ASSOC);
        return is_array($result) ? $result : [];
    }
    public function get(string $get, $default = 0)
    {
        return $this->__query("SELECT $get FROM $this->table " . $this->__where() . $this->__order() . " LIMIT 1")->fetch_object()->{$get} ?? $default;
    }
    public function count(string $count = 'id')
    {
        return $this->get("count($count)");
    }
}
