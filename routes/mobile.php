<?php

use App\NativeComponents\Recorder;
use Illuminate\Support\Facades\Route;

Route::native('/', Recorder::class);
