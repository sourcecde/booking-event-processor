<?php

use Illuminate\Support\Facades\Route;

Route::get('api/booking/{bookingId}', 'BookingController@fetch');
Route::post('api/booking/{bookingId}', 'BookingController@registerEvent');
