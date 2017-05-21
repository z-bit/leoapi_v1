<?php
/**
 * 
 */

class Care
{
    private $hcon = [];         // 1 connection handle pro Firma
    private $hres = null;       // result handle for openQuery
    private static $instance;   // singleton

    private function __construct()
    {
        $pref   = Preferences::getInstance();
        $mode   = $pref->getProperty('mode');
        $connect = $pref->getProperty('connect');
        unset($pref);

        $str = "DRIVER={iSeries Access ODBC Driver}; ";
        $str.= "SYSTEM=53.101.245.6; PORT=446; PROTOCOL=TCPIP; DBQ=" ;
        $libl = 'RZE';
        $cStr = $str . $libl . ';';
        $hcon = odbc_connect($cStr, $mode, $connect);
        if (!$hcon) throw new Exception(
            "Model Care: Fehler bei Verbindung zur Datenbank Fa=RZE, Db=RZE."
        );
        $sql = "select distinct fa, db from rze.firma";
        $res = odbc_exec($hcon, $sql);
        throw new Exception('res-count'.count($res));
        for ($i=0; $i<count($res); $i++) {
            $fa = $res[$i][0];
            $db = $res[$i][1];
            $libl  = 'REPDBF'.$db . 'RDCDBF'.$db . 'REPCUS'.$db;
            $libl .= ' REPDC RDCPGM RDCDBF REPPGM RDCTRAN';
            $cStr = $str . $libl . ';';
            $this->hcon[$fa] = odbc_connect($cStr, $mode, $connect);
            if (!$this->hcon[$fa]) throw new Exception(
                "Model Care: Fehler bei Verbindung zur Datenbank Fa=$fa, Db=REPDBF$db."
            );
        }
        $this->hcon['RZE'] = $hcon;
    }

    public static function getInstance()
    {
        if (empty(self::$instance))
        {
            self::$instance = new Care();
        }
        return self::$instance;
    }

    public function execQuery($fa, $sql)
    {
        $this->hres = odbc_exec($this->hcon[$fa], $sql);
        if (odbc_error()){
            $err =odbc_errormsg($this->hcon[$fa]);
            throw new exception("
                Model Class Care->execQuery() Fehler
                <br> Firma: $fa
                <br> Meldung:<br> 	$err
                <br> bei Query:<br> $sql
            ");
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

        $empty = array_pop($val); //letzter Satz immer leer!
        if (count($empty)>0) throw new Exception(
            'MySql - letzter Satz nicht leer: ' . $empty
        );

        return $val;
    }
} 