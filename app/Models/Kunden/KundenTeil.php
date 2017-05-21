<?php namespace App\Models\Kunden;

use App\Models\Odbc\OdbcConnection;

class KundenTeil
{

    private $db;

    public function __construct()
    {
        $this->db = OdbcConnection::getInstance();
    }

    public function info($fa, $knr, $fab, $tnr) {

        $time_start = microtime(true);
        $info = [];
        $info['error'] = '';

        if ($knr == 'P') {
            // Passant: Standard für Abfragen ohne Kundennummer
            $krg = 'OHN';
            $ktvkpst = '3';
        } else {
            //select $ktvkpst from kunden
            $sql = "SELECT kdxetx FROM rpairep WHERE kdfacd='$fa' AND kdkdcd='$knr'";
            $res = $this->db->openQuery($fa, $sql);
            if(count($res) == 0)
            {
                $info['error'] = "Kunde $fa/$knr nicht in Kunden gefunden.";
                return $info;
            }else {
                $ktvkpst = trim($res[0][0]);    // Kundenteileverkaufspreisstufe
                $info['ktvkpst'] = $ktvkpst;
            }

            // select $krg from Kunden-Rabattgruppen
            // folgende Kriterien werden vernachlässigt, obwohl in Fa. 10 gepflegt,
            // sind sie alle mit der gleichen krg belegt:
            // cvficd as fi, cvekst as vw(Verkauf/Werkstatt), cva7cd as aart, cvdwcd as kunden_bestellart
            $sql = "SELECT cvajcd FROM rpcvrep WHERE cvfacd='$fa' AND cvkdcd='$knr'";
            $res = $this->db->openQuery($fa, $sql);
            if(count($res) == 0)
            {
                $info['error'] = "Kunde $fa/$knr nicht in Kunden-Rabattgruppen";
                return $info;
            }else {
                $krg = trim($res[0][0]);    // Kundenrabattgruppe
            }
            if ($ktvkpst == 3 && $krg != 'OHN') {
                $warnung = "Kunde $knr ist Passant (TVKPST=3) mit Rabattguppe $krg - Passant ignoriert.";
                //todo WARNUNG kommunizieren: log und mail
                $info['warnung'] = $warnung;
            }
        }

        //select rc_vk, lipr, vk1, vk2, vk3 from Teile-Preise
        $sql = "select TPTGCD as tgr, TPIRCD as rcvk, TPLIPR as lipr, TPTVK1 as vk1, TPTVK2 as vk2, TPTVK3 as vk3
                from RPATREP where TPFACD='$fa' and TPFBCD='$fab' and TPTNR='$tnr'";
        $res = $this->db->openQuery($fa, $sql);
        if(count($res) == 0)
        {
            $info['error'] = "Teilenummer $fa/$fab/$tnr in Teilepreise nicht gefunden.";
            return $info;
        }else {
            $tgr = trim($res[0][0]);    // Teilegruppe
            $rcvk = trim($res[0][1]);   // Rabattcode Verkauf
            $lipr = $res[0][2];         // Listenpreis
            $vk1 = $res[0][3];
            $vk2 = $res[0][4];
            $vk3 = $res[0][5];
        }
/*
        //select Bezeichnung from teile_bezeichnung
        $sql = "select C7TPBZ from RPC7REP where C7FACD='$fa' and C7FBCD='$fab' and C7TNR='$tnr' and C7SPCD='D'";
        $res = $this->db->openQuery($fa, $sql);
        if(count($res) == 0)
        {
            $info['error'] = "Teilebezeichnung $fa/$fab/$tnr/D in Teilepreise-Bezeichnung nicht gefunden.";
            return $info;
        }else {
            $bez = trim($res[0][0]);    // Teilebezeichnung
        }

        $info['teile_bezeichnung'] = $bez;
        $info['care_preis'] = $vk1;
*/
        if ($krg == 'OHN') {
            switch ($ktvkpst) {
                case 1: $vk = $vk1; break;
                case 2: $vk = $vk2; break;
                case 3: $vk = $vk3; break;
                default: $vk = $vk1;
            }
            $info['kunden_preis'] = $vk;
            return $info;
        }

        //select vkpst, rabatt from Rabattgruppen-Positionen
        $sql = "select RAIRCD as rcvk_von, RAI1CD as rcvk_bis, RAFLSU as vkpst, RARABT as rabatt
                from RPBAREP where RAFACD='$fa' and RAAJCD='$krg'";
        $res = $this->db->openQuery($fa, $sql);
        if(count($res) == 0)
        {
            $info['error'] = "Keine Rabattgruppen-Positionen für Kunden-Rabattgruppe $krg (Fa. $fa).";
            return $info;
        }else {
            for ($i=0; $i<count($res); $i++) {
                $rc_von = trim($res[$i][0]);
                $rc_bis = trim($res[$i][1]);
                $vkpst  = trim($res[$i][2]);
                $rabatt = $res[$i][3];

                if ($rcvk >= $rc_von && $rcvk <= $rc_bis) {
                    switch ($vkpst) {
                        case 1: $vk = $vk1; break;
                        case 2: $vk = $vk2; break;
                        case 3: $vk = $vk3; break;
                        default: $vk = $vk1;
                    }
                    $info['kunden_rabatt'] = sprintf("%01.2f", $rabatt).' %';
                    $info['kunden_preis'] = sprintf("%01.2f", $vk - round($vk * $rabatt) / 100);

                    $time_end = microtime(true);
                    $info['time'] = ($time_end - $time_start) * 1000 .' ms';
                    return $info;
                }
            }
            switch ($ktvkpst) {
                case 1: $vk = $vk1; break;
                case 2: $vk = $vk2; break;
                case 3: $vk = $vk3; break;
                default: $vk = $vk1;
            }
            $info['kunden_preis'] = $vk;

            $time_end = microtime(true);
            $info['time'] = ($time_end - $time_start)/60;
            return $info;
        }
    }
}
/*
 *   Teilepreisaufschläge wie in Fa. 10 aus Teilepreiskalkulation:
 *       Die Preise stehen schon kalkuliert im Stamm,
 *       hier irrelevant aber beim Anlegen von Teilen zu beachten!!
 *
 *       $sql = "select TKAAPC as vk1_proz, TKABPC as vk2_proz, TKACPC as vk3_proz
 *               from RPASREP where TKFACD='$fa' and TKFBCD='$fab' and TKAKCE='$tgr'";
 */



