<?php namespace App\Http\Controllers;

class TestController extends Controller 
{
    public function __construct() {
       
    }

    function array_sort($array, $on, $order=SORT_ASC)
    {
        $new_array = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }

        return $new_array;
    }

    function key_sort($array, $key, $bedarf) {
        $b = [];
        $c = '';
        foreach ($array as $a) {
            $b[$a[$key]] = $a;
        }
        ksort($b);
        $sort = [];
        foreach ($b as $key => $val) {
            if($val['bestand'] > 0) array_push($sort, $val);
        }
        return $sort;
    }

    /**
     * select(arr): arr with arr = [i][fab, tnr, posBez, carePreis, kundPreis, bestFi, bestFa, datum]
     * @param array $arr
     * @return array $arr
     */
    public function select($arr){
        // nach Datum aufsteigend sortieren


        return $arr;
    }
       
    public function index($what='')
    {
        if ($what == 'array') {
            $a[0]['name']='Guenther';
            $a[0]['nummer']= 'A123456';
            $a[0]['datum']=1170301;
            $a[2]['name']='Max';
            $a[2]['nummer']= 'A123456';
            $a[2]['datum']=1160301;
            $a[1]['name']='Paul';
            $a[1]['nummer']='B654321';
            $a[1]['datum']=1170101;
            $a[2]['bestand']=1;
            $a[1]['bestand']=0;
            $a[0]['bestand']=2;

            $data = $this->key_sort($a, 'datum', 2);
        } else {
            //$data = '{"test": "erfolgreich"}';
            $data['test'] = 'erfolgreich';
        }
        $code =  200;
        return response()->json($data, $code);
        
    }

    public function odbc()
    {
            $r['client_ip'] = $_SERVER['REMOTE_ADDR'];
            $r['client_name'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
       
            $r['odbc'] =  'function odbc() getriggert';
            
            
            return response()->json($r, 200);
    }
}    
    