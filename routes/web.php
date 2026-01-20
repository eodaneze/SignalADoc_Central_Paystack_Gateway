<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tenant/return', function (Request $request) {
    return response()->json([
        'message'   => 'Returned to originating app',
        'status'    => $request->query('status'),
        'reference' => $request->query('reference'),
    ]);
});





