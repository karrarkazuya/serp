@props(['state'])

@if($state === 'completed')
<div class="absolute top-5 -right-7 w-28 bg-green-500 text-white text-[9px] font-bold uppercase tracking-widest py-1 text-center rotate-45 shadow-sm pointer-events-none">{{ strtoupper(__('workflow.completed_label')) }}</div>
@elseif($state === 'closed')
<div class="absolute top-5 -right-7 w-28 bg-red-500 text-white text-[9px] font-bold uppercase tracking-widest py-1 text-center rotate-45 shadow-sm pointer-events-none">{{ strtoupper(__('workflow.cancelled_label')) }}</div>
@endif
