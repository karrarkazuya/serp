@extends('layouts.app')
@section('title', isset($room) ? ($room->isChannel() ? '#' . $room->name : $room->displayName(auth()->user())) . ' — Chat' : 'Chat')

@section('content')
@php
    $auth       = auth()->user();
    $isChannel  = !isset($room) || $room->isChannel();
    $defaultTab = (isset($room) && !$room->isChannel()) ? 'dms' : 'channels';
@endphp
<div class="flex h-full overflow-hidden">

    {{-- ── Left sidebar ── --}}
    <div class="w-64 shrink-0 flex flex-col bg-[#1e1527] overflow-hidden"
         x-data="{ tab: '{{ $defaultTab }}', newRoom: false, newDm: false, dmSearch: '' }">

        {{-- Sidebar header --}}
        <div class="px-4 py-4 border-b border-white/10 shrink-0">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="text-xs font-semibold text-white/40 uppercase tracking-widest mb-0.5">Workspace</div>
                    <div class="text-white font-bold text-sm">Chat</div>
                </div>
                {{-- Context-sensitive add button --}}
                <button @click="tab === 'channels' ? (newRoom = !newRoom, newDm = false) : (newDm = !newDm, newRoom = false)"
                        class="w-6 h-6 rounded flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-colors"
                        title="New">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>

            {{-- Tab switcher --}}
            <div class="flex rounded-lg bg-white/8 p-0.5 gap-0.5">
                <button @click="tab = 'channels'"
                        class="flex-1 text-xs font-semibold py-1 rounded-md transition-colors"
                        :class="tab === 'channels' ? 'bg-white/20 text-white' : 'text-white/45 hover:text-white/70'">
                    Channels
                </button>
                <button @click="tab = 'dms'"
                        class="flex-1 text-xs font-semibold py-1 rounded-md transition-colors flex items-center justify-center gap-1"
                        :class="tab === 'dms' ? 'bg-white/20 text-white' : 'text-white/45 hover:text-white/70'">
                    Direct
                    @php $totalUnread = array_sum($unreadCounts ?? []); @endphp
                    @if($totalUnread > 0)
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                        {{ $totalUnread > 9 ? '9+' : $totalUnread }}
                    </span>
                    @endif
                </button>
            </div>
        </div>

        {{-- New channel form --}}
        <div x-show="newRoom" x-transition style="display:none"
             class="px-3 py-3 border-b border-white/10 shrink-0">
            <form method="POST" action="{{ route('chat.rooms.create') }}">
                @csrf
                <div class="mb-2">
                    <input type="text" name="name" required maxlength="100" placeholder="Channel name"
                           class="w-full text-xs bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-white/40">
                </div>
                <div class="mb-2">
                    <input type="text" name="description" maxlength="255" placeholder="Description (optional)"
                           class="w-full text-xs bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-white/40">
                </div>
                <div class="flex gap-1.5">
                    <button type="submit"
                            class="flex-1 py-1.5 text-xs font-semibold bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors">
                        Create
                    </button>
                    <button type="button" @click="newRoom = false"
                            class="px-3 py-1.5 text-xs text-white/40 hover:text-white/70 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        {{-- New DM: user picker --}}
        <div x-show="newDm" x-transition style="display:none"
             class="px-3 py-3 border-b border-white/10 shrink-0">
            <input type="text" x-model="dmSearch" placeholder="Search people…"
                   class="w-full text-xs bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-white/40 mb-2">
            <div class="max-h-40 overflow-y-auto space-y-0.5">
                @foreach($users as $u)
                @if($u->id !== $auth->id)
                <form method="POST" action="{{ route('chat.direct', $u) }}"
                      x-show="dmSearch === '' || '{{ strtolower($u->name) }}'.includes(dmSearch.toLowerCase())"
                      style="display:none">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/10 text-left transition-colors">
                        <div class="w-6 h-6 rounded-full bg-[#714B67] flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                            {{ strtoupper(substr($u->name, 0, 1)) }}
                        </div>
                        <span class="text-xs text-white/75 truncate">{{ $u->name }}</span>
                    </button>
                </form>
                @endif
                @endforeach
            </div>
        </div>

        {{-- ── Channels tab ── --}}
        <div x-show="tab === 'channels'" class="flex-1 overflow-y-auto py-2" style="display:none">
            <div class="px-3 mb-1">
                <span class="text-[10px] font-bold text-white/30 uppercase tracking-widest">Channels</span>
            </div>
            @forelse($channels as $r)
            <a href="{{ route('chat.show', $r) }}"
               class="flex items-start gap-2.5 px-3 py-2 mx-2 rounded-lg transition-colors group
                      {{ isset($room) && $room->id === $r->id ? 'bg-white/15 text-white' : 'text-white/55 hover:bg-white/8 hover:text-white/80' }}">
                <span class="text-lg leading-none mt-0.5 shrink-0 font-bold
                             {{ isset($room) && $room->id === $r->id ? 'text-white/60' : 'text-white/25 group-hover:text-white/40' }}">#</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $r->name }}</div>
                    @if($r->lastMessage)
                    <div class="text-xs truncate text-white/35 mt-0.5">
                        @if($r->lastMessage->user)<span class="font-medium">{{ $r->lastMessage->user->name }}:</span> @endif
                        {{ Str::limit($r->lastMessage->body ?? '📎 file', 28) }}
                    </div>
                    @endif
                </div>
            </a>
            @empty
            <div class="px-4 py-6 text-center text-white/30 text-xs">No channels yet.<br>Create one with +</div>
            @endforelse
        </div>

        {{-- ── DMs tab ── --}}
        <div x-show="tab === 'dms'" class="flex-1 overflow-y-auto py-2" style="display:none">
            <div class="px-3 mb-1">
                <span class="text-[10px] font-bold text-white/30 uppercase tracking-widest">Direct Messages</span>
            </div>
            @forelse($dms as $r)
            @php
                $displayName = $r->displayName($auth);
                $unread = $unreadCounts[$r->id] ?? 0;
                $isActive = isset($room) && $room->id === $r->id;
                $initial = strtoupper(substr($displayName, 0, 1));
            @endphp
            <a href="{{ route('chat.show', $r) }}"
               class="flex items-center gap-2.5 px-3 py-2 mx-2 rounded-lg transition-colors group
                      {{ $isActive ? 'bg-white/15 text-white' : 'text-white/55 hover:bg-white/8 hover:text-white/80' }}">
                {{-- Avatar --}}
                <div class="w-7 h-7 rounded-full bg-[#714B67]/70 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    {{ $initial }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-semibold truncate {{ $unread > 0 ? 'text-white' : '' }}">{{ $displayName }}</span>
                        @if($r->isGroup())
                        <span class="text-[10px] text-white/30 shrink-0">group</span>
                        @endif
                    </div>
                    @if($r->lastMessage)
                    <div class="text-xs truncate text-white/35 mt-0.5">
                        {{ Str::limit($r->lastMessage->body ?? '📎 file', 28) }}
                    </div>
                    @endif
                </div>
                @if($unread > 0)
                <span class="shrink-0 min-w-4.5 h-4.5 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
                @endif
            </a>
            @empty
            <div class="px-4 py-6 text-center text-white/30 text-xs">No direct messages yet.<br>Start one with +</div>
            @endforelse
        </div>
    </div>

    {{-- ── Right: chat area ── --}}
    @if(isset($room))
    @php
        $roomDisplayName = $room->isChannel() ? $room->name : $room->displayName($auth);
    @endphp
    <div class="flex-1 min-w-0 flex flex-col bg-white overflow-hidden">

        {{-- Header --}}
        <div class="shrink-0 px-6 py-3.5 border-b border-gray-100 flex items-center gap-3 bg-white shadow-sm">
            @if($room->isChannel())
            <span class="text-2xl font-bold text-gray-200 leading-none">#</span>
            @else
            <div class="w-8 h-8 rounded-full bg-[#714B67]/20 flex items-center justify-center text-[#714B67] text-sm font-bold shrink-0">
                {{ strtoupper(substr($roomDisplayName, 0, 1)) }}
            </div>
            @endif
            <div>
                <div class="text-base font-bold text-gray-900">{{ $roomDisplayName }}</div>
                @if($room->isChannel() && $room->description)
                <div class="text-xs text-gray-400">{{ $room->description }}</div>
                @elseif(!$room->isChannel())
                <div class="text-xs text-gray-400">
                    {{ $room->isGroup() ? 'Group · ' : '' }}{{ $room->members->pluck('name')->join(', ') }}
                </div>
                @endif
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-0.5" id="chat-messages">

            @if(empty($grouped))
            <div class="flex flex-col items-center justify-center h-full text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-[#714B67]/10 flex items-center justify-center mb-4">
                    @if($room->isChannel())
                    <svg class="w-8 h-8 text-[#714B67]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    @else
                    <svg class="w-8 h-8 text-[#714B67]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    @endif
                </div>
                @if($room->isChannel())
                <div class="text-lg font-bold text-gray-800 mb-1"># {{ $room->name }}</div>
                <div class="text-sm text-gray-400">This is the start of the <span class="font-semibold text-gray-600">#{{ $room->name }}</span> channel.</div>
                @else
                <div class="text-lg font-bold text-gray-800 mb-1">{{ $roomDisplayName }}</div>
                <div class="text-sm text-gray-400">This is the beginning of your conversation with <span class="font-semibold text-gray-600">{{ $roomDisplayName }}</span>.</div>
                @endif
                <div class="text-xs text-gray-300 mt-1">Be the first to say something!</div>
            </div>
            @else

            @foreach($grouped as $item)
            @php $msg = $item['message']; $isOwn = $msg->user_id === $auth->id; @endphp

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
                <div class="w-9 shrink-0">
                    @if($item['show_header'])
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white
                                {{ $isOwn ? 'bg-[#714B67]' : 'bg-gray-400' }}">
                        {{ $msg->user ? strtoupper(substr($msg->user->name, 0, 1)) : '?' }}
                    </div>
                    @endif
                </div>
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
                    <p class="text-sm text-gray-800 leading-relaxed wrap-break-word">{{ $msg->body }}</p>
                    @endif
                    @if($msg->files->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($msg->files as $f)
                        @if($f->isImage())
                        <a href="{{ route('chat.file', [$room, $f]) }}" target="_blank"
                           class="block rounded-xl overflow-hidden border border-gray-100 hover:border-[#714B67]/30 transition-colors shadow-sm">
                            <img src="{{ route('chat.file', [$room, $f]) }}" alt="{{ $f->original_name }}"
                                 class="max-w-xs max-h-48 object-cover block">
                        </a>
                        @else
                        <a href="{{ route('chat.file', [$room, $f]) }}"
                           class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 hover:bg-[#714B67]/5 border border-gray-200 hover:border-[#714B67]/30 rounded-xl transition-colors group/file max-w-xs">
                            <div class="w-8 h-8 rounded-lg bg-[#714B67]/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#714B67]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.286 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
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
                              if (isImg) { const r = new FileReader(); r.onload = ev => { entry.url = ev.target.result; }; r.readAsDataURL(f); }
                              this.previews.push(entry);
                          });
                      },
                      clearFiles() { this.previews = []; this.$refs.fileInput.value = ''; },
                      humanSize(b) { if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }
                  }">
                @csrf

                {{-- Unified composer box --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 focus-within:border-[#714B67]/40 focus-within:ring-2 focus-within:ring-[#714B67]/15 transition-all">

                    {{-- File previews (inside box) --}}
                    <div x-show="previews.length > 0" x-transition style="display:none"
                         class="flex flex-wrap gap-2 px-3 pt-3">
                        <template x-for="(f, i) in previews" :key="i">
                            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs">
                                <template x-if="f.isImg"><img :src="f.url" class="w-7 h-7 rounded object-cover shrink-0"></template>
                                <template x-if="!f.isImg">
                                    <svg class="w-4 h-4 text-[#714B67] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.286 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </template>
                                <span class="text-gray-700 font-medium max-w-25 truncate" x-text="f.name"></span>
                                <span class="text-gray-400" x-text="humanSize(f.size)"></span>
                                <button type="button" @click="previews.splice(i,1)" class="text-gray-300 hover:text-red-400 ml-0.5">&times;</button>
                            </div>
                        </template>
                    </div>

                    {{-- Textarea --}}
                    <textarea name="body" rows="3"
                              placeholder="Message {{ $room->isChannel() ? '#' . $room->name : $roomDisplayName }}"
                              class="w-full text-sm bg-transparent border-0 px-4 pt-3 pb-2 focus:outline-none focus:ring-0 resize-none leading-relaxed placeholder-gray-400"
                              style="min-height: 80px; max-height: 200px;"
                              @keydown.enter.prevent.exact="$el.closest('form').requestSubmit()"
                              @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'"></textarea>

                    {{-- Bottom toolbar --}}
                    <div class="flex items-center justify-between px-3 pb-3">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="$refs.fileInput.click()"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-[#714B67] hover:bg-[#714B67]/8 transition-colors"
                                    title="Attach file (images, PDFs, Office docs — max 10 MB)">
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                            </button>
                            <input type="file" name="files[]" multiple x-ref="fileInput" @change="handleFiles"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" class="hidden">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-300">Enter to send</span>
                            <button type="submit"
                                    class="w-8 h-8 rounded-lg bg-[#714B67] hover:bg-[#5c3d55] text-white flex items-center justify-center transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @else
    <div class="flex-1 flex flex-col items-center justify-center bg-white text-center p-8">
        <div class="w-20 h-20 rounded-2xl bg-[#714B67]/10 flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-[#714B67]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Welcome to Chat</h2>
        <p class="text-sm text-gray-400">Select a channel or start a direct message.</p>
    </div>
    @endif

</div>

<script>
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
</script>
@endsection
