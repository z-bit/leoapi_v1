<?php use App\Http\Controllers\Controller;


class PaketController extends Controller
{

    /*  todo
     *  select modbez as cpnr (c8)
     *
     */
    public function getByOpnr($fa, $fi, $baum, $opnr, $knr) {
        //todo
        //  select modbez as cpnr, pkintnr as apnr, bezeich, preis, import
        //  from rdcdbfDB.rdcpkopf where baumust=BAUM and pknr=OPNR

    }

    public function getByCpnr($fa, $fi, $opnr, $cpnr, $knr){
        //todo get it
    }


}