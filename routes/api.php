<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\IpIntel\Http\Controllers\LookupController;

/*
| Loaded ONLY when laranail.ip-intel.api.enabled is true. The prefix, version
| and middleware come from config; this file names the endpoints and nothing
| else.
|
| Both are GET and neither writes anything.
*/

Route::get('/me', [LookupController::class, 'me'])->name('laranail.ip-intel.me');
Route::get('/lookup', [LookupController::class, 'show'])->name('laranail.ip-intel.lookup');
