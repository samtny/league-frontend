<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/test', function (Request $request) {

});

use App\Association;
use App\Http\Resources\Association as AssociationResource;
use App\Http\Resources\AssociationCollection as AssociationCollectionResource;

Route::get('/associations', function () {
    return new AssociationCollectionResource(Association::all());
});
