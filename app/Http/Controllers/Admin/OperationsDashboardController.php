<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\OperationsDashboardService;

class OperationsDashboardController extends Controller
{
    public function __construct(protected OperationsDashboardService $operationsService)
    {
    }

    public function index()
    {
        $data = $this->operationsService->getOperationsData();

        return view('admin.operations', $data);
    }
}
