@props(['dateFrom' => null, 'dateTo' => null, 'showJournal' => false, 'journals' => collect(), 'journalId' => null, 'showAccount' => false, 'accounts' => collect(), 'accountId' => null, 'showAsOf' => false, 'asOf' => null])

<form method="GET" class="mb-5 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
    @if($showAsOf)
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">As Of</label>
        <input type="date" name="as_of" value="{{ $asOf ?? now()->toDateString() }}"
               class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
    </div>
    @else
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
        <input type="date" name="date_from" value="{{ $dateFrom }}"
               class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
        <input type="date" name="date_to" value="{{ $dateTo }}"
               class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
    </div>
    @endif

    @if($showJournal)
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Journal</label>
        <select name="journal_id" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
            <option value="">All Journals</option>
            @foreach($journals as $journal)
            <option value="{{ $journal->id }}" {{ $journalId == $journal->id ? 'selected' : '' }}>
                {{ $journal->code }} – {{ $journal->name }}
            </option>
            @endforeach
        </select>
    </div>
    @endif

    @if($showAccount)
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Account</label>
        <select name="account_id" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 max-w-xs">
            <option value="">All Accounts</option>
            @foreach($accounts as $account)
            <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                {{ $account->code }} {{ $account->name }}
            </option>
            @endforeach
        </select>
    </div>
    @endif

    <button type="submit" class="px-4 py-1.5 bg-purple-600 text-white text-sm font-medium rounded hover:bg-purple-700">Apply</button>
    <a href="{{ url()->current() }}" class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Reset</a>
</form>
