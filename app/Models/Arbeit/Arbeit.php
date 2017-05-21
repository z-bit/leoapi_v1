<?php namespace App\Models\Arbeit;
use App\Models\Odbc\OdbcConnection;

class Arbeit
{
    private $db;                // Care ODBC connection
    private $t1;                // Startzeit

    public function __construct()
    {
        $this->t1 = microtime(true);
        $this->db = OdbcConnection::getInstance();
    }

    /**
     * get(fa, fab, aoNr, knr): [fa, fab, aoNr, aoBezLang, careAwPreis, kundAwPreis]
     * @param $fa
     * @param $fab
     * @param $aoNr
     * @param $knr
     * @return array [fa, fab, aoNr, aoBezLang, careAwPreis, kundAwPreis]
     */
    public function get($fa, $fab, $aoNr, $knr)
    {
        $sql =  " SELECT axattx FROM rpb6rep WHERE axfacd='$fa' AND axfbcd='$fab' AND axaocd='$aoNr' AND axspcd='D'".
                " ORDER BY axaxpn";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            $r['error'] = "AO-Bezeichnung für $fa/$fab/$aoNr nicht in RPB6REP.";
            $r['code'] = 'AO-Bezeichnung fehlt';
            return $r;
        }
        $aoBezLang = '';
        foreach ($res as $ao) {
            $aoBezLang .= trim($ao[0]) . ' ';
        }
        $aoBezLang = trim($aoBezLang);
        $r = [];
        $r['fa'] = $fa;
        $r['fab'] = $fab;
        $r['aoNr'] = $aoNr;
        $r['aoBezLang'] = $aoBezLang;

        $res = $this->getPreise($fa, $fab, $aoNr, $knr);
        if ($res[0]['error']) {
            $r['error'] = $res[0]['error'];
            $r['code'] = $res[0]['code'];
            return $r;
        }
        $r['careAwPreis'] = $res[0]['careAwPreis'];
        $r['kundAwPreis'] = $res[0]['kundAwPreis'];
        return $r;
    }

    /**
     * getPreise(fa, fi, fab, aoNr, knr, aufArt=''): [careAwPreis, kundAwPreis, error, code]
     * @param $fa
     * @param $fi
     * @param $fab
     * @param $aoNr
     * @param $knr
     * @param $aufArt
     * @return mixed [careAwPreis, kundAwPreis, error, code]
     */
    public function getPreise($fa, $fi, $fab, $aoNr, $knr, $aufArt=''){
        $sql = "SELECT aovrcd FROM rpawrep where aofacd='$fa' AND aofbcd='$fab' AND aoaocd='$aonr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            $r['error'] = "AO-Verrechnungscode für $fa/$fab/$aoNr nicht in RPAWREP.";
            $r['code'] = 'AO-Verrechnungscode fehlt';
            return $r;
        }
        $aovrcd = $res[0][0];
        //KRG
        switch ($knr)
        {
            case 'K':
                $krg = 'OHN';
                $kart = 'K';
                break;
            case 'P':
                $krg = 'OHN';
                $kart = 'P';
                break;
            default:
                // Kundenart
                $sql = "SELECT kdb8st from rpairep WHERE kdfacd='$fa' AND kdkdcd='$knr' ";
                $res = $this->db->openQuery($fa, $sql);
                if (count($res) == 0) {
                    $r['error'] = "Kunde fehlt im Stamm: $fa/$knr";
                    $r['code'] = 'Kunde fehlt';
                    return $r;
                }
                $kart = $res[0][0];
                if ($kart == 'P') {
                    $knr = 'P';
                } else {
                    // Rabattgruppe
                    $sql = "SELECT cvajcd FROM rpcvrep WHERE cvfacd='$fa' AND cvkdcd='$knr' ";
                    $res = $this->db->openQuery($fa, $sql);
                    if (count($res) == 0) {
                        $krg = 'OHN';
                    } else {
                        $krg = $res[0][0];
                    }
                }
        }

        $sql =  " SELECT rarabt FROM rpbarep ".
                " WHERE rafacd='$fa' AND raajcd='$krg' AND raaicd>='$aovrcd' AND raa1cd<='$aovrcd' and raflsu = '9'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0){
            $awRabat = 0;
        } else {
            $awRabat = $res[0][0];
        }

        // AW-Preis abhängig von fi, aufArt, aovrcd
        if ($aufArt == '') {
            if ($fa == '20') {
                $aufArt = strpos($aoNr, '-') == 3 ? 'KON' : 'KOS';
            } else {
                $aufArt = 'KOS';
            }
        }
        $sql =  " SELECT a9afva FROM rpa9rep ".
                " WHERE a9facd='$fa' AND a9ficd='$fi' AND a9a7cd='$aufArt' AND a9vrcd='$aovrcd' ";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            $r['error'] = "AW-Preis nicht gefunden: $fa/$fi/$aufArt/$aovrcd";
            $r['code'] = 'AW-Preis fehlt';
            return $r;
        }

        $sql = "SELECT 

'SELECT '''+sFa+''', '''+sKnr+''', gqh5st, gqfbcd, gqqfcd, gqb7dt, ' +
      'gqb8dt, gql2tx, gqflsu, gqrabt, gqiest, gqh2va FROM '+gAsp+'rqgqrep ' +
	    'WHERE gqfacd='''+sFa+''' AND gqkdcd=''666040'' ';"

        // RcVK, vk1, vk2, vk3 aus TeilePreise
        $sql =  " SELECT tpircd, tpvk1, tpvk2, tpvk3, tplipr, tpekne  FROM rpatrep ".
            " WHERE tpfacd='$fa' AND tpfbcd='$fab' AND rptnr='$tnr' ";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            $r['error'] = "Kein Satz in Teilepreise für $fa/$fab/$tnr, obwohl Teilepreis-Bezeichnung vorhanden.";
            $r['code'] = 'Teilepreis fehlt';
            return $r;
        }
        $res = $res[0];
        $rcvk = $res[0];
        $vk1 = $res[1];
        $vk2 = $res[2];
        $vk3 = $res[3];
        $lipr = $res[4];
        $ek = $res[5];

        //Preise festlegen
        switch ($knr) {
            case 'K':
                $r['carePreis'] = $vk1;
                $r['kundPreis'] = $vk1;
                break;
            case 'P':
                $r['carePreis'] = $vk3;
                $r['kundPreis'] = $vk3;
            default:
                $r['carePreis'] = $vk2;
                $sql =  " SELECT rarabt, raflsu FROM rpbarep ".
                    " WHERE rafacd='$fa' AND raajcd = $krg AND $rcvk >= raircd AND $rcvk <= rai1cd ";
                $res = $this->db->openQuery($fa, $sql);
                if (count($res) == 0){
                    // kein Rabatt
                    $r['carePreis'] = $vk1;
                    $r['kundPreis'] = $vk1;
                } else {
                    $rabattFaktor = 1 - ($res[0][0]/100); //Bsp.: 1 - (25.00 / 100) = 0.75;
                    switch ($res[0][1]) {
                        case '1': $basisPreis = $vk1; break;
                        case '2': $basisPreis = $vk2; break;
                        case '3': $basisPreis = $vk3; break;
                        case '4': $basisPreis = $lipr; break; //Listpreis
                        case '5': $basisPreis = $ek; break; //ek
                        case '6': $basisPreis = $ek; break; //dak
                        // DAK ist in Telebestand, um eine extra Abfage hier zu vermeiden, schummeln wir
                        // zulässig, weil 1.) dak idR niedriger als ek. 2.) kommt so wie nicht vor
                        case '7': $basisPreis = $vk2; break; //wh basis rabatt
                        case '8': $basisPreis = $vk2; break; //wh zusatz rabatt
                        case '9': $basisPreis = $vk2; break; //aw rabatt == vollständigkeitshabler: hier unrelevant
                    }
                    $r['carePreis'] = $basisPreis;
                    $r['kundPreis'] = round($basisPreis * $rabattFaktor, 2);
                }
        }
        return $r;
    }
}
