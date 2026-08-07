<?php

use Illuminate\Support\Facades\Route;
use Platform\People\Livewire\Dashboard;
use Platform\People\Livewire\Employee\Index as EmployeeIndex;
use Platform\People\Livewire\Skill\Index as SkillIndex;

// Modul-Dashboard (Startseite)
Route::get('/', Dashboard::class)->name('people.dashboard');

// Mitarbeiter
Route::get('/employees', EmployeeIndex::class)->name('people.employees.index');

// Skills (Katalog + Matrix)
Route::get('/skills', SkillIndex::class)->name('people.skills.index');
