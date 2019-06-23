<?php

use Spatie\Honeypot\ProtectAgainstSpam;

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

Route::domain('{association}.pinballleague.org')->middleware('subdomain')->group(function() {
    Route::get('/', 'AssociationsController@home')->name('association.home');

    Route::get('/submit', 'AssociationsController@submitScoreBegin')->name('association.submit.score.step1');
    Route::post('/submit/step2', 'AssociationsController@submitScoreStep2')->name('association.submit.score.step2');
    Route::post('/submit/step3', 'AssociationsController@submitScoreStep3')->name('association.submit.score.step3');
    Route::post('/submit/step4', 'AssociationsController@submitScoreStep4')->name('association.submit.score.step4')
        ->middleware(ProtectAgainstSpam::class);

    Route::get('/standings', 'AssociationsController@standings')->name('association.standings');
    Route::get('/schedule', 'AssociationsController@schedule')->name('association.schedule');
    Route::get('/css/association.css', 'AssociationsController@css')->name('association.css');

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/', 'AssociationsController@view')->name('association.view');
    });

});

Route::prefix('admin')->middleware('admin')->group(function () {

    Route::prefix('association')->middleware('admin.association')->group(function () {

        Route::get('{association}/venue/create', 'VenuesController@create')->name('venue.create');
        Route::post('{association}/venue/create', 'VenuesController@store');
        Route::get('{association}/venue/{venue}/edit', 'VenuesController@edit')->name('venue.edit');
        Route::post('venue/{venue}/update', 'VenuesController@update')->name('venue.update');
        Route::get('venue/{venue}/delete', 'VenuesController@deleteConfirm')->name('venue.deleteConfirm');
        Route::post('venue/{venue}/delete', 'VenuesController@delete')->name('venue.delete');

        Route::get('{association}/team/create', 'TeamsController@create')->name('team.create');
        Route::post('{association}/team/create', 'TeamsController@store');
        Route::get('{association}/team/{team}/edit', 'TeamsController@edit')->name('team.edit');
        Route::post('{association}/team/{team}/update', 'TeamsController@update')->name('team.update');
        Route::get('{association}/team/{team}/delete', 'TeamsController@deleteConfirm')->name('team.deleteConfirm');
        Route::post('{association}/team/{team}/delete', 'TeamsController@delete')->name('team.delete');

        Route::get('{association}/division/create', 'DivisionsController@create')->name('division.create');
        Route::post('{association}/division/create', 'DivisionsController@store');
        Route::get('{association}/division/{division}/edit', 'DivisionsController@edit')->name('division.edit');
        Route::post('{association}/division/{division}/update', 'DivisionsController@update')->name('division.update');
        Route::get('{association}/division/{division}/delete', 'DivisionsController@deleteConfirm')->name('division.deleteConfirm');
        Route::post('{association}/division/{division}/delete', 'DivisionsController@delete')->name('division.delete');

        Route::get('{association}/edit', 'AssociationsController@edit')->name('association.edit');
        Route::get('{association}/divisions', 'AssociationsController@divisions')->name('association.divisions');
        Route::get('{association}/teams', 'AssociationsController@teams')->name('association.teams');
        Route::get('{association}/venues', 'AssociationsController@venues')->name('association.venues');
        Route::get('{association}/series', 'AssociationsController@series')->name('association.series');
        Route::get('{association}/users', 'AssociationsController@users')->name('association.users');

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

        Route::get('{association}/user/{user}/edit', 'AssociationsController@editUser')->name('association.user.edit');
        Route::post('{association}/user/{user}/update', 'AssociationsController@updateUser')->name('association.user.update');
        Route::get('{association}/user/add', 'AssociationsController@addUser')->name('association.user.add');

        Route::get('{association}/undelete', 'AssociationsController@undeleteConfirm')->name('association.undeleteConfirm');
        Route::post('{association}/undelete', 'AssociationsController@undelete')->name('association.undelete');


    });

    Route::prefix('user')->group(function () {
        Route::get('create', 'UsersController@create')->name('user');
        Route::get('{user}', 'UsersController@view')->name('user');
    });

    // FIXME: route series under {association}:
    Route::prefix('series')->middleware('admin.association')->group(function () {
        Route::get('{series}/schedule/create', 'ScheduleController@create')->name('schedule.create');
        Route::post('{series}/schedule/create', 'ScheduleController@store');

        Route::get('{series}/schedules', 'SeriesController@schedules')->name('series.schedules');

        Route::get('{series}/edit', 'SeriesController@edit')->name('series.edit');
        Route::get('create', 'SeriesController@create')->name('series.create');
        Route::post('create', 'SeriesController@store');
        Route::post('update', 'SeriesController@update')->name('series.update');
        Route::get('{series}', 'SeriesController@view')->name('series.view');
        Route::get('delete', 'SeriesController@delete')->name('series.delete');
    });

    // FIXME: route schedule under {association}:
    Route::prefix('schedule')->middleware('admin.association')->group(function () {
        Route::get('{schedule}/round/create', 'RoundsController@create')->name('round.create');
        Route::post('{schedule}/round', 'RoundsController@store')->name('round.store');

        Route::get('{schedule}/round/{round}/edit', 'RoundsController@edit')->name('round.edit');
        Route::post('{schedule}/round/{id}/update', 'RoundsController@update')->name('round.update');

        Route::get('{schedule}/round/{round}/delete-confirm', 'RoundsController@deleteConfirm')->name('round.delete-confirm');
        Route::post('{schedule}/round/{round}/delete', 'RoundsController@destroy')->name('round.delete');

        Route::get('{schedule}/rounds', 'ScheduleController@rounds')->name('schedule.rounds');

        Route::get('{schedule}/edit', 'ScheduleController@edit')->name('schedule.edit');
        Route::post('{schedule}/update', 'ScheduleController@update')->name('schedule.update');
        Route::get('{schedule}', 'ScheduleController@view')->name('schedule.view');
    });

    // FIXME: route results under {association}:
    Route::prefix('results')->middleware('admin.association')->group(function () {
        Route::get('{schedule}/edit', 'ResultsController@edit')->name('results.edit');
        Route::post('{schedule}/update', 'ResultsController@update')->name('results.update');
        Route::get('{association}/results/submissions', 'ResultSubmissionsController@index')->name('result_submissions.list');
        Route::prefix('result_submission')->group(function () {
            Route::post('{id}', 'ResultSubmissionsController@update')->name('result_submission.update');
        });
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

    Route::get('/', 'AdminController@admin')->name('admin');

});

Auth::routes();

Route::get('/', 'AppController@default');
