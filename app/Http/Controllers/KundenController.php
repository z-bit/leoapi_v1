<?php namespace App\Http\Controllers;

use App\Models\Kunden\KundenTeil;

class KundenController extends Controller
{
    public function get($fa, $knr, $token)
    {
        $r['message'] = 'getKunde - Routing works';
        $r['error'] = "";
        $code = 200;
        //$this->result($r);
        return response()->json($r, $code);
    }
}