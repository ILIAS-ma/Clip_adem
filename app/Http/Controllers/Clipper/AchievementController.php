<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Services\Clippers\AchievementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function index(Request $request, AchievementService $achievements): View
    {
        return view('clipper.achievements', [
            'achievements' => $achievements->for($request->user()),
        ]);
    }
}
