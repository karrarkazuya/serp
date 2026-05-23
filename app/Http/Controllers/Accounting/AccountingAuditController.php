<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountTax;
use App\Models\Chatter\ChatterMessage;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class AccountingAuditController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $accountingModelTypes = [
            AccountMove::class,
            Account::class,
            AccountJournal::class,
            AccountTax::class,
        ];

        $query = ChatterMessage::query()
            ->with(['user', 'creator'])
            ->whereIn('model_type', $accountingModelTypes)
            ->where(function ($q) use ($activeCompanyIds) {
                $q->where(function ($q2) use ($activeCompanyIds) {
                    $q2->where('model_type', AccountMove::class)
                       ->whereIn('model_id', AccountMove::whereIn('company_id', $activeCompanyIds)->select('id'));
                })->orWhere(function ($q2) use ($activeCompanyIds) {
                    $q2->where('model_type', Account::class)
                       ->whereIn('model_id', Account::whereIn('company_id', $activeCompanyIds)->select('id'));
                })->orWhere(function ($q2) use ($activeCompanyIds) {
                    $q2->where('model_type', AccountJournal::class)
                       ->whereIn('model_id', AccountJournal::whereIn('company_id', $activeCompanyIds)->select('id'));
                })->orWhere(function ($q2) use ($activeCompanyIds) {
                    $q2->where('model_type', AccountTax::class)
                       ->whereIn('model_id', AccountTax::whereIn('company_id', $activeCompanyIds)->select('id'));
                });
            })
            ->orderByDesc('created_at');

        // Filter by model type
        if ($modelFilter = $request->query('model_type')) {
            if (in_array($modelFilter, $accountingModelTypes, true)) {
                $query->where('model_type', $modelFilter);
            }
        }

        // Filter by message type
        if ($msgType = $request->query('message_type')) {
            $query->where('message_type', $msgType);
        }

        // Filter by date range
        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Search body
        if ($search = $request->query('search')) {
            $query->where('body', 'like', "%{$search}%");
        }

        $entries = $query->paginate(50)->withQueryString();

        $modelLabels = [
            AccountMove::class    => 'Journal Entry',
            Account::class        => 'Account',
            AccountJournal::class => 'Journal',
            AccountTax::class     => 'Tax',
        ];

        return view('accounting.audit.index', compact('entries', 'modelLabels', 'accountingModelTypes'));
    }
}
