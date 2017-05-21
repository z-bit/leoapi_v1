<?php namespace App\Models\MySql;

use Illuminate\Database\Connection;

class Catlog
{
    public $log = [
        //  'Zeit' => '',       // timestamp wird automatisch eingetragen
        'Art' => '',        // c(12): WARNUNG, ERFOLG, FEHLER, TEST, KATASTROPHE
        'Meldung' => '',    // c(100)
        'Umgebung' => ''    // c(200) serialisiertes JSON, noch genauer zu bestimmen
    ];

    public function __construct()
    {
        $this->db = app('db');
        date_default_timezone_set('Europe/Berlin');
    }

    public function log($art, $meldung, $source='', $code='')
    {
        $u['test'] = 'ja';  // damit auf jeden Fall was drin steht.
        if ($source) $u['source'] = $source;
        if ($code) $u['code'] = $code;
        $sql =  "INSERT INO catlog (Art, Meldung, Umgebung) VALUES ('$art', '$meldung', '$umgebung')";
        $resIns = $this->db->select($sql);

        return true;
    }
}