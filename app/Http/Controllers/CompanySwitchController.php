<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $context
    ) {}

    public function switch(Request $request)
    {
        $request->validate([
            'companies'   => 'required|array|min:1',
            'companies.*' => 'integer|exists:companies,id',
        ]);

        $this->context->switch($request->companies);

        return redirect()->back()->with('success', 'Company context updated.');
    }
}
