<?php namespace App\Models\MySql;

use Illuminate\Database\Connection;

class Session {
    public $session = [
        'error' => '',
        'fa' => '',
        'fi' => '',
        'pnr' => '',
        'bkz' => '',
        'auth' => '',
        'nam' => '',
    ];

    public function __construct() {
        $this->db = app('db');
        date_default_timezone_set('Europe/Berlin');
    }

    public function start($ip, $fa, $fi, $pnr, $bkz, $auth, $name) {
        $resDel = $this->delete_old_sessions();

        $valid_until = $this->valid_until($auth);

        $sql =  "insert into sessions (client_ip, valid_until, fa, fi, pnr, bkz, auth, nam ) " .
                "values ('$ip', '$valid_until', '$fa', '$fi', '$pnr', '$bkz', '$auth', '$name')";
        $resIns = $this->db->select($sql);

        $sql = "select max(session_id) as id from sessions where client_ip = '$ip' ";
        $resId = $this->db->select($sql);
        /*
         * $resId:
         * [
         *      {
         *          "id": 1
         *      }
         * ]
         */
        $id = explode(' ', $resId[0]->id);

        $s['error'] = '';
        $s['token'] = $id[0]+0;
        $s['fa'] = $fa;
        $s['fi'] = $fi;
        $s['pnr'] = $pnr;
        $s['bkz'] = $bkz;
        $s['auth'] = $auth;
        $s['nam'] = $name;
        //$s['resDel'] = count($resDel);
        //$s['resIns'] = $resIns;
        //$s['resId']  = $resId;

        return $s;
    }

    public function update($id, $ip) {
        $resDel = $this->delete_old_sessions();

        $sql = "select fa, fi, pnr, bkz, auth, nam from sessions where session_id=$id and client_ip='$ip' ";
        try {
            $resSel = $this->db->select($sql);
            if (count($resSel) == 0) {
                $s['error'] = "Token $id passt nicht zur IP $ip";
                return $s;
            }
        } catch(Exception $e) {
            $s['error'] = $e->getMessage();
            return $s;
        }
        $r = $resSel[0];

        $s['error'] = '';
        $s['fa'] = $r->fa;
        $s['fi'] = $r->fi;
        $s['pnr'] = $r->pnr;
        $s['bkz'] = $r->bkz;
        $s['auth'] = $r->auth;
        $s['name'] = $r->nam;

        //update valid_until
        $valid_until = $this->valid_until($s['auth']);

        $sql = "update sessions set valid_until = '$valid_until' where session_id = $id";
        $s['resVal'] = $this->db->select($sql);

        return $s;
    }


    public function end($id) {
        $s['resDel'] = $this->delete_old_sessions();

        $sql = "delete from sessions where session_id = $id";
        $s['resEnd'] = $this->db->select($sql);

        return $s;
    }

    private function delete_old_sessions() {
        $now = date('d.m.Y - H:i', time());

        $sql = "delete from sessions where valid_until < '$now'";
        $res = $this->db->select($sql);

        return $res;
    }

    private function valid_until($auth){
        if ($auth == 'NO') {
            $valid = 15 * 60; // 15 Minuten
        } else {
            $valid = 2 * 60* 60; // 2 Stunden
        }

        return date('d.m.Y - H:i', time() + $valid);
    }
}