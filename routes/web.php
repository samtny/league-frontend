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
    Route::get('{association}/edit', 'AssociationsController@edit')->name('association.edit');
    Route::get('create', 'AssociationsController@create')->name('association.create');
    Route::post('create', 'AssociationsController@store');
    Route::post('update', 'AssociationsController@update');
    Route::get('{association}', 'AssociationsController@view')->name('association.view');
});

Route::prefix('series')->group(function () {
    Route::get('{series}/edit', 'SeriesController@edit')->name('series.edit');
    Route::get('create', 'SeriesController@create')->name('series.create');
    Route::post('create', 'SeriesController@store');
    Route::post('update', 'SeriesController@update');
    Route::get('{series}', 'SeriesController@view')->name('series.view');
});

Route::prefix('onboard')->group(function () {

    Route::get('association/{association}', function (App\Association $association) {
        return view('onboard.association', ['association' => $association]);
    })->name('onboard.association');

    Route::get('series/{series}', function (App\Series $series) {
        return view('onboard.series', ['series' => $series]);
    })->name('onboard.series');

});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::prefix('user')->group(function () {

    Route::get('{user}', 'UsersController@view')->name('user');

});
