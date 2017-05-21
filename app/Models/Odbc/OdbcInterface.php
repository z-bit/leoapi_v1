<?php namespace App\Models\Odbc;

interface OdbcInterface {

    public function select($fields=['*'], $where=null);

    public function paginate($fields=['*'], $where=null, $orderBy, $offset=0, $limit=15);

    public function insert(array $fields, array $values);

    public function update(array $fields, array $values, $where);

    public function delete($where);

    public function open($sql);

    public function exec($sql);
}