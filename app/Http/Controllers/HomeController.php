<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {

        // echo json_encode(\App\Libraries\PaginationHandler::test());
        return response(\App\Libraries\PaginationHandler::test2())
            ->header('Content-Type', "json")
            ->header('Access-Control-Allow-Headers', 'X-Requested-With,content-type')
            ->header('Access-Control-Allow-Credentials', true)
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, PATCH, DELETE')
            ->header('Access-Control-Allow-Origin', '*');
        // return echo ;
        // return view('home');
    }
}
