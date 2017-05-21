<?php namespace App\Models\Odbc;

use App\Models\Odbc\OdbcInterface;
use App\Models\Odbc\OdbcConnection;
use League\Flysystem\Exception;


abstract class OdbcModel implements OdbcInterface
{
    protected $table;
    protected $fa;
    protected $columnTypes;
    protected $id;
    var $cols;

    protected function checkCount(array $fields, array $values) {
        if (count($fields) != count($values)) {
            throw new Exception('OdbcModel::checkCount(): $fields <> $values');
        }
    }

    protected function checkStrings(array $fields, array $values)
    {
        //print('checkStrings: ');
        //print_r($values);
        if (!$this->columnTypes) {
            $tab = explode('.', $this->table);
            $s = $tab[0];
            $t = $tab[1];
            $sql =  " select COLUMN_NAME, TYPE_NAME from SYSIBM.SQLCOLUMNS" .
                " where TABLE_SCHEM = '$s' AND TABLE_NAME = '$t'";

            $odbc = OdbcConnection::getInstance();
            $res = $odbc->namedQuery($this->fa, $sql);
            for ($i=0; $i<count($res); $i++){
                $s  = $res[$i]['COLUMN_NAME'];
                $t = $res[$i]['TYPE_NAME'];
                $this->columnTypes[$s] = $t;
            }
            /* DATA_TYPE    TYPE_NAME
             *         1	CHAR
             *         3	DECIMAL
             *         4    INTEGER
             *        91	DATE
             *        92	TIME
             *        93    TIMESTAMP
             */
        }
        for ($i=0; $i < count($fields); $i++) {
            $t = trim($this->columnTypes[strtoupper($fields[$i])]);
            //print("$fields[$i]  |$t|  $values[$i] ");
            if ( ($t == 'CHAR' or $t == 'DATE' or $t == 'TIME' or $t == 'TIMESTAMP')
                 and (strpos($values[$i], "'") === false)
            ) {
                $values[$i] = "'$values[$i]'";
                //print("$values[$i]<br>");
            }
        }
        //print_r($values);
        return $values;
    }

    private function columnString($columns) {
        $cs = ' ';
        foreach($columns as $col) {
            $cs .= $col;
            $cs .= ',';
        }
        $sql = rtrim($cs, ','); //das Komma am Ende entfernen

        return $cs;
    }

    public function select($columns=['*'], $where=null) {
        $sql  = "select ". implode(',', $columns);
        $sql .= " from " . $this->table;

        if ($where) $sql .= " where $where";

        $odbc = OdbcConnection::getInstance();
        return $odbc->namedQuery($this->fa, $sql);
    }

    public function paginate($columns=['*'], $where=null, $orderBy, $offset=0, $limit=15) {
        $sql  = "select RN," . implode(',', $columns) . " from (";
        $sql .=     "select row_number over (order by $orderBy) as RN," . implode(',', $columns);
        $sql .=     " from " . $this->table;
        if($where){
            $sql .= " where $where";
        }
        $sql .= ") as t";
        $sql .= "where t.RN between $offset and $offset+$limit";

        $odbc = OdbcConnection::getInstance();
        return $odbc->namedQuery($this->fa, $sql);
    }
    /*
    $sql =  "select RN, FABRIKAT, TEILENUMMER, BEZEICHNUNG".
            " from (".
            "   select row_number() over (order by FABRIKAT, TEILENUMMER) as RN, FABRIKAT, TEILENUMMER, BEZEICHNUNG".
            "   from repdbfsc.rzeteil2 ".
            "   where 1=1 ".
            "   and upper(TEILENUMMER) like upper('$tnr%') ".
            "   and upper(KONTIERUNG) like upper('$kont%') ".
            "   and (LG201 <> 0 or LG202 <> 0 or LG203 <> 0 or LG204 <> 0 or LG205 <> 0) ".
            " ) as t ".
            " where t.RN between $offset and $offset+$limit ";
    */

    public function insert(array $fields, array $values) {
        $this->checkCount($fields, $values);
        $values = $this->checkStrings($fields, $values);
        $sql  = "insert into " . $this->table;
        $sql .= " (" . implode(',', $fields) . ")";
        $sql .= " values (" . implode(',', $values) . ")";

        $odbc = OdbcConnection::getInstance();
        $odbc->execQuery($this->fa, $sql);
//        print($sql."<br><br>");
        return true;
    }

    public function update(array $fields, array $values, $where) {
        $this->checkCount($fields, $values);
        $values = $this->checkStrings($fields, $values);
        $sql  = "update " . $this->table . " set ";
        for ($i=0; $i < count($fields); $i++) {
            $sql .= $fields[$i] . "=" . $values[$i].",";
        }
        $sql = rtrim($sql, ",");
        $sql .= " where $where";

        $odbc = OdbcConnection::getInstance();
        $odbc->execQuery($this->fa, $sql);
//       print($sql."<br><br>");
        return true;
    }

    public function delete($where) {
        $sql = "delete from {$this->table} where $where";

        $odbc = OdbcConnection::getInstance();
        $odbc->execQuery($this->fa, $sql);
        return true;
    }

    public function open($sql) {
        $odbc = OdbcConnection::getInstance();
        return $odbc->namedQuery($this->fa, $sql);
    }

    public function exec($sql) {
        $odbc = OdbcConnection::getInstance();
        $odbc->execQuery($this->fa, $sql);
        return true;
    }

    //=================
    //Special Functions
    //=================

    private function getWhereFromId(){
        $id = $this->id;
        $where = '';
        for($i=0; $i<count($id); $i++){
            $sp = $id[$i];//"'".$id[$i]."'";
            if(!isset($this->cols[$sp]))  throw new Exception(
                "OdbcModel - getWhereFromId() - Id-Spalte ".$sp." nicht vorhanden."
            );
            if(!$this->cols[$sp]) throw new Exception(
                'OdbcModel - getWhereFromId() - Id-Spalte '.$sp.' hat keinen Wert.'
            );
            $where .= $id[$i] . "='" . $this->cols[$sp] . "' and ";
        }
        $where = substr($where, 0, -5);
        return $where;
    }

    public function resetCols(){
        if(!isset($this->cols)){
            throw new Exception(
                'OdbcModel - resetCols() - keine Spalten definiert.'
            );
        }
        foreach($this->cols as $key => $val){
            $this->cols[$key] = null;
        }
        if(isset($this->cols['fa'])) $this->cols['fa'] = $this->fa;
    }

    public function readCols(){
        $r = $this->select(['*'], $this->getWhereFromId());
        if (count($r) == 0){
            foreach($this->cols as $key => $val){
                if (!in_array($key, $this->id)) $this->cols[$key] = null;
            }
        } else {
            $r = $r[0];
            foreach ($r as $key => $val) {
                $this->cols[strtolower($key)] = $val;
            }
        }
        return true;
    }

    public function writeCols(){
        $p = $this->cols;
        $f = []; //fields
        $v = []; //value

        foreach($p as $key => $val){
            if($val){
                $f[] = $key;
                $v[] = $val;
            }
        }
        $r = $this->select(['count(*) as count'], $this->getWhereFromId());
        if($r[0]['COUNT'] == 0){
            //insert
            $this->insert($f, $v);
        }else{
            //update
            $this->update($f, $v, $this->getWhereFromId());
        }
    }
}