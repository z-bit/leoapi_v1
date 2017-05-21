<?php namespace App\Models\Kunden;
use App\Models\Odbc\OdbcConnection;

class Sonderkonditionen {
    private $db;
    private $t1;
    private $heute;
    private $matrix;

    public function __construct($fa, $knr)
    {
        $this->t1 = microtime(true); //Startzeit
        $this->db = OdbcConnection::getInstance();
        $this->heute = intval(date('1ymd'));
        $this->matrix = $this->getSokoMatrix($fa, $knr);
    }

    /**
    getSokoMatrix(fa, knr): [i][art, fab, nr, rabatt, sonderPreis]
    GQH5ST                     GQFBCD      GQQFCD      GQFLSU          GQRABT      GQIEST      GQH2VA
    Art                        Fab         Nr          Preisstufe      Rabatt%     immer ''    sonderPreis
    $m[0]                      $m[1]       $m[2]       ausgelassen     $m[3]       ausgelassen $m[4]
    ====================================================================================================
    1: Teile-Nr                FAB         TNR         1 oder '' (1)   15.00       oder        12.50
    2: Teilegruppe             FAB (2)     TGR                         15.00
    3: Rabattcode-Verkauf                  RcVk(MX02)  1 oder '' (1)   15.00
    4: Teile Fi/AArt/Fab/      -/- kommt nicht vor

    6: Arbeitsop. Festpreis    Fab / *     aoNr                                                12.50 (3)
    7: Arbeit Fi/AArt/Fab/VAr  -/- kommt nicht vor
    8: Arbeitsoperation        Fab / *     aoNr        ''             100.00       oder        12.50 (3)
    9: Verrechnungsart         -/-         aoVrcd                                               5.50 (4)
    ====================================================================================================
     * FAB = immer korrekt eingetragen
     * (1) meist, manchmal 1, in Care nicht eintragbar, wenn Rabat%, dann immer '' => Preisstufe aus KD-Stamm
     * (2) nur genutzt für BOD, C, V
     * (3) Arbeitspreis unabhängig von AW-Anzahl
     * (4) Preis pro AW oder Stunde
     * @param $fa
     * @param $knr
     * @return array $sokoMatrix
     */
    private function getSokoMatrix($fa, $knr) {
        $sql =  " SELECT gqh5st, gqfbcd, gqqfcd, gqrabt, gqh2va FROM rqgqrep ".
                " WHERE gqfacd='$fa' AND gqkdcd='$knr' AND gqb7dt<=$this->heute AND gqb8dt>=$this->heute";
        $res = $this->db->openQuery($fa, $sql);
        $sokoMatrix = [];
        if (count($res) == 0){
            return $sokoMatrix;
        }
        foreach($res as $m) {
            $r['art'] = $m[0];
            $r['fab'] = $m[1];
            $r['nr'] = $m[2];
            $r['rabatt'] = $m[3];
            $r['sonderPreis'] = $m[4];
            array_push($sokoMatrix, $r);
        }
        return $sokoMatrix;
    }

    /**
     * checkTeil($fab, $tnr, $tgr, $rcVk): [teileRabatt, teileSonderpreis, teilegruppenRabatt, teilemarginRabatt] || []
     * @param $fab
     * @param $tnr
     * @param $tgr
     * @param $rcVk
     * @return mixed [teileRabatt, teileSonderpreis, teilegruppenRabatt, teilemarginRabatt] || []
     */
    public function checkTeil($fab, $tnr, $tgr, $rcVk) {
        $r = [];
        if (count($this->matrix) == 0) return $r;

        foreach($this->matrix as $m) {
            if ($m['art'] == '1') {
                if ($m['fab'] == $fab && $m['nr'] == $tnr) {
                    if ($m['rabatt'] >0) {
                        $r['teileRabatt'] = $m['rabatt'];
                    } else {
                        $r['teileSonderpreis'] = $m['sonderPreis'];
                    }
                }
            }
            if ($m['art'] == '2') {
                if ($m['fab]'] == $fab && $m['nr'] == $tgr) {
                    $r['teilegruppenRabatt'] = $m['rabatt'];
                }
            }
            if ($m['art'] == '3') {
                if ($m['nr'] == $rcVk) {
                    $r['teilemarginRabatt'] = $m['rabatt']; // Rabatt auf Verkaufsgewinn (Margin)
                }
            }
        }
        return $r;
    }

    /**
     * checkArbeit(fab, aoNr, aoVrcd): [aoFestpreis, aoRabatt, aoSonderpreis, awSonderpreis] || []
     * @param $fab
     * @param $aoNr
     * @param $aoVrcd
     * @return mixed [aoFestpreis, aoRabatt, aoSonderpreis, awSonderpreis]
     */
    public function checkArbeit($fab, $aoNr, $aoVrcd) {
        $r = [];
        if (count($this->matrix) == 0) return $r;

        foreach($this->matrix as $m) {
            if ($m['art'] == '6') {
                if ($m['fab'] == $fab || $m['fab'] == '*') {
                    if ($m['nr'] == $aoNr) {
                        $r['aoFestpreis'] = $m['sonderPreis'];
                    }
                }
            }
            if ($m['art'] == '8') {
                if ($m['fab'] == $fab || $m['fab'] == '*') {
                    if ($m['nr'] == $aoNr) {
                        if ($m['rabatt'] > 0) {
                            $r['aoRabatt'] = $m['rabatt'];
                        } else {
                            $r['aoSonderpreis'] = $m['sonderPreis'];
                        }
                    }
                }
            }
            if ($m['art'] == 9) {
                if ($m['nr'] == $aoVrcd) {
                    $r['awSonderpreis'] = $m['sonderPreis'];
                }
            }
        }
        return $r;
    }

}