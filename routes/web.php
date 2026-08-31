<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')->name('dashboard');
Route::livewire('/print-jobs/new', 'pages::print-jobs.create')->name('print-jobs.create');
