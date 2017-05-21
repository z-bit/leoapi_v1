<?php namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Firma;

class FirmaController extends Controller
{
    private function ip_in_network($ip, $net_addr, $net_mask){
        if($net_mask <= 0){ return false; }
        $ip_binary_string = sprintf("%032b",ip2long($ip));
        $net_binary_string = sprintf("%032b",ip2long($net_addr));
        return (substr_compare($ip_binary_string,$net_binary_string,0,$net_mask) === 0);
    }

    public function index(){
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $firma = new Firma;
        $r = $firma->get($ip);
        return $this->result($r);
    }

    public function set($fa, $fi)
    {
        $firma = new Firma;
        $r = $firma->set($fa, $fi);

        $code = $r['error'] ? 400 : 200;
        return response()->json($r, $code);
    }

    public function getLagers($fa, $fi)
    {
        $firma = new Firma;
        $r = $firma->getLagers($fa, $fi);
        return $this->result($r);
    }
}