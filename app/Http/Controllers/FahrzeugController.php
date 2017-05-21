<?php namespace App\Http\Controllers;

use App\Models\Kunden\KundenTeil;

class FahrzeugController extends Controller
{
    public function get($fa, $fin, $token)
    {
        $r['message'] = 'getFahrzeug - Routing works';
        $r['error'] = "";
        $code = 200;
        return response()->json($r, $code);
        //$this->result($r);
    }
}