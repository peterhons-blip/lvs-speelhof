<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // HIER INLOGGEN MET SMART!!
        $waarde = 50;

        // logica uitgevoerd, daarna redirect
        if ($waarde > 100) {
            return redirect('/leerlingen');
        }

        // fallback (optioneel, wordt meestal niet bereikt)
        return view('home');
    }
}
