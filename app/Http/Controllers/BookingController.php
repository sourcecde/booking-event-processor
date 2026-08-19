<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public const LIST_OF_BOOKINGS = [
        1 => ['id' => 1, 'date' => '1111-11-11', 'total_price' => 1111, 'currency' => 'EUR', ],
        2 => ['id' => 2, 'date' => '2222-22-22', 'total_price' => 2222, 'currency' => 'USD', ],
    ];

    public function fetch(int $bookingId)
    {
        if (empty(self::LIST_OF_BOOKINGS[$bookingId])) {
            return response(['error' => '_NOT_FOUND_'], Response::HTTP_NOT_FOUND);
        }

        return response(self::LIST_OF_BOOKINGS[$bookingId]);
    }


    public function registerEvent(int $bookingId, Request $request)
    {
        if (empty(self::LIST_OF_BOOKINGS[$bookingId])) {
            return response(['error' => '_NOT_FOUND_'], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->post(), [
            'event' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['error' => '_VALIDATION_FAILED_'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (2 === $bookingId) {
            return response(['error' => '_INTERNAL_ERROR_'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response([], Response::HTTP_CREATED);
    }
}
