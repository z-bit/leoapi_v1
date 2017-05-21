<?php namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MySql\Session;
use Illuminate\Http\Request;

class UserController extends Controller 
{
    private $ip;

    public function __constructor()
    {
        $this->ip = $_SERVER['REMOTE_ADDR'];
    }

    public function pnr($fa, $pnr)
    {
        $pnr = strtoUpper($pnr);
        $user = new User();
        $r = $user->pnr($fa, $pnr);

        if (!$r['error']) {
            $s = $this->start_session($fa, $r['user']['fi'], $pnr, $r['user']['bkz'], $r['user']['berechtigung'], $r['user']['name']);
            $r['user']['token'] = $s['token'];
        }

        return $this->result($r);
    }

    private function find($content, $name)
    {
        $i = strpos($content, $name);
        if($i === false) {
            return null;
        } else {
            // find $name in $content
            $a = $i+strlen($name)+1; // +1 fuer ", Anfang des Parameterwertes
            $e = strpos($content, '-', $a); // Ende des Parameterwertes
            $w = substr($content, $a, $e-$a);
            return trim($w);
        }
    }

    public function login($fa, Request $request)
    {
        /*if ($request->isMethod('OPTIONS')) {
            return
                response('OK', 200)
                    ->header('Access-Control-Allow-Origin', 'http://localhost:4200');
        }*/
        //$content = $request->getContent();

        $login = $request->input('benutzer');
        $pass  = $request->input('passwort');


        //$fa    = $request->input('fa');
        //$login = $this->find($content, 'login');
        //print('login: '); print($login); print('<br>');
        //$pass  = $this->find($content, 'pass');
        //print('pass: '); print($pass); print('<br>');
        //$fa = $this->find($content, 'fa');
        //print('fa: '); print($fa); print('<br>');

        $login = strtoupper($login);


        $r['error'] = '';
        $r['fa'] = $fa;
        $r['login'] = $login;
        $r['pass'] = $pass;
        //return $this->result($r);

        $user = new User();
        if ($user->checkPass($login, $pass))
        {
            $r = $user->login($fa, $login);
            $u = $r['user'];

            $s = $this->start_session($u['fa'], $u['fi'], $u['pnr'], $u['bkz'], $u['berechtigung'], $u['name']);
            $r['user']['token'] = $s['token'];

            // just for tests
            //$r['resDel'] = $s['resDel'];
            //$r['resIns'] = $s['resIns'];
            //$r['resId'] = $s['resId'];

        }
        else
        {
            $r["error"] = "Benutzername und/oder Kennwort falsch.";
        }

        return $this->result($r);//response()->json($r, $code);
    }

    private function start_session($fa, $fi, $pnr, $bkz, $auth, $nam) {
        $s = new Session();
        $r = $s->start($this->ip, $fa, $fi, $pnr, $bkz, $auth, $nam);

        return $r;
    }
    
}