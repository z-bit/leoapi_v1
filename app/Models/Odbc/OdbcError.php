<?php namespace App\Models\Odbc;

class OdbcError
{
    public function send($err, $fa='', $sql='', $controller='', $model=''){
        $r = [];
        $r['error'] = $err;
        
        if ($fa)            $r['firma'] = $fa;
        if ($sql)           $r['sql'] = $sql;
        if ($controller)    $r['controller'] = $controller;
        if ($model)         $r['model'] = $model;

        return response()->json($r, 400);
    } 
}


