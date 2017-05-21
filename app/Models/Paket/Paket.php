<?php namespace App\Models\Paket;
use App\Models\Odbc\OdbcConnection;
use App\Models\MySql\Catlog;
use App\Models\Teil\Teil;

class Paket {
    private $t1;        // Startzeit

    private $db;
    private $catlog;
    private $tInner;    // (inner) trennt pos-nr und Menge innerhalb der Positionen
    private $tOuter;    // (outer) trennt Positionen voneinander
    private $today;     // heutiges Datum in iSeries Notation
    //todo look for better way of setting $rfi, $knrP, $knrK from outside
    private $rfi;       // Referenz-Filiale für ASC-Paket-Daten, z.Z. $fil='05'
    private $knrP;      // Kundennummer für unbekannt, Passant (P)
    Private $knrK;      // Kundennummer für unbekannt, Kunde (K)

    public function __construct()
    {
        $this->t1 = microtime(true); //Startzeit
        $this->db = OdbcConnection::getInstance();
        $this->catlog = new Catlog();
        $this->i = chr(1); //nicht tippbare Zeichen
        $this->o = chr(2);
        $this->today = intval(date('1ymd'));
        $this->rfi = '05';
        $this->knrP = 'P';
        $this->knrK = 'K';
    }

    private function done($r)
    {
        $r['time_inner'] = sprintf("%d ms", round((microtime(true) - $this->t1) * 1000));
        return ($r);
    }


    /**
     * listPakete(fa, fin): [opnr, cpnr, pakBez, sppsPreis]
     * Listet gemerkte Pakete für ein bestimmtes Fahrzeug aus rze.cat_pakmrk.
     * Dabei wird gleichzeitig geprüft, ob diese gemerkten Pakete noch aktuell sind.
     * @param $fa
     * @param $fin
     * @return array pak = [opnr, cpnr, pakBez, sppsPreis]
     */
    public function listPakete($fa, $fin)
    {
        $r['time_setup'] = sprintf("%d ms", round((microtime(true) - $this->t1) * 1000));
        $baum = substr($fin, 4, 6);
        $fi = $this->rfi;
        $sql = "SELECT opnr, cpnr, pakbez, spppreis, datum FROM rze.cat_pakfin WHERE fa='$fa' fin='$fin'";
        $res = $this->db->openQuery($fa, $sql);
        $r = [];
        $i = 0;
        if (count($res) > 0) {
            foreach ($res as $val) {
                $opnr = $val[0];
                $cpnr = $val[1];
                $pakBez = $val[2];
                $sppsPreis = $val[3];
                $datum = $val[4];

                // checken, ob es für dieses Paket ein Update gegeben hat: import(PKOPF) > datum(PALSEL)
                $sql =  " SELECT bezeich, preis, import FROM rdcpkopf ".
                        " WHERE ficd='$fi' pknr='$opnr' AND baumust='$baum'";
                $res1 = $this->db->openQuery($fa, $sql);
                if (count($res1) == 0) {
                    // theoretischer Fehler: loggen und übergehen
                    $this->catlog->log('Fehler', "Paket $fa/$baum/$opnr nicht gefunden in PKOPF", "App/Models/Paket/list($fa, $fin)");
                    continue;
                }
                $newBez = $res[0][0];
                $newPreis = $res[0][1];
                $import = $res1[0][2];
                if ($import > $datum) {
                    $c = $this->getCpnr($fa, $baum, $opnr);
                    if ($c['cpnr']) {
                        $newCpnr = $c['cpnr'];
                    } else {
                        $this->catlog->log('Fehler', $c['error'], "App/Models/Paket/listPaket($fa, $fin)", $c['code']);
                        return $c;
                    }

                    if ($cpnr <> $newCpnr) {
                        // update CAT_PAKFIN
                        $sql = " UPDATE rze.cat_pakfin SET " .
                            " cpnr='$newCpnr', pakbez='$newBez', sppspreis='$newPreis', datum=$this->today".
                            " WHERE fa='$fa' AND fin='$fin' AND opnr='$opnr'";
                        $this->db->execQuery($fa, $sql);
                        // update CAT_PAKSEL
                        $sql = " UPDATE rze.cat_paksel SET " .
                            " cpnr='$newCpnr', pakbez='$newBez', sppspreis='$newPreis', datum=$this->today".
                            " WHERE fa='$fa' AND fin='$fin' AND opnr='$opnr'";
                        $this->db->execQuery($fa, $sql);
                        $cpnr = $newCpnr;
                        $sppsPreis = $newPreis;
                    } else {
                        // nur das Datum updaten
                        $sql =  "UPDATE rze.cat_pakfin SET datum=$this->today".
                            " WHERE fa='$fa' fin='$fin' AND opnr='$opnr' AND cpnr='$cpnr'";
                        $this->db->execQuery($fa, $sql);
                    }

                }
                $r[$i]['opnr'] = $opnr;
                $r[$i]['cpnr'] = $cpnr;
                $r[$i]['pakBez'] = $pakBez;
                $r[$i]['sppsPreis'] = $sppsPreis;
                $i++;
            }
        }
        return $this->done($r);
    }

    /**
     * get(fa, fi, fzg, $opnr, $knr): pakBez, sppsPreis, pos = [art(A/T), nr, anz, bestFi, bestFa, carePreis, kndPreis]
     * fzg = fin oder baum
     */
    public function get($fa, $fi, $fzg, $opnr, $knr) {
        if (strlen($fzg) == 6) {
            $baum = $fzg;
            $fin = '';
        } else {
            $baum = substr($fzg, 3, 6);
            $fin = $fzg;
        }
        $r = $this->getCpnr($fa, $baum, $opnr);
        // = $r['cpnr'], $r['pakBez'], $r['sppsPreis']
        if ($r['error']) {
            $this->catlog->log('SEVERE', $r['error'], "App/Models/Paket/get($fa, $fi, $fzg, $opnr, $knr)", $r['code']);
            return $r;
        }

        // get Positionen
        $r['pos'] = this->getPositionen($fa, $fi, $r['cpnr'], $knr);
        // return Paket


    }

    /**
     * getPositionen(fa, fi, cpnr, knr): [i][art, nr, anz, bestFi, bestFa, carePreis, kundPreis]
     * @param $cpnr
     * @return array [i][art, nr, anz, bestFi, bestFa, carePreis, kundPreis]
     */
    public function getPositionen($fa, $fi, $cpnr, $knr) {
        $sql = "SELECT pos FROM rze.cat_pakpos where fa='$fa' AND cpnr='$cpnr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) ==0){
            // nur theoretisch
            $positionen = [];
            return $positionen;
        }
        $posString = $res[0][0];
        $positionen = explode($this->tOuter, $posString);
        $fab = array_shift($positionen); // erstes Element rausschnipsen
        foreach($positionen as $pos) {
            $a = explode($this->tInner, $pos);
            $nr = $a[0];
            $anz = $a[1];
            $art = strlen($anz) == 3 ? 'A' : 'T';
            if ($art = 'A'){
                $anz = intval($anz);
                $res = $arbeit
            } else {
                $anz = sprintf('%6.3f', $anz/1000);
                $teil = new Teil();
                $res = $teil->get($fa, $fi, $fab, $nr, $anz, $knr);
                //[i][fab, tnr, posBez, carePreis, kundPreis, bestFi, bestFa, datum, anz]
            }

        }

    }


    /**
     * getCpnr(fa, baum, opnr): $r['cpnr'], $r['pakBez'], $r['sppsPreis'] || $r['error'], $r['code']
     * findet CPNR
     * Gibt es keine zugehörige CPNR, wird sie erstellt.
     */
    private function getCpnr($fa, $baum, $opnr) {
        if (substr($opnr,0,1) == '4') {
            $fab = 'WME';
        } else {
            $fab = 'WDB';
        }
        $sql = "SELECT cpnr, pakbez, sppspreis FROM rze.cat_paksel WHERE fa='$fa' AND baum='$baum' AND opnr='$opnr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) > 0) {
            $r['error'] = '';
            $r['cpnr'] = $res[0][0];
            $r['pakBez'] = $res[0][1];
            $r['sppsPreis'] = $res[0][2];
            return $r;
        }

        // Paket definiert durch BAUM + OPNR nicht in PAKSEL: einfügen
        $rfi = $this->rfi;
        $sql =  " SELECT  pkintnr, bezeich, preis FROM rdcpkopf ".
                " WHERE ficd='$rfi' AND baumust='$baum' AND pknr='$opnr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            // Care-ASC-Daten nicht aktuell
            //todo Wer immer dafür zuständig ist, muss benachrichtigt werden.
            $r['error'] = "Daimler Paket $fa/$baum/$opnr nicht in ASC-Paketen gefunden.";
            $r['code'] = "Paket fehlt in RDC";
            return $r;
        }
        if (count($res) > 1) {
            // theoretisch nicht mðglich
            $r['error'] = "Daimler Paket $fa/$baum/$opnr in ASC-Paketen mehrfach gefunden.";
            $r['code'] = "Paket mehrfach in RDC";
            return $r;
        }
        $res = $res[0];         // Ergebnis = 1. Zeile des Ergebnis-Array
        $apnr = $res[0];
        $pakBez = $res[1];
        $sppsPreis = sprintf('%7.2f', $res[2]/100);

        $pos = $fab;
        // get AW
        $sql =  " SELECT trim(awcare), trim(aw) FROM rdcpaw ".
                " WHERE facd='$fa' AND ficd='$this->rfi' AND pkintnr='$apnr' AND posfolge=1 ".
                " ORDER BY awcare";
        $res = $this->db->openQuery($fa, $sql);

        foreach ($res as $re) {
            $pos = $this->addPos($pos, $re);
        }
        // get Teile
        $sql =  " SELECT trim(tnrcare), trim(menge) FROM rdcpteil ".
                " WHERE facd='$fa' AND ficd='$this->rfi' AND pkintnr='$apnr' ".
                " ORDER BY tnrcare ";
        $res = $this->db->openQuery($fa, $sql);

        foreach ($res as $re) {
            $pos = $this->addPos($pos, $re);
        }

        // find/add CPNR
        $sql =  " SELECT cpnr FROM rze.cat_pakpos WHERE fa='$fa' AND pos='$pos' ";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) > 0) {
            // CPNR gefunden, nur noch in PAKSEL eintragen
            $cpnr = $res[0][0];
        } else {
            // neue CPNR ermitteln und in PAKPOS einfügen
            $sql = "SELECT max(cpnr) from rze.cat_pakpos ";
            $res = $this->db->openQuery($fa, $sql);
            if (count($res) == 0){
                $cpn = 1;
            } else {
                $cpn = $res[0][0] + 1;
            }
            $cpnr =  sprintf('%.8d', $cpn);
            $sql =  " INSERT INTO rze.cat_pakpos (fa, cpnr, pos, len, datum) ".
                " VALUES ('$fa', '$cpnr', '$pos', strlen($pos), $this->today)";
            $this->db->execQuery($fa, $sql);
        }

        // insert into PAKSEL
        $sql =  " INSERT INTO rze.cat_paksel (fa, baum, opnr, cpnr pakbez, sppspreis, datum) ".
                " VALUES ('$fa', '$baum', '$opnr', '$cpnr', '$pakBez', $sppsPreis, $this->today) ";
        $this->db->execQuery($fa, $sql);

        $r['error'] = '';
        $r['cpnr'] = $cpnr;
        $r['pakBez'] = $pakBez;
        $r['sppsPreis'] = $sppsPreis;
        return $r;
    }

    /**
     * appPos($posString, [nr, anz]): $posString = fab<tOuter>pos<tOuter>pos... with pos = nr<tInner>anz
     * @param string $posString
     * @param array $pos
     * @return string $posString
     */
    private function addPos($posString, array $pos) {
        return $posString . $this->tOuter . implode($this->tInner, $pos);
    }
}