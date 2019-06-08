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
    Route::get('/css/association.css', 'AssociationsController@css')->name('association.css');
});

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/standings', function () {
    return view('standings');
})->name('standings');

Route::prefix('schedule')->group(function () {


    Route::get('/', function () {
        return view('schedule');
    })->name('schedule');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::prefix('admin')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('create', 'UsersController@create')->name('user');
        Route::get('{user}', 'UsersController@view')->name('user');
    });

    Route::prefix('association')->group(function () {
        Route::get('{association}/venue/create', 'VenuesController@create')->name('venue.create');
        Route::post('{association}/venue/create', 'VenuesController@store');
        Route::get('{association}/venue/{venue}/edit', 'VenuesController@edit')->name('venue.edit');

        Route::get('{association}/team/create', 'TeamsController@create')->name('team.create');
        Route::post('{association}/team/create', 'TeamsController@store');
        Route::get('{association}/team/{team}/edit', 'TeamsController@edit')->name('team.edit');

        Route::get('{association}/division/create', 'DivisionsController@create')->name('division.create');
        Route::post('{association}/division/create', 'DivisionsController@store');
        Route::get('{association}/division/{division}/edit', 'DivisionsController@edit')->name('division.edit');
        Route::post('{association}/division/{division}/update', 'DivisionsController@update')->name('division.update');

        Route::get('{association}/edit', 'AssociationsController@edit')->name('association.edit');
        Route::get('create', 'AssociationsController@create')->name('association.create');
        Route::post('create', 'AssociationsController@store');
        Route::post('{association}/update', 'AssociationsController@update')->name('association.update');
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
        Route::get('{series}/schedule/create', 'ScheduleController@create')->name('schedule.create');
        Route::post('{series}/schedule/create', 'ScheduleController@store');
        Route::get('{series}/edit', 'SeriesController@edit')->name('series.edit');
        Route::get('create', 'SeriesController@create')->name('series.create');
        Route::post('create', 'SeriesController@store');
        Route::post('update', 'SeriesController@update')->name('series.update');
        Route::get('{series}', 'SeriesController@view')->name('series.view');
        Route::get('delete', 'SeriesController@delete')->name('series.delete');
    });

    Route::prefix('schedule')->group(function () {
        Route::get('{schedule}/edit', 'ScheduleController@edit')->name('schedule.edit');
        Route::post('{schedule}/update', 'ScheduleController@update');
    });

    Route::prefix('onboard')->group(function () {
        Route::get('association/{association}', function (App\Association $association) {
            return view('onboard.association', ['association' => $association]);
        })->name('onboard.association');
        Route::get('series/{series}', function (App\Series $series) {
            return view('onboard.series', ['series' => $series]);
        })->name('onboard.series');
    });

    Route::get('users', 'AdminController@users')->name('admin.users');
    Route::get('associations/deleted', 'AdminController@associationsDeleted')->name('admin.associations.deleted');
    Route::get('/', 'AdminController@overview')->name('admin');
});
