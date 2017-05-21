<?php namespace App\Models;

use App\Models\Odbc\Preferences;
use App\Models\Odbc\OdbcConnection;
use App\Models\Odbc\OdbcError;

interface iFirma
{
    /* {
     *      firma: {
     *          fa: string;
     *          fi: string;
     *          name: string;
     *          fils: string[];
     *          ip: string;
     *          client: string;
     *      }
     *      error: string;
     *      time: number;
     * }
     *
     */
    public function get($ip);
    public function set($fa, $fi);
}

class Firma implements iFirma
{
    private $db;

    private function ip_in_network($ip, $net_addr, $net_mask){
        if($net_mask <= 0){ return false; }
        $ip_binary_string = sprintf("%032b",ip2long($ip));
        $net_binary_string = sprintf("%032b",ip2long($net_addr));
        return (substr_compare($ip_binary_string,$net_binary_string,0,$net_mask) === 0);
    }

    private function getClient(){
        $client = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        $i = strpos($client, '.');
        if ($i > 0)
        {
            $client = substr($client, 0, $i);
        }
        return strtoupper($client);
    }

    private function getFils($fa){
        $sql = "select distinct fi from rze.firma where fa='$fa' order by fi";
        try
        {
            $res = $this->db->openQuery('RZ', $sql);
        }
        catch (Exception $e)
        {
            $error["error"] = $e->getMessage();
            return $error;
        }
        if (count($res) == 0)
        {
            $error['error'] = "Firma->getFils: Keine Filiale zu Firma $fa in rze.firma";
            return $error;
        }
        else {
            $fils = [];
            for($j=0; $j<count($res); $j++){
                array_push($fils, $res[$j][0]);
            };
            return $fils;
        }


    }

    public function __construct()
    {
        try{
            $this->db = OdbcConnection::getInstance();
        }
        catch (Exception $e)
        {
            $error["error"] = $e->getMessage();
            return $error;
        }

    }

    public function set($fa, $fi)
    {
        $sql = "select name from rze.firma where fa = '$fa' and fi = '$fi'";
        try{
            $val = $this->db->openQuery('RZ', $sql);
        }
        catch (Exception $e)
        {
            $error["error"] = $e->getMessage();
            return $error;
        }
        if (count($val) == 0) 
        {
            $error['error'] = "Firma::set - Firma $fa mit Filiale $fi nicht in RZE.Firma.";
            return $error;
        }

        $firma['fa'] =	$fa;
        $firma['fi'] =	$fi;
        $firma['name'] = trim($val[0][0]);
        $firma['fils'] = $this->getFils($fa);
        $firma['ip'] = $_SERVER['REMOTE_ADDR'];
        $firma['client'] = $this->getClient();

        $result['firma'] = $firma;
        $result["error"] = false;
        return $result;
    }


    public function get($ip)
    {
        $ip_arr = explode('.', $ip);

        if (count($ip_arr) != 4){
            $error['error'] = "Model Class Firma->get($ip): ip is not a valid IPv4 address.";
            return $error;
        }

        $ip_arr[3]=0;
        $sum = array_sum($ip_arr);

        
        $sql = "select fa, fi, name from rze.firma where ipsum = $sum";
        try{
            $val = $this->db->openQuery('RZ', $sql);
        }
        catch (Exception $e)
        {
            $error["error"] = $e->getMessage();
            return $error;
        }

        switch(count($val)){
            case 0:
                $sql = "select fa, fi, name, subnet, nml from rze.firma where nml <> 24";
                try
                {
                    $val = $this->db->openQuery('RZ', $sql);
                }
                catch (Exception $e)
                {
                    $error["error"] = $e->getMessage();
                    return $error;
                }
                if (count($val) == 0)
                {
                    $error['error'] = "Firma->get($ip): ipsum = $sum nicht in RZE.FIRMA und kein Eintrag mit verkürzter Maske (nml<>24).";
                    //wenn nml=24, dann sollte ipsum die dezimale Summe der ersten 3 Bytes sein, also zB. 3 für 1.1.1.0/24
                    //andernfalls sollte ein Satz mit nml<>24 existieren
                    return $error;
                }
                else
                {
                    for($i=0; $i<count($val); $i++){
                        $net  = $val[$i][3];
                        $mask = $val[$i][4];
                        if($this->ip_in_network($ip, $net, $mask)){
                            $firma['fa'] = $val[$i][0];
                            $firma['fi'] = $val[$i][1];
                            $firma['name'] = $val[$i][2];
                            $firma['fils'] = $this->getFils($val[$i][0]);
                            $firma['ip'] = $ip;
                            $firma['client'] = $this->getClient();

                            $result['firma'] = $firma;
                            $result['error'] = false;
                            return $result;
                        }
                    }

                }
            case 1:
                $firma['fa'] = $val[0][0];
                $firma['fi'] = $val[0][1];
                $firma['name'] = trim($val[0][2]);
                $firma['fils'] = $this->getFils($val[0][0]);
                $firma['ip'] = $ip;
                $firma['client'] = $this->getClient();

                $result['firma'] = $firma;
                $result['error'] = false;
                return $result;
            default:
                for($i=0; $i<count($val); $i++){
                    $net  = $val[$i][3];
                    $mask = $val[$i][4];
                    if($this->ip_in_network($ip, $net, $mask)){
                        $firma['fa'] =	$val[$i][0];
                        $firma['fi'] =	$val[$i][1];
                        $firma['name']=trim($val[$i][2]);
                        $firma['fils']=$this->getFils($val[$i][0]);
                        $firma['ip'] = $ip;
                        $firma['client'] = $this->getClient();

                        $result['firma'] = $firma;
                        $result['error'] = false;
                        return $result;
                    }
                }
                $result['error'] = "Model Class Firma->get($ip): nml = $mask and ipsum = $sum => not in RZE.FIRMA";
                //wenn nml=24, dann sollte ipsum die dezimale Summe der ersten 3 Bytes sein, also z.B. 3 für 1.1.1.0/24
                return $result;
        }
    }

    public function getLagers($fa, $fi)
    {
        $sql = "SELECT DISTINCT flflcd FROM rpberep WHERE flfacd='$fa' AND flficd='$fi' ORDER BY flflcd";
        try{
            $val = $this->db->openQuery($fa, $sql);
        }
        catch (Exception $e)
        {
            $error["error"] = $e->getMessage();
            return $error;
        }
        if (count($val) == 0)
        {
            $error['error'] = "Firma::getLagers - Firma $fa hat keine Lager in Filiale $fi.";
            return $error;
        }
        $res = [];
        for($i=0; $i<count($val); $i++) {
            array_push($res, $val[$i][0]);
        }
        $result['error'] = '';
        $result['lagers'] = $res;
        return $result;
    }
}
