<script setup>
import { watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';

const props = defineProps({ modelValue: { type: String, default: '' }, placeholder: { type: String, default: 'Tulis konten...' } });
const emit = defineEmits(['update:modelValue']);
const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit],
    editorProps: { attributes: { class: 'min-h-28 px-3 py-3 text-sm text-slate-700 focus:outline-none prose prose-sm max-w-none' } },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML()),
});

watch(() => props.modelValue, (value) => {
    if (editor.value && editor.value.getHTML() !== value) editor.value.commands.setContent(value || '', { emitUpdate: false });
});
</script>

<template>
    <div class="rounded-xl border border-[#c8ddd6] bg-white overflow-hidden focus-within:ring-2 focus-within:ring-[#4a7c6b]/15">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b border-slate-100 bg-slate-50 p-2">
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="editor.isActive('bold') && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn font-bold">B</button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="editor.isActive('italic') && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn italic">I</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="editor.isActive('heading', { level: 2 }) && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn">H2</button>
            <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="editor.isActive('heading', { level: 3 }) && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn">H3</button>
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="editor.isActive('bulletList') && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn">• List</button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="editor.isActive('orderedList') && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn">1. List</button>
            <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="editor.isActive('blockquote') && 'bg-[#dcebe4] text-[#2d4a3e]'" class="editor-btn">Kutipan</button>
            <button type="button" @click="editor.chain().focus().undo().run()" class="editor-btn">↶</button>
            <button type="button" @click="editor.chain().focus().redo().run()" class="editor-btn">↷</button>
        </div>
        <EditorContent :editor="editor" :data-placeholder="placeholder" />
    </div>
</template>

<style scoped>
.editor-btn { min-width: 32px; border-radius: 6px; padding: 5px 8px; font-size: 12px; color: #64748b; }
.editor-btn:hover { background: #eaf2ee; color: #2d4a3e; }
:deep(.tiptap p.is-editor-empty:first-child::before) { color: #94a3b8; content: attr(data-placeholder); float: left; height: 0; pointer-events: none; }
:deep(.tiptap ul) { list-style: disc; padding-left: 1.25rem; }
:deep(.tiptap ol) { list-style: decimal; padding-left: 1.25rem; }
:deep(.tiptap blockquote) { border-left: 3px solid #c8ddd6; padding-left: .75rem; color: #64748b; }
:deep(.tiptap h2) { font-size: 1.2rem; font-weight: 700; }
:deep(.tiptap h3) { font-size: 1.05rem; font-weight: 700; }
</style>
