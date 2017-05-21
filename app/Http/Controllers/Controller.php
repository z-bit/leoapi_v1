<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    private $time_start;

    function __construct()
    {
        $this->time_start = microtime(true);
    }

    public function result($r){
        //echo "result()";
        $r['time'] = sprintf("%d ms", round((microtime(true) - $this->time_start) * 1000));
        //print_r($r);
        $code = $r['error'] ? 400 : 200;
        return response()->json($r, $code);
    }
/*    public function result($r) {
        $code = $r['error'] ? 400 : 200;
        //print(json_encode($r));
        return response()
            ->json($r, $code)
            //->header('Access-Control-Allow-Origin', '*')
           // ->header('Access-Control-Allow-Methods', 'GET, PUT, POST, PATCH, DELETE, OPTIONS')
           //->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Access-Control-Allow-Origin')
        ;
    }
*/
}
