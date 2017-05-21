<?php namespace App\Http\Controllers;

use App\Http\Controllers\Controller;


class EwanapiController extends Controller
{
    public function epc($pc) {
        $ewanapiPath = __DIR__.'/../../../storage/ewanapi/';
        $file = "$pc.param";
        $param = "-application EPC-Net".PHP_EOL;
        try {
            file_put_contents($ewanapiPath.$file, $param);
            $r["message"] = "ewanapiParams OK for $pc";
            $r["error"] = "";
        } catch (Exception $e) {
            $r["error"] = $e->getMessage();
        } finally {
            return $this->result($r);
        }
    }

    public function epc_fin($pc, $fin) {
        $ewanapiPath = __DIR__.'/../../../storage/ewanapi/';
        $file = "$pc.param";
        $param = "-application EPC-Net -V $fin".PHP_EOL;
        try {
            file_put_contents($ewanapiPath.$file, $param);
            $r["message"] = "ewanapiParams OK for $pc";
            $r["error"] = "";
        } catch (Exception $e) {
            $r["error"] = $e->getMessage();
        } finally {
            return $this->result($r);
        }
    }
}