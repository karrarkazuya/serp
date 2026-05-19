@extends('layouts.app')
@section('title', isset($room) ? $room->name . ' — Chat' : 'Chat')

@section('content')
<div class="flex h-full overflow-hidden">

    {{-- ── Left sidebar: rooms ── --}}
    <div class="w-64 shrink-0 flex flex-col bg-[#1e1527] overflow-hidden">

        {{-- Sidebar header --}}
        <div class="px-4 py-4 border-b border-white/10 flex items-center justify-between shrink-0">
            <div>
                <div class="text-xs font-semibold text-white/40 uppercase tracking-widest mb-0.5">Workspace</div>
                <div class="text-white font-bold text-sm">Chat</div>
            </div>
            {{-- New room trigger --}}
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button @click="open = !open"
                        class="w-6 h-6 rounded flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-colors"
                        title="New room">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                <div x-show="open" x-transition style="display:none"
                     class="absolute left-0 top-full mt-2 w-64 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 p-4">
                    <form method="POST" action="{{ route('chat.rooms.create') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Room name</label>
                            <input type="text" name="name" required maxlength="100" placeholder="e.g. general"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#714B67]">
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Description <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="description" maxlength="255" placeholder="What's this room for?"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#714B67]">
                        </div>
                        <button type="submit"
                                class="w-full py-2 text-sm font-semibold bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg transition-colors">
                            Create Room
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Room list --}}
        <div class="flex-1 overflow-y-auto py-2">
            @forelse($rooms as $r)
            <a href="{{ route('chat.show', $r) }}"
               class="flex items-start gap-2.5 px-3 py-2 mx-2 rounded-lg transition-colors group
                      {{ isset($room) && $room->id === $r->id ? 'bg-white/15 text-white' : 'text-white/55 hover:bg-white/8 hover:text-white/80' }}">
                <span class="text-lg leading-none mt-0.5 shrink-0 font-bold
                             {{ isset($room) && $room->id === $r->id ? 'text-white/60' : 'text-white/25 group-hover:text-white/40' }}">#</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate {{ isset($room) && $room->id === $r->id ? 'text-white' : '' }}">{{ $r->name }}</div>
                    @if($r->lastMessage)
                    <div class="text-xs truncate text-white/35 mt-0.5">
                        @if($r->lastMessage->user)<span class="font-medium">{{ $r->lastMessage->user->name }}:</span> @endif
                        {{ Str::limit($r->lastMessage->body ?? '📎 file', 28) }}
                    </div>
                    @endif
                </div>
            </a>
            @empty
            <div class="px-4 py-6 text-center text-white/30 text-xs">No rooms yet.<br>Create one above.</div>
            @endforelse
        </div>
    </div>

    {{-- ── Right: chat area ── --}}
    @if(isset($room))
    <div class="flex-1 min-w-0 flex flex-col bg-white overflow-hidden">

        {{-- Channel header --}}
        <div class="shrink-0 px-6 py-3.5 border-b border-gray-100 flex items-center gap-3 bg-white shadow-sm">
            <span class="text-2xl font-bold text-gray-200 leading-none">#</span>
            <div>
                <div class="text-base font-bold text-gray-900">{{ $room->name }}</div>
                @if($room->description)
                <div class="text-xs text-gray-400">{{ $room->description }}</div>
                @endif
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-0.5" id="chat-messages">

            @if(empty($grouped))
            <div class="flex flex-col items-center justify-center h-full text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-[#714B67]/10 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-[#714B67]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div class="text-lg font-bold text-gray-800 mb-1"># {{ $room->name }}</div>
                <div class="text-sm text-gray-400">This is the start of the <span class="font-semibold text-gray-600">#{{ $room->name }}</span> channel.</div>
                <div class="text-xs text-gray-300 mt-1">Be the first to say something!</div>
            </div>
            @else

            @foreach($grouped as $item)
            @php $msg = $item['message']; $isOwn = $msg->user_id === auth()->id(); @endphp

            {{-- Date separator --}}
            @if($item['show_date'])
            <div class="flex items-center gap-3 py-4 {{ $loop->first ? '' : 'mt-4' }}">
                <div class="flex-1 h-px bg-gray-100"></div>
                <span class="text-xs font-semibold text-gray-400 bg-white px-2">{{ $item['date_label'] }}</span>
                <div class="flex-1 h-px bg-gray-100"></div>
            </div>
            @endif

            {{-- Message row --}}
            <div class="flex gap-3 {{ $item['show_header'] ? 'mt-3' : 'mt-0.5' }} group">

                {{-- Avatar (only on header row) --}}
                <div class="w-9 shrink-0">
                    @if($item['show_header'])
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white
                                {{ $isOwn ? 'bg-[#714B67]' : 'bg-gray-400' }}">
                        {{ $msg->user ? strtoupper(substr($msg->user->name, 0, 1)) : '?' }}
                    </div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    @if($item['show_header'])
                    <div class="flex items-baseline gap-2 mb-1">
                        <span class="text-sm font-bold {{ $isOwn ? 'text-[#714B67]' : 'text-gray-900' }}">
                            {{ $msg->user?->name ?? 'Deleted User' }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $msg->created_at->format('g:i A') }}</span>
                    </div>
                    @endif

                    @if($msg->body)
                    <p class="text-sm text-gray-800 leading-relaxed break-words">{{ $msg->body }}</p>
                    @endif

                    {{-- Attachments --}}
                    @if($msg->files->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($msg->files as $f)
                        @if($f->isImage())
                        <a href="{{ route('chat.file', [$room, $f]) }}" target="_blank"
                           class="block rounded-xl overflow-hidden border border-gray-100 hover:border-[#714B67]/30 transition-colors shadow-sm">
                            <img src="{{ route('chat.file', [$room, $f]) }}"
                                 alt="{{ $f->original_name }}"
                                 class="max-w-xs max-h-48 object-cover block">
                        </a>
                        @else
                        <a href="{{ route('chat.file', [$room, $f]) }}"
                           class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 hover:bg-[#714B67]/5 border border-gray-200 hover:border-[#714B67]/30 rounded-xl transition-colors group/file max-w-xs">
                            <div class="w-8 h-8 rounded-lg bg-[#714B67]/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#714B67]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-gray-800 truncate">{{ $f->original_name }}</div>
                                <div class="text-xs text-gray-400">{{ $f->humanSize() }}</div>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 group-hover/file:text-[#714B67] shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                        @endif
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
            @endif

        </div>

        {{-- Message input --}}
        <div class="shrink-0 px-6 py-4 border-t border-gray-100 bg-white">
            <form method="POST" action="{{ route('chat.store', $room) }}"
                  enctype="multipart/form-data"
                  x-data="{
                      previews: [],
                      handleFiles(e) {
                          this.previews = [];
                          const allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/plain','text/csv'];
                          Array.from(e.target.files).forEach(f => {
                              if (!allowed.includes(f.type)) return;
                              if (f.size > 10 * 1024 * 1024) return;
                              const isImg = f.type.startsWith('image/');
                              const entry = { name: f.name, size: f.size, isImg, url: '' };
                              if (isImg) {
                                  const reader = new FileReader();
                                  reader.onload = ev => { entry.url = ev.target.result; };
                                  reader.readAsDataURL(f);
                              }
                              this.previews.push(entry);
                          });
                      },
                      clearFiles() { this.previews = []; this.$refs.fileInput.value = ''; },
                      humanSize(b) { if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }
                  }">
                @csrf

                {{-- File previews --}}
                <div x-show="previews.length > 0" x-transition style="display:none"
                     class="flex flex-wrap gap-2 mb-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <template x-for="(f, i) in previews" :key="i">
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs">
                            <template x-if="f.isImg">
                                <img :src="f.url" class="w-8 h-8 rounded object-cover shrink-0">
                            </template>
                            <template x-if="!f.isImg">
                                <svg class="w-4 h-4 text-[#714B67] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </template>
                            <span class="text-gray-700 font-medium max-w-[100px] truncate" x-text="f.name"></span>
                            <span class="text-gray-400" x-text="humanSize(f.size)"></span>
                        </div>
                    </template>
                    <button type="button" @click="clearFiles()" class="text-xs text-gray-400 hover:text-red-500 ml-auto self-center px-1">
                        Clear all
                    </button>
                </div>

                <div class="flex items-end gap-3">
                    {{-- Attach button --}}
                    <button type="button" @click="$refs.fileInput.click()"
                            class="shrink-0 w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:text-[#714B67] hover:border-[#714B67]/30 hover:bg-[#714B67]/5 transition-colors"
                            title="Attach file (images, PDF, Office docs, text — max 10 MB)">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                    </button>
                    <input type="file" name="files[]" multiple x-ref="fileInput"
                           @change="handleFiles"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv"
                           class="hidden">

                    {{-- Text area --}}
                    <div class="flex-1 relative">
                        <textarea name="body" rows="1" placeholder="Message #{{ $room->name }}"
                                  class="w-full text-sm bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-12 focus:outline-none focus:ring-2 focus:ring-[#714B67]/30 focus:border-[#714B67]/50 resize-none transition-shadow leading-relaxed"
                                  @keydown.enter.prevent.exact="$el.closest('form').requestSubmit()"
                                  @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"></textarea>
                        <button type="submit"
                                class="absolute right-2 bottom-2 w-8 h-8 rounded-lg bg-[#714B67] hover:bg-[#5c3d55] text-white flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-300 text-center">
                    Enter to send &nbsp;·&nbsp; Attach images, PDFs, Office docs &nbsp;·&nbsp; Max 10 MB per file
                </div>
            </form>
        </div>
    </div>

    @else
    {{-- No room selected / no rooms exist --}}
    <div class="flex-1 flex flex-col items-center justify-center bg-white text-center p-8">
        <div class="w-20 h-20 rounded-2xl bg-[#714B67]/10 flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-[#714B67]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">No rooms yet</h2>
        <p class="text-sm text-gray-400 mb-6">Create your first room using the <span class="font-semibold text-gray-600">+</span> button in the sidebar.</p>
    </div>
    @endif

</div>

<script>
    // Auto-scroll messages to bottom on page load
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
</script>
@endsection
