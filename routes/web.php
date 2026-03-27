<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('marketplace', ['surface' => 'storefront']);
});

Route::get('/admin', function () {
    return view('marketplace', ['surface' => 'admin']);
});
