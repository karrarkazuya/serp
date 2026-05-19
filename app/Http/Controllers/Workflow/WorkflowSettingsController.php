<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkflowSettingsController extends Controller
{
    public function index(Request $request)
    {
        $request->user()->hasPermission('workflow.config.read') || abort(403);

        //to do port ss_workflow res.config.settings fields when workflow-specific settings exist in Laravel.
        return view('workflow.settings.index');
    }
}
