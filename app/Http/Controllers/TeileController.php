<?php namespace App\Http\Controllers;

use App\Models\Kunden\KundenTeil;
use App\Models\Teile\TeileBezeichnung;

class TeileController extends Controller
{
    public function kundenpreis($fa, $kunden_nummer, $fabrikat, $teile_nummer, $token=0)
    {
        $kt = new KundenTeil();
        $r = $kt->info($fa, $kunden_nummer, $fabrikat, $teile_nummer);
        if ($r['error']) {
            $code = 400;
        } else {
            $code = 200;
        }
        return response()->json($r, $code);
    }

    public function bezeichnung($fa, $fabrikat, $teile_nummer, $token=0) {
        $tb = new TeileBezeichnung();
        $r = $tb->get($fa, $fabrikat, $teile_nummer);
        return $this->result($r);
    }

    public function bestand($fa, $fi, $fabrikat, $teile_nummer, $token=0) {
        $r['error'] = 'routing klappt';
        $r['code'] = 'Test';

        if ($r['error']) {
            $code = 400;
        } else {
            $code = 200;
        }
        return response()->json($r, $code);
    }


    public function read_list($fa, $fi, $list, $token, $slash)
    {
        $list=str_replace($slash, '\\', $list);
        if ($file = fopen($list, "r")) {
            $r['error'] = '';
            $string = fread($file, filesize($list));
            $string = utf8_encode($string);
            $a = explode('|', $string);
            $kopf = (object)[
                'file' => array_shift($a),
                'user' => array_shift($a),
                'name' => array_shift($a)
            ];
            $teile = [];

            while (count($a) >= 6) {
                $teil = [
                    'tnr' => trim(array_shift($a)),
                    'bez' => array_shift($a),
                    'anz' => array_shift($a),
                    'fin' => array_shift($a),
                    'g_k' => array_shift($a),
                    'txt' => array_shift($a),
                    'k_preis' => '',
                    'best_fi' => '',
                    'best_fa' => '',
                    'ersetzt' => '',
                ];
                array_push($teile, $teil);
            }
            //$liste = (object)[$kopf, $teile];

            //print_r(json_encode($teile));
            //print('<br> Length a: ' + count($a));
            //print('<br> Rest a: ' + array_shift($a));
            //$r['kopf'] = $kopf;
            $r['teile'] = $teile;
            $code = 200;
            fclose($file);
        } else {
            $r['error'] = "Einkaufsliste nicht lesbar: $list";
            $r['teile'] = '';
            $code = 400;
        }
        //$this->result($r);
        return response()->json($r, $code);
    }
}