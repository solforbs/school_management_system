<?php

use Illuminate\Support\Facades\Http;
use Itsmurumba\Hostpinnacle\Exceptions\IsNullException;
use Itsmurumba\Hostpinnacle\Hostpinnacle;

beforeEach(function () {
    hostpinnacle_set_config();
});

test('sends post with file when only file required', function () {
    Http::fake([
        'https://api.hostpinnacle.test/send' => Http::response(['status' => 'success'], 200),
    ]);

    $file = hostpinnacle_mock_file("Phone,Message\n254700000000,Hi\n");
    $hostpinnacle = new Hostpinnacle;
    $response = $hostpinnacle->sendMobileAndMessageFileSMS(['file' => $file]);

    expect($response->successful())->toBeTrue();
    $file->close();
});

test('throws IsNullException when file is missing', function () {
    $hostpinnacle = new Hostpinnacle;

    $hostpinnacle->sendMobileAndMessageFileSMS([]);
})->throws(IsNullException::class, 'file must not be null');
