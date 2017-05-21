<?php namespace App\Http\Middleware;

use Closure;
use App\Models\MySql\Session;

class CheckSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inputs = explode('/', $request->path());
        $id = $inputs[count($inputs)-1];

        $s = new Session();
        $r = $s->update($id, $ip);

        if ($r['error']) {
            $code = 400;
            return response()->json($r, $code);
        }

        return $next($request);
    }
}
