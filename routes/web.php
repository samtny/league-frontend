<?php

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
    return view('home');
})->name('home');

Route::get('/standings', function () {
    return view('standings');
})->name('standings');

Route::get('/schedule', function () {
    return view('schedule');
})->name('schedule');

Route::prefix('association')->group(function () {

    Route::get('create', function () {
        return view('association.create');
    });

    Route::post('create', 'AssociationsController@store');

    Route::get('{association}/new', function (App\Association $association) {
        return view('association.new', ['association' => $association]);
    })->name('association.new');

    Route::get('{association}', function (App\Association $association) {
        return $association->name;
    });

});
