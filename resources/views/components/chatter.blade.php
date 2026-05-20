<div class="bg-white"
     x-data="{
         tab: 'all',
         messages: [],
         loading: true,
         posting: false,
         body: '',
         modelType: @js($modelType),
         modelId: @js($modelId),
         canComment: @js($canComment),
         apiUrl: @js($apiUrl),
         postUrl: @js($postUrl),
         fileBaseUrl: @js($fileBaseUrl),

         async init() {
             await this.fetch();
         },

         async fetch() {
             this.loading = true;
             try {
                 const r = await fetch(
                     `${this.apiUrl}?model_type=${encodeURIComponent(this.modelType)}&model_id=${this.modelId}`,
                     { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                 );
                 if (r.ok) this.messages = await r.json();
             } finally {
                 this.loading = false;
             }
         },

         async post() {
             if (!this.body.trim() || this.posting) return;
             this.posting = true;
             try {
                 const r = await fetch(this.postUrl, {
                     method: 'POST',
                     headers: {
                         'Accept': 'application/json',
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                         'X-Requested-With': 'XMLHttpRequest',
                     },
                     body: JSON.stringify({
                         model_type: this.modelType,
                         model_id: this.modelId,
                         body: this.body,
                         message_type: 'comment',
                     }),
                 });
                 if (r.ok) {
                     const { data } = await r.json();
                     this.messages.unshift(data);
                     this.body = '';
                 }
             } finally {
                 this.posting = false;
             }
         },

         initials(name) {
             if (!name) return '?';
             return name.split(' ').map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
         },

         timeAgo(dateStr) {
             const s = Math.floor((Date.now() - new Date(dateStr)) / 1000);
             if (s < 60)  return 'just now';
             if (s < 3600)  return Math.floor(s / 60)   + 'm ago';
             if (s < 86400) return Math.floor(s / 3600)  + 'h ago';
             if (s < 604800) return Math.floor(s / 86400) + 'd ago';
             return new Date(dateStr).toLocaleDateString();
         },

         visible(msg) {
             return this.tab === 'all' || this.tab === msg.message_type;
         },

         hasChanges(msg) {
             return msg.metadata && msg.metadata.changes && msg.metadata.changes.length > 0;
         },

         isImage(mime) {
             return mime && mime.startsWith('image/');
         },

         fileUrl(msgId, idx, side) {
             return `${this.fileBaseUrl}/${msgId}/file/${idx}/${side}`;
         },
     }">

    {{-- Header --}}
    <div class="flex items-center gap-4 px-6 pt-4 pb-0 border-b border-gray-100">
        <span class="text-sm font-semibold text-gray-700">{{ __('common.activity_tab') }}</span>
        <div class="flex gap-1 ms-auto">
            <button @click="tab = 'all'"
                    :class="tab === 'all' ? 'bg-[#714B67]/10 text-[#714B67]' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.all') }}</button>
            <button @click="tab = 'comment'"
                    :class="tab === 'comment' ? 'bg-[#714B67]/10 text-[#714B67]' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.comments') }}</button>
            <button @click="tab = 'log'"
                    :class="tab === 'log' ? 'bg-[#714B67]/10 text-[#714B67]' : 'text-gray-500 hover:bg-gray-100'"
                    class="px-2.5 py-1 rounded text-xs font-medium transition-colors">{{ __('common.activity_tab') }}</button>
        </div>
    </div>

    {{-- Comment form --}}
    <div x-show="canComment" class="px-6 py-4 border-b border-gray-100">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-[#714B67] flex items-center justify-center text-xs font-bold text-white shrink-0">
                {{ auth()->user()->initials }}
            </div>
            <div class="flex-1">
                <textarea x-model="body" rows="2"
                          placeholder="{{ __('common.write_comment') }}"
                          @keydown.ctrl.enter.prevent="post()"
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#714B67]/30 focus:border-[#714B67] resize-none transition-shadow"
                          :disabled="posting"></textarea>
                <div class="flex justify-end mt-1.5">
                    <button @click="post()"
                            :disabled="!body.trim() || posting"
                            class="px-3 py-1.5 bg-[#714B67] text-white text-xs font-medium rounded-md hover:bg-[#5c3d55] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-text="posting ? '...' : '{{ __('common.send') }}'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Messages --}}
    <div class="px-6 py-4 space-y-4 max-h-96 overflow-y-auto">

        <div x-show="loading" class="text-center py-6">
            <div class="inline-block w-5 h-5 border-2 border-[#714B67]/30 border-t-[#714B67] rounded-full animate-spin"></div>
        </div>

        <template x-if="!loading && messages.length === 0">
            <p class="text-sm text-gray-400 text-center py-4">{{ __('common.no_activity') }}</p>
        </template>

        <template x-for="msg in messages" :key="msg.id">
            <div class="flex gap-3" x-show="visible(msg)">

                {{-- Avatar --}}
                <div class="shrink-0 mt-0.5">
                    <template x-if="msg.user">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                             :class="msg.message_type === 'comment' ? 'bg-[#714B67] text-white' : 'bg-gray-200 text-gray-600'"
                             x-text="initials(msg.user.name)">
                        </div>
                    </template>
                    <template x-if="!msg.user">
                        <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </template>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2 mb-0.5">
                        <span class="text-xs font-semibold text-gray-700"
                              x-text="msg.user ? msg.user.name : '{{ __('common.system_label') }}'"></span>
                        <span class="text-xs text-gray-400" x-text="timeAgo(msg.created_at)"></span>
                        <template x-if="msg.message_type === 'comment'">
                            <span class="ms-auto text-xs px-1.5 py-0.5 bg-[#714B67]/10 text-[#714B67] rounded font-medium">{{ __('common.comment_label') }}</span>
                        </template>
                        <template x-if="msg.message_type === 'system'">
                            <span class="ms-auto text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded font-medium">{{ __('common.system_label') }}</span>
                        </template>
                    </div>

                    <template x-if="hasChanges(msg)">
                        <ul class="space-y-1">
                            <template x-for="(change, i) in msg.metadata.changes" :key="i">
                                <li class="text-sm flex items-center gap-1.5 flex-wrap">
                                    {{-- FROM side --}}
                                    <template x-if="change.from_file_path && isImage(change.from_mime)">
                                        <a :href="fileUrl(msg.id, i, 'from')" target="_blank" class="shrink-0">
                                            <img :src="fileUrl(msg.id, i, 'from')" :alt="change.from"
                                                 class="h-8 w-8 rounded object-cover border border-gray-200">
                                        </a>
                                    </template>
                                    <span class="text-gray-400" x-text="change.from"></span>

                                    <span class="text-gray-300">→</span>

                                    {{-- TO side: image thumbnail --}}
                                    <template x-if="change.to_file_path && isImage(change.to_mime)">
                                        <a :href="fileUrl(msg.id, i, 'to')" target="_blank" class="shrink-0">
                                            <img :src="fileUrl(msg.id, i, 'to')" :alt="change.to"
                                                 class="h-8 w-8 rounded object-cover border border-gray-200">
                                        </a>
                                    </template>
                                    {{-- TO side: linked filename (file change) --}}
                                    <template x-if="change.to_file_path">
                                        <a :href="fileUrl(msg.id, i, 'to')" target="_blank"
                                           class="text-blue-600 font-medium hover:underline"
                                           x-text="change.to"></a>
                                    </template>
                                    {{-- TO side: plain text (non-file change) --}}
                                    <template x-if="!change.to_file_path">
                                        <span class="text-blue-600 font-medium" x-text="change.to"></span>
                                    </template>

                                    <span class="text-gray-400 text-xs" x-text="'(' + change.label + ')'"></span>
                                </li>
                            </template>
                        </ul>
                    </template>
                    <template x-if="!hasChanges(msg)">
                        <p class="text-sm text-gray-600 whitespace-pre-line" x-text="msg.body"></p>
                    </template>
                </div>

            </div>
        </template>
    </div>
</div>
