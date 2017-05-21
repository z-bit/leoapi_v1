<?php namespace App\Http\Controllers;

use App\Models\Kunden\KundenTeil;

class AuftragController extends Controller
{
    public function get($fa, $fi, $auf, $fg, $token)
    {
        $r['message'] = 'getAuftrag - Routing works';
        $r['error'] = "";
        $code = 200;
        return response()->json($r, $code);
        //$this->result($r);
    }
}