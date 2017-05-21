<?php namespace App\Models\Teil;
use App\Models\Odbc\OdbcConnection;

class Teil{

    private $db;                // Care ODBC connection
    private $t1;                // Startzeit
    private $inTeilePreise;     // Teil in Teilepreise (angelegt)

    public function __construct()
    {
        $this->t1 = microtime(true);
        $this->db = OdbcConnection::getInstance();
    }

    private function done($r) {
        $r['time_inner'] = sprintf("%d ms", round((microtime(true) - $this->t1) * 1000));
        return($r);
    }

    /**
     * get(fa, fi, fab, tnr, knr): [i][fab, tnr, posBez, carePreis, kundPreis, bestFi, bestFa, datum, $anz]
     * wegen Vorgänger /Nachfolger werden alle möglichen Teile (mit Bestand) zurückgegeben
     * @param $fa
     * @param $fi
     * @param $fab
     * @param $tnr
     * @param $bedarf
     * @param $knr
     * @return array [
     */
    public function get($fa, $fi, $fab, $tnr, $bedarf, $knr) {
        // alle Vorgänger/Nachfolger einbeziehen
        $alt = $this->getAlternativeTeile($fa, $fab, $tnr);
        $i = 0;
        foreach ($alt as $t) {
            $r[$i]['fa'] = $fa;
            $r[$i]['fi'] = $fi;
            $r[$i]['fab'] = $t['fab'];
            $r[$i]['tnr'] = $t['tnr'];

            $res = $this->getBezeichnung($fa, $t['fab'], $t['tnr']);
            if ($res['error']) return $res;
            $r[$i]['posBez'] = $res['teile_bezeichnung'];

            $res = $this->getBestand($fa, $fi, $t['fab'], $t['tnr']);
            // keine Fehler, weil bei fehlendem Teil der Bestand 0 zurückgegeben wird
            $r[$i]['bestFi'] = $res['bestFi'];
            $r[$i]['bestFa'] = $res['bestFa'];
            $r[$i]['datum'] = $res['datum'];
            $r[$i]['anz'] = 0;                      // wird zugewiesen in slectFromAlternatives

            if ($this->inTeilePreise) {
                // inTeilePreise wird gezetzt von getBezeichnung(),
                // wenn die Bezeichnung bereits in den Teilepreise-Bezeichnungen gefunden wird
                $res = $this->getPreise($fa, $t['fab'], $t['tnr'], $knr);
                $r[$i]['carePreis'] = $res[0]['carePreis'];
                $r[$i]['kundPreis'] = $res[0]['kundPreis'];
            } else {
                $r[$i]['carePreis'] = 0;
                $r[$i]['kundPreis'] = 0;
            }
            $i++;
        }

        /**
         * selectFromAlternatives
         * ======================
         * entprechend Bedarf auswählen nach: Datum letzter Zugang und Bestand
         */
        // 1. sortieren nach Datum letzter Zugang
        $b = [];
        $c = '';
        foreach ($r as $a) {
            $b[$a['datum']] = $a;               // jeder Zeile wird das Datum als Schlüssel zugewiesen
        }                                       // [i][alternative] ==> ['datum'][alternative]
        ksort($b);                              // dann Sortieren nach Schlüssel (Datum) aufsteigen,
        $sort = [];                             // so dass die ältesten Teile zuerst verawendet werden.
        $ib = count($b);
        $ival = 0;
        foreach ($b as $key => $val) {
            $ival++; // Wir starten mit 1, damit im letzten Satz $ival = $ib ist.
            if ($ival == $ib) {
                // letzter Satz von $b erreicht: Restbestand wird draufgeknallt (muss bestellt werden)
                // unabhängig vom Filialbestand
                $val['anz'] = $bedarf;
                array_push($sort, $val);
            } else {
                if ($val['bestandFi'] > 0) {
                    if ($val['bestandFi'] >= $bedarf) {
                        // ausreichend Bestand
                        $val['anz'] = $bedarf;
                        $bedarf = 0;
                    } else {
                        // Bestand reicht nicht aus
                        $val['anz'] = $val['bestandFi'];
                        $bedarf -= $val['bestandFi'];
                    }
                    array_push($sort, $val);
                }
            }
            if ($bedarf == 0) break;
        }
        $r = $sort;
        return $r;
    }

    /**
     * getBestand(fa, fi, fab, tnr): [bestFi, bestFa, datum(=letzter Zugang)]
     * @param $fa
     * @param $fi
     * @param $fab
     * @param $tnr
     * @return array [bestFi, bestFa]
     */
    public function getBestand($fa, $fi, $fab, $tnr){
        // Bestand Fa
        $sql =  " SELECT tbawva FROM rpbfrep WHERE tbfacd='$fa' AND tbfbcd='$fab' AND tbtnr='$tnr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            // Teile nicht gefunden
            $r['bestFa'] = 0;
            $r['bestFi'] = 0;
            $r['datum'] = 0;
            return $r;
        } else {
            $r['bestFa'] = 0;
        }
        // Bestand Fi
        $sql =  " SELECT tbawva, tblezu FROM rpbfrep WHERE tbfacd='$fa' AND tbficd='$fi' AND tbfbcd='$fab' AND tbtnr='$tnr'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) == 0) {
            // Teile nicht gefunden
            $r['bestFi'] = 0;
            $r['datum'] = 0;
        } else {
            $r['bestFi'] = $res[0][0];
            $r['datum'] = $res[0][1];
        }
        return $r;
    }

    /**
     * getBezeichnung(fa, fab, tnr): [teile_bezeichnung, error, code]
     * @param $fa
     * @param $fab
     * @param $tnr
     * @return array = [teileBezeichnung, error, code]
     */
    public function getBezeichnung($fa, $fab, $tnr)
    {
        $r['time_setup'] = sprintf("%d ms", round((microtime(true) - $this->t1) * 1000));
        //Teilepreise-Bezeichnung RPC7REP
        $sql = "SELECT c7tpbz FROM rpc7rep WHERE c7facd='$fa' AND c7fbcd='$fab' AND c7tnr='$tnr' AND c7spcd='D'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) > 0) {
            $r['error'] = '';
            $r['teile_bezeichnung'] = trim($res[0][0]);
            $this->inTeilePreise = true;
            return $this->done($r);
        }

        // Teilekatalog-Bezeichnung RPC5REP
        $sql = "SELECT c5tpbz FROM rpc5rep WHERE c5facd='$fa' AND c5fbcd='$fab' AND c5tnr='$tnr' AND c5spcd='D'";
        $res = $this->db->openQuery($fa, $sql);
        if (count($res) > 0) {
            $r['error'] = '';
            $r['teile_bezeichnung'] = trim($res[0][0]);
            $this->inTeilePreise = false;
            return $this->done($r);
        }

        // Bezeichnung nicht gefunden
        $r['error'] = "Teilebezeichnung $fa/$fab/$tnr/D weder in Teilepreise noch in Teilekatalog.";
        $r['code'] = 'Teil fehlt';
        $this->inTeilePreise = false;
        return $this->done($r);
    }

    /**
     * getPreise(fa, fab, tnr, knr): [carePreis, kundPreis, error, code]
     * @param $fa
     * @param $fab
     * @param $tnr
     * @param $knr
     * @return mixed [carePreis, kundPreis, error, code]
     */
    public function getPreise($fa, $fab, $tnr, $knr){
        //KRG
        switch ($knr) {
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

    /**
     * private getAlternativeTeile(fa, fab, tnr): [i][fab, tnr]
     * @param $fa
     * @param $fab
     * @param $tnr
     * @return mixed [i][fab, tnr]
     */
    private function getAlternativeTeile($fa, $fab, $tnr) {
        // das Teil selbst
        $r[0]['fab'] = $fab;
        $r[0]['tnr'] = $tnr;
        // Vorgänger rekursiv bis Kettenanfang oder dieses Teil (Kreis)
        while (true){
            $sql = "SELECT tefbcd, tetnr FROM rpaqrep WHERE tefacd='$fa' AND tepuce='$fab' AND tecgcd=$tnr";
            $res = $this->db->openQuery($fa, $sql);
            if (count($res) == 0){
                // kein Vorgänger (mehr); Kettenanfang
                continue;
            } else {
                $v['fab'] = $res[0][0];
                $v['tnr'] = $res[0][1];
                if ($v['fab'] == $fab && $v['tnr'] == $tnr) {
                    // Kreis geschlossen: alle Alternativen gefunden
                    return $r;
                } else {
                    // neuer Vorgänger
                    array_unshift($r, $v);
                }
            }
        }
        // Nachfolger rekursiv bis Kettenende oder dieses Teil ein zweites Mal
        while(true) {
            $sql = "SELECT tepuce, tecgcd FROM rpaqrep WHERE tefacd='$fa' AND tefncd='$fab' AND tetnr=$tnr";
            $res = $this->db->openQuery($fa, $sql);
            if (count($res) == 0){
                // kein Nachfolger (mehr): Kettenende
                return $r;
            } else {
                $n['fab'] = $res[0][0];
                $n['tnr'] = $res[0][1];
                if ($n['fab'] == $fab && $n['tnr'] == $tnr) {
                    // Kreis geschlossen: alle Alternativen gefunden
                    // sollte eigentlich schon in der Vorgänger-Runde gefunden worden sein
                    return $r;
                }
                if ($n['fab'] == $v['fab'] && $n['tnr'] == $v['tnr']) {
                    // erweiterter Kreis (Kettenanfang erreicht)
                    // sollte ebenfalls schon in der Vorgänger-Runde gefunden worden sein
                }
                array_push($r, $v);
            }
        }
        // sollte nie erreich werden
        return $r;
    }

}