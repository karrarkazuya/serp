@extends('layouts.app')
@section('title', $account->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\Account::class)
        @php $newHref = route('accounting.accounts.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.accounts.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.accounts.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.accounts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Chart of Accounts</a>
            <span class="text-sm font-semibold text-gray-800">{{ $account->code }} {{ $account->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $account)
                <a href="{{ route('accounting.accounts.edit', $account) }}" class="shrink-0 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @if($account->active)
                <form method="POST" action="{{ route('accounting.accounts.archive', $account) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
                </form>
                @else
                <form method="POST" action="{{ route('accounting.accounts.unarchive', $account) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
                </form>
                @endif
                @endcan
                @can('delete', $account)
                <form method="POST" action="{{ route('accounting.accounts.delete', $account) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this account?</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">Yes</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">Cancel</button>
                    </div>
                </form>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        @if(session('error'))
        <div class="mx-4 mt-4 px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            @if(!$account->active)
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This account is archived.</div>
            </div>
            @endif

            <div class="p-6">
                <div class="text-sm text-gray-600 mb-1">{{ $account->type_label }}</div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $account->code }} <span class="text-gray-700">{{ $account->name }}</span></h1>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            ['Code',     $account->code],
                            ['Type',     $account->type_label],
                            ['Currency', $account->currency ?: '—'],
                            ['Reconcile', $account->reconcile ? 'Yes' : 'No'],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-36 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        @foreach([
                            ['Company',      $account->company?->name],
                            ['Parent Group', $account->parent ? $account->parent->code.' '.$account->parent->name : '—'],
                            ['Children',     $account->children->count()],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-36 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <p class="text-xs font-semibold uppercase text-purple-700">Posted Balance</p>
                            <p class="mt-2 text-3xl font-bold text-purple-900 tabular-nums">{{ number_format($balance, 2) }}</p>
                            <p class="mt-1 text-xs text-purple-600">Debit minus credit · {{ $account->currency ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                @if($account->notes)
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $account->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Accounting\Account"
                :model-id="$account->id"
                :can-comment="auth()->user()->can('comment', $account)"
            />
        </div>
    </div>
</div>
@endsection
