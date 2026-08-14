<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Fasa 4 — membolehkan $this->authorize() digunakan dalam controller
    // sebagai lapisan kawalan akses kedua selepas middleware route.
    use AuthorizesRequests;
}
