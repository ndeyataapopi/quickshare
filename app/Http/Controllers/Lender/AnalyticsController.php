<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index()
    {
        return view('client.analytics');
    }
}
