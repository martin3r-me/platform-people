<?php

use Illuminate\Support\Facades\Route;
use Platform\People\Livewire\Dashboard;

// Modul-Dashboard (Startseite)
Route::get('/', Dashboard::class)->name('people.dashboard');
