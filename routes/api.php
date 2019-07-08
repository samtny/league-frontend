<?php

Route::middleware('auth:api')->group(function() {
    Route::get('associations', 'Api\AssociationsController@index')->name('associations');
});
