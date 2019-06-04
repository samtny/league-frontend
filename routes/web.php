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

Route::domain('{subdomain}.pinballleague.org')->middleware('subdomain')->group(function() {
    Route::get('/', 'AssociationsController@home')->name('association.home');
    Route::get('/submit', 'AssociationsController@submitScore')->name('association.submit.score');
    Route::get('/standings', 'AssociationsController@standings')->name('association.standings');
    Route::get('/schedule', 'AssociationsController@schedule')->name('association.schedule');
});

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
    Route::get('{association}/delete', 'AssociationsController@deleteConfirm')->name('association.deleteConfirm');
    Route::post('{association}/delete', 'AssociationsController@delete')->name('association.delete');
    Route::get('{association}/undelete', function () {
        Route::bind('trashed_user', function ($id) {
            return App\User::onlyTrashed()->find($id);
        });
    });

    Route::get('{association}/undelete', 'AssociationsController@undeleteConfirm')->name('association.undeleteConfirm');
    Route::post('{association}/undelete', 'AssociationsController@undelete')->name('association.undelete');
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

Route::prefix('admin')->group(function () {
    Route::get('users', 'AdminController@users')->name('admin.users');
    Route::get('associations/deleted', 'AdminController@associationsDeleted')->name('admin.associations.deleted');
});
