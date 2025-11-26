<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Licensing\LicenseEngine;
use Illuminate\Http\Request;

class LicenseValidationController extends Controller
{
    protected $engine;

    public function __construct(LicenseEngine $engine)
    {
        $this->engine = $engine;
    }

    public function validateLicense(Request $request)
    {
        $request->validate(['license_key' => 'required|string']);

        $result = $this->engine->validate(
            $request->license_key,
            $request->input('fingerprint'),
            $request->ip()
        );

        return response()->json($result);
    }
}
