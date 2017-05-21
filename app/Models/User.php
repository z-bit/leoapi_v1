<?php namespace App\Models;

use App\Models\Odbc\OdbcConnection;
use App\Models\Odbc\Preferences;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = OdbcConnection::getInstance(); 
    }

    public function pnr($fa, $pnr)
    { 
        $sql = "select mnrjcd, mnmnna, mne8st, mnzkce, mnficd, mnpftx, mnaedz";
        $sql.= "  from RPBBREP ";
        $sql.= "  where mnfacd='$fa' and mnmncd='$pnr'";

        $res =$this->db->openQuery($fa, $sql);

        if(count($res) == 0)
        {
            $r['error'] = "Personalnummer $pnr in Fa. $fa nicht gefunden.";
        }else{
            $pcd = trim($res[0][0]);    //pincode = alfanumerische Pnr / Benutzerkürzel
            $nam = trim($res[0][1]);    //Name
            $art = trim($res[0][2]);    //Personalart
            $abt = trim($res[0][3]);    //Abteilung
            $fi  = trim($res[0][4]);    //Filiale
            $pgr = substr($fi, -1) . trim($res[0][5]); //Personengruppe SP/Lohn
            $austritt = trim($res[0][6]);
            $austritt = $austritt == '0001-01-01' ? '' : $austritt;

            /* zoppi volle Berechtigung für Testzwecke */
            if ($pnr == '1152') $austritt=''; 
            /*******************************************/
            
            $user['fa']    = $fa;
            $user['fi']    = $fi;
            $user['pgr']   = $pgr;
            $user['bkz']   = $pcd;
            $user['pnr']   = $pnr;
            $user['name']  = utf8_encode($nam);
            $user['abt']   = $abt;
            $user['art']   = $art;
            $user['austritt'] = $austritt;
            $user['berechtigung']  = 'NO';
            $user['token'] = '';
//print_r($user); die; //OK
            $r = [];
            $r['user'] = $user;
            $r['error'] = false;
            $r['time'] = '';
        }
//print_r($r); die; //OK
        return $r;
    }

    public function checkPass($user, $pass)
    {
        $pref = Preferences::getInstance();
        $dsn = $pref->getProperty('odbc');
        try
        {
            $hcon = odbc_connect($dsn, $user, $pass);
        }
        catch(ErrorException $e)
        {
            return false;
        }

        odbc_close($hcon); 
        return true;
    }


    public function login($fa, $login)
    {
        $r = [];
        $sql = "select mnmncd, mnelcd, mnrjcd, mnmnna, mne8st, mnzkce, mnficd, mnpftx ";
        $sql.= "  from RPBBREP ";
        $sql.= "  where mnfacd='$fa' and mnpjtx='$login' ";
        $res =$this->db->openQuery($fa, $sql);
        if(count($res) == 0){
            //$login kommt im Personalstamm nicht vor - ist es ein Admin?
            $sql = "select admin, fa from RZE.Firma";
            $res = $this->db->openQuery($fa, $sql);
            for ($i=0; $i<count($res); $i++)
            {
                $admin = trim($res[$i][0]);
                if($admin == $login)
                {
                    $r['fa'] = $res[$i][1];
                }
            }
            if (isset($r['fa'])) {
                $user['fa'] = $fa;
                $user['fi'] = '01';        //immer 01, weil viele Firmen nur 01 haben
                $user['pgr'] = 'ADM';      // ?? to edit settings
                $user['bkz'] = $login;
                $user['pnr'] = $login;
                $user['name']= "Admin Fa. " . $r['fa'];
                $user['abt'] = 'EDV';
                $user['art']= '2';
                $user['berechtigung']= 'IT';
                $user['token'] = '';

                $r['user'] = $user;
                $r['error'] = false;
                return $r;
            }else{
                $r['error'] = "Kein Benutzer mit Login $login in Fa. $fa gefunden, obwohl Anmeldung positiv.";
                return $r;
            }
        }else{
            //$login im Personalstamm gefunden
            // +0 wandelt String in Integer und entfernt somit die Vornullen
            // (string) wadelt es zurück in einen String
            $pnr = (string) $res[0][1] + 0;

            /* zoppi volle Berechtigung für Testzwecke */
            if ($pnr == '9999') $pnr='1152'; 
            /*******************************************/

            $r = $this->pnr($fa, $pnr);
          
            

            if ($r['user']['austritt'] > '0001-01-01'){
                $heute = date("Y-m-d");
                if ($heute > $r['user']['austritt']){
                        $r['user']['berechtigung'] = 'NO';
                    }
            } else {
                $r['user']['berechtigung'] = $this->setBerechtigung($r['user']['abt']);
            }
        }
        return $r;
    }

    private function setBerechtigung($kriterium)
    {
        switch ($kriterium) {
            case 'BUCHH':   return 'BH';    // Buchhaltung
            case 'EDV':     return 'IT';    // Admin
            case 'LAGER':   return 'ET';    // Teile
            case 'VERK':    return 'WH';    // Wagenhandel
            default:        return 'SB';    // Serviceberater
        }    
    }
}