<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AcademyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Academy/Index');
    }

    public function salesTraining(): Response
    {
        return Inertia::render('Academy/SalesTraining');
    }
}
