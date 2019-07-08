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

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/
use App\Association;
use App\Http\Resources\Association as AssociationResource;
use App\Http\Resources\AssociationCollection as AssociationCollectionResource;

Route::middleware('auth:api')->get('/associations', function(Request $request) {
    return new AssociationCollectionResource(Association::all());
});
