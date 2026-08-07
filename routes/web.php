<?php

use Illuminate\Support\Facades\Route;
use Platform\People\Livewire\Dashboard;
use Platform\People\Livewire\Employee\Index as EmployeeIndex;
use Platform\People\Livewire\Employee\Show as EmployeeShow;
use Platform\People\Livewire\JobProfile\Index as JobProfileIndex;
use Platform\People\Livewire\JobProfile\Show as JobProfileShow;
use Platform\People\Livewire\Skill\Index as SkillIndex;

// Modul-Dashboard (Startseite)
Route::get('/', Dashboard::class)->name('people.dashboard');

// Mitarbeiter
Route::get('/employees', EmployeeIndex::class)->name('people.employees.index');
Route::get('/employees/{employee}', EmployeeShow::class)->name('people.employees.show');

// Jobprofile
Route::get('/job-profiles', JobProfileIndex::class)->name('people.job-profiles.index');
Route::get('/job-profiles/{jobProfile}', JobProfileShow::class)->name('people.job-profiles.show');

// Skills (Katalog + Matrix)
Route::get('/skills', SkillIndex::class)->name('people.skills.index');
