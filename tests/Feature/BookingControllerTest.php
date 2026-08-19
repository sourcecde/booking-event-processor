<?php

namespace Tests\Feature;

use Illuminate\Http\Response;

class BookingControllerTest extends \Tests\TestCase
{
    public function fetchDataProvider(): array
    {
        return [
            'OK1' => [
                'bookingId' => 1,
                'expectedResponse' => ['id' => 1, 'date' => '1111-11-11', 'total_price' => 1111, 'currency' => 'EUR', ],
                'expectedStatusCode' => Response::HTTP_OK,
            ],
            'OK2' => [
                'bookingId' => 2,
                'expectedResponse' => ['id' => 2, 'date' => '2222-22-22', 'total_price' => 2222, 'currency' => 'USD', ],
                'expectedStatusCode' => Response::HTTP_OK,
            ],
            'Not found' => [
                'bookingId' => 3,
                'expectedResponse' => ['error' => '_NOT_FOUND_'],
                'expectedStatusCode' => Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * @param int $bookingId
     * @param array $expectedResponse
     * @param int $expectedStatusCode
     * @dataProvider fetchDataProvider
     */
    public function testFetch(int $bookingId, array $expectedResponse, int $expectedStatusCode)
    {
        $response = $this->get("/api/booking/{$bookingId}");
        $response->assertResponseStatus($expectedStatusCode);
        $response->seeJson($expectedResponse);
    }

    public function registerEventDataProvider(): array
    {
        return [
            'OK1' => [
                'bookingId' => 1,
                'request' => ['event' => 'test'],
                'expectedResponse' => [],
                'expectedStatusCode' => Response::HTTP_CREATED,
            ],
            'VALIDATION1' => [
                'bookingId' => 1,
                'request' => [],
                'expectedResponse' => ['error' => '_VALIDATION_FAILED_'],
                'expectedStatusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            'OK2' => [
                'bookingId' => 2,
                'request' => ['event' => 'test'],
                'expectedResponse' => ['error' => '_INTERNAL_ERROR_'],
                'expectedStatusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'Not found' => [
                'bookingId' => 3,
                'request' => ['event' => 'test'],
                'expectedResponse' => ['error' => '_NOT_FOUND_'],
                'expectedStatusCode' => Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * @param int $bookingId
     * @param array $request
     * @param array $expectedResponse
     * @param int $expectedStatusCode
     * @dataProvider registerEventDataProvider
     */
    public function testRegisterEvent(int $bookingId, array $request, array $expectedResponse, int $expectedStatusCode)
    {
        $response = $this->post("/api/booking/{$bookingId}", $request);
        $response->assertResponseStatus($expectedStatusCode);
        $response->seeJson($expectedResponse);
    }
}
