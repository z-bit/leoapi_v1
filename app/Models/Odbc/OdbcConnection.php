<?php namespace App\Models\Odbc;

use App\Models\Odbc\Preferences;
use App\Models\Odbc\OdbcError;


class OdbcConnection
{
    private $hcon = [];         // 1 connection handle pro Firma
    private $hres = null;       // result handle for openQuery
    private static $instance;   // singleton

    private function __construct()
    {
        $pref   = Preferences::getInstance();
        $mode   = $pref->getProperty('mode');
        $connect = $pref->getProperty('connect');
        $odbc = $pref->getProperty('odbc');
        unset($pref);

        $libl = ' DBQ=RZE';
        $cStr = $odbc . $libl . ';';
        $hcon = odbc_connect($cStr, $mode, $connect);
        //if (!$hcon) throw new Exception(
        if (!$hcon) abort(500,    
            "Model Care: Fehler bei Verbindung zur Datenbank Fa=RZ, Db=RZE."
        );
       
        $this->hcon['RZ'] = $hcon;  // connection handle mit RZE als Bibliotheksliste
        
        // connection handles für die anderen Firmen:
        $sql = "select distinct fa, db from rze.firma";
        $res = odbc_exec($this->hcon['RZ'], $sql);
        while($r = odbc_fetch_array($res)){
            //print_r($r);
            $fa = $r['FA'];
            $db = trim($r['DB']);
            $libl  = ' DBQ=REPDBF'.$db . ' RDCDBF'.$db . ' REPCUS'.$db;
            $libl .= ' REPDC RDCPGM RDCDBF REPPGM RDCTRAN;';
            $cStr = $odbc . $libl . ';';
            $this->hcon[$fa] = odbc_connect($cStr, $mode, $connect);
            //if (!$this->hcon[$fa]) throw new Exception(
            if (!$this->hcon[$fa]) {
                $err = "Model OdbcConnection: Fehler bei Verbindung zur Datenbank Fa=$fa, Db=REPDBF$db.";
                $error['error'] = $err;
                return $error;
            }    
        }
    }

    public static function getInstance()
    {
        if (empty(self::$instance))
        {
            self::$instance = new OdbcConnection();
        }
        return self::$instance;
    }

    public function execQuery($fa, $sql)
    {
        try {
            $this->hres = odbc_exec($this->hcon[$fa], $sql);    
        } catch(Exception $e) {
            print($e->getLine());
            print($e->getFile());
            print($e->getMessge());
            die;
        }

        return true;
    }

    public function openQuery($fa, $sql)
    {
        $this->execQuery($fa, $sql);

        $val = [];
        $i=0;
        while (odbc_fetch_into($this->hres, $val[$i])) $i++;

        odbc_free_result($this->hres);

        $empty = array_pop($val); 
        //letzter Satz immer leer!
        if (count($empty)>0) {
            $error['error'] = 'MySql - letzter Satz nicht leer: ' . $empty;
            return $error;
        }    
        return $val;
    }

    public function namedQuery($fa, $sql) {
        $this->execQuery($fa, $sql);
        $res = [];
        while ($val = odbc_fetch_array($this->hres)){
            array_push($res, $val);
        };
        return $res;
    }
}