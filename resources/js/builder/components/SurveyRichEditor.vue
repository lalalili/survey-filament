<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import { mergeAttributes, Node } from '@tiptap/core';
import { ref, watch, onBeforeUnmount } from 'vue';
import { SurveyVideoNode } from './SurveyVideoNode';
import { parseVideoUrl } from '../utils/videoUrl';

export interface RichEditorVariableToken {
  label: string;
  token: string;
  description?: string;
}

const VariableTokenNode = Node.create({
  name: 'variableToken',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: false,

  addAttributes() {
    return {
      token: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-variable-token') ?? '',
        renderHTML: (attributes) => ({ 'data-variable-token': attributes.token }),
      },
      label: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-variable-label') ?? '',
        renderHTML: (attributes) => ({ 'data-variable-label': attributes.label }),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'span[data-variable-token]' }];
  },

  renderHTML({ HTMLAttributes }) {
    const token = String(HTMLAttributes['data-variable-token'] ?? '');
    const label = String(HTMLAttributes['data-variable-label'] ?? token);
    const code = token.replace(/^\{\{\s*/, '').replace(/\s*\}\}$/, '');

    return ['span', mergeAttributes(HTMLAttributes, {
      class: 'survey-variable-token',
      contenteditable: 'false',
    }), label, ['code', {}, code]];
  },
});

const props = withDefaults(defineProps<{
  modelValue: string;
  placeholder?: string;
  uploadUrl?: string;
  csrfToken?: string;
  variableTokens?: RichEditorVariableToken[];
}>(), {
  placeholder: '請輸入內容…',
  uploadUrl: '',
  csrfToken: '',
  variableTokens: () => [],
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const PRESET_COLORS = [
  '#000000', '#374151', '#6b7280', '#9ca3af',
  '#ef4444', '#f97316', '#eab308', '#22c55e',
  '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6',
];

const colorPickerOpen = ref(false);
const videoModalOpen = ref(false);
const videoUrlInput = ref('');
const videoError = ref('');
const imageFileInput = ref<HTMLInputElement | null>(null);
const imageUploading = ref(false);
const imageError = ref('');
const variableMenuOpen = ref(false);

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      link: false,
      underline: false,
    }),
    TextStyle,
    Color,
    Underline,
    Link.configure({ openOnClick: false, autolink: false, linkOnPaste: false }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Placeholder.configure({ placeholder: props.placeholder }),
    Image.configure({ inline: false, allowBase64: false }),
    VariableTokenNode,
    SurveyVideoNode,
  ],
  onUpdate({ editor }) {
    const html = editor.getHTML();
    emit('update:modelValue', html === '<p></p>' ? '' : html);
  },
});

watch(() => props.modelValue, (newVal) => {
  if (!editor.value) return;
  const current = editor.value.getHTML();
  const normalized = current === '<p></p>' ? '' : current;
  if (newVal !== normalized) {
    editor.value.commands.setContent(newVal || '', false);
  }
});

onBeforeUnmount(() => editor.value?.destroy());

function setColor(color: string) {
  editor.value?.chain().focus().setColor(color).run();
  colorPickerOpen.value = false;
}

function unsetColor() {
  editor.value?.chain().focus().unsetColor().run();
  colorPickerOpen.value = false;
}

function setLink() {
  const prev = editor.value?.getAttributes('link').href ?? '';
  const url = window.prompt('連結 URL', prev);
  if (url === null) return;
  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
  } else {
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }
}

function insertVariableToken(variable: RichEditorVariableToken) {
  editor.value
    ?.chain()
    .focus()
    .insertContent({
      type: 'variableToken',
      attrs: { token: variable.token, label: variable.label },
    })
    .insertContent(' ')
    .run();
  variableMenuOpen.value = false;
}

const currentColor = () => editor.value?.getAttributes('textStyle').color ?? null;

function triggerImageUpload() {
  imageError.value = '';
  imageFileInput.value?.click();
}

async function onImageFileSelected(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  if (!allowedTypes.includes(file.type)) {
    imageError.value = '只允許 JPG、PNG、WebP、GIF 格式';
    (event.target as HTMLInputElement).value = '';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    imageError.value = '圖片不可超過 5 MB';
    (event.target as HTMLInputElement).value = '';
    return;
  }

  if (!props.uploadUrl) {
    imageError.value = '上傳端點未設定';
    return;
  }

  imageUploading.value = true;
  imageError.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(props.uploadUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': props.csrfToken,
        Accept: 'application/json',
      },
      body: formData,
    });

    if (!response.ok) {
      const json = await response.json().catch(() => ({}));
      imageError.value = (json as any)?.message ?? '上傳失敗，請再試一次';
      return;
    }

    const json = await response.json() as { url: string };
    editor.value?.chain().focus().setImage({ src: json.url }).run();
  } catch {
    imageError.value = '上傳失敗，請再試一次';
  } finally {
    imageUploading.value = false;
    (event.target as HTMLInputElement).value = '';
  }
}

function openVideoModal() {
  videoUrlInput.value = '';
  videoError.value = '';
  videoModalOpen.value = true;
}

function insertVideo() {
  const parsed = parseVideoUrl(videoUrlInput.value);
  if (!parsed) {
    videoError.value = '不支援的影片連結，請貼上 YouTube 或 Vimeo 網址';
    return;
  }
  editor.value
    ?.chain()
    .focus()
    .insertContent({ type: 'surveyVideo', attrs: { src: parsed.src, provider: parsed.provider } })
    .run();
  videoModalOpen.value = false;
}
</script>

<template>
  <div class="sre-wrap">
    <div v-if="editor" class="sre-toolbar">
      <!-- Text format -->
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('bold') }" title="粗體" @click="editor.chain().focus().toggleBold().run()"><strong>B</strong></button>
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('italic') }" title="斜體" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('underline') }" title="底線" @click="editor.chain().focus().toggleUnderline().run()"><u>U</u></button>

      <div class="sre-divider"></div>

      <!-- Heading -->
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('heading', { level: 2 }) }" title="標題" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('heading', { level: 3 }) }" title="子標題" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>

      <div class="sre-divider"></div>

      <!-- Alignment -->
      <button type="button" class="sre-btn" :class="{ active: editor.isActive({ textAlign: 'left' }) }" title="靠左" @click="editor.chain().focus().setTextAlign('left').run()">≡</button>
      <button type="button" class="sre-btn" :class="{ active: editor.isActive({ textAlign: 'center' }) }" title="置中" @click="editor.chain().focus().setTextAlign('center').run()">≡</button>
      <button type="button" class="sre-btn" :class="{ active: editor.isActive({ textAlign: 'right' }) }" title="靠右" @click="editor.chain().focus().setTextAlign('right').run()">≡</button>

      <div class="sre-divider"></div>

      <!-- Color picker -->
      <div class="sre-color-wrap">
        <button
          type="button"
          class="sre-btn sre-color-btn"
          title="文字顏色"
          @click="colorPickerOpen = !colorPickerOpen"
        >
          <span class="sre-color-icon">
            <span class="sre-color-a">A</span>
            <span class="sre-color-bar" :style="{ background: currentColor() ?? '#000' }"></span>
          </span>
        </button>
        <div v-if="colorPickerOpen" class="sre-color-popup">
          <button type="button" class="sre-color-swatch sre-color-none" title="預設色" @click="unsetColor()">✕</button>
          <button
            v-for="c in PRESET_COLORS"
            :key="c"
            type="button"
            class="sre-color-swatch"
            :style="{ background: c }"
            :title="c"
            @click="setColor(c)"
          ></button>
        </div>
      </div>

      <div class="sre-divider"></div>

      <!-- Link -->
      <button type="button" class="sre-btn" :class="{ active: editor.isActive('link') }" title="連結" @click="setLink()">🔗</button>

      <div class="sre-divider"></div>

      <!-- Variables -->
      <div v-if="variableTokens.length > 0" class="sre-color-wrap">
        <button
          type="button"
          class="sre-btn sre-variable-btn"
          title="插入變數"
          @click="variableMenuOpen = !variableMenuOpen"
        >
          <span>&#123;&#123;&#125;&#125;</span>
        </button>
        <div v-if="variableMenuOpen" class="sre-variable-popup">
          <div class="sre-variable-title">插入變數</div>
          <button
            v-for="variable in variableTokens"
            :key="variable.token"
            type="button"
            class="sre-variable-option"
            :title="variable.description ?? variable.token"
            @click="insertVariableToken(variable)"
          >
            <span class="sre-variable-label">{{ variable.label }}</span>
            <code>{{ variable.token }}</code>
          </button>
        </div>
      </div>

      <div v-if="variableTokens.length > 0" class="sre-divider"></div>

      <!-- Image upload -->
      <button
        type="button"
        class="sre-btn"
        :disabled="imageUploading || !uploadUrl"
        :title="uploadUrl ? '插入圖片' : '圖片上傳未設定'"
        @click="triggerImageUpload"
      >
        <svg v-if="!imageUploading" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sre-spin">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
      </button>

      <!-- Video insert -->
      <div class="sre-color-wrap">
        <button type="button" class="sre-btn" title="插入影片" @click="openVideoModal">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>
          </svg>
        </button>
        <div v-if="videoModalOpen" class="sre-video-popup" @click.stop>
          <div class="sre-video-popup-label">貼上 YouTube 或 Vimeo 網址</div>
          <input
            v-model="videoUrlInput"
            class="sre-video-input"
            type="url"
            placeholder="https://youtu.be/..."
            @keydown.enter.prevent="insertVideo"
          />
          <div v-if="videoError" class="sre-video-error">{{ videoError }}</div>
          <div class="sre-video-actions">
            <button type="button" class="sre-video-btn sre-video-btn-cancel" @click="videoModalOpen = false">取消</button>
            <button type="button" class="sre-video-btn sre-video-btn-ok" @click="insertVideo">插入</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="imageError" class="sre-upload-error">{{ imageError }}</div>

    <!-- Hidden file input -->
    <input
      ref="imageFileInput"
      type="file"
      accept="image/jpeg,image/png,image/webp,image/gif"
      style="display:none"
      @change="onImageFileSelected"
    />

    <EditorContent v-if="editor" class="sre-editor" :editor="editor" />
  </div>
</template>

<style scoped>
.sre-wrap {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
  color-scheme: light;
}

.sre-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
  padding: 4px 6px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.sre-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 26px;
  padding: 0 5px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: #374151;
  font-size: 13px;
  cursor: pointer;
  line-height: 1;
}

.sre-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.sre-btn:hover:not(:disabled) { background: #e5e7eb; }
.sre-btn.active { background: #dbeafe; color: #1d4ed8; }

.sre-divider {
  width: 1px;
  height: 18px;
  background: #d1d5db;
  margin: 0 3px;
}

/* Color picker */
.sre-color-wrap { position: relative; }

.sre-color-btn { padding: 0 4px; }

.sre-color-icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
}

.sre-color-a { font-weight: 700; font-size: 13px; line-height: 1; }

.sre-color-bar {
  width: 16px;
  height: 3px;
  border-radius: 1px;
}

.sre-color-popup {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 200;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 8px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,.12);
  width: 148px;
}

.sre-color-swatch {
  width: 22px;
  height: 22px;
  border-radius: 4px;
  border: 1px solid rgba(0,0,0,.12);
  cursor: pointer;
}

.sre-color-none {
  background: #fff;
  color: #6b7280;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Video popup */
.sre-video-popup {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 200;
  padding: 10px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,.12);
  width: 280px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sre-video-popup-label {
  font-size: 12px;
  color: #6b7280;
}

.sre-video-input {
  width: 100%;
  padding: 5px 8px;
  font-size: 13px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  outline: none;
  box-sizing: border-box;
}

.sre-video-input:focus { border-color: #6366f1; }

.sre-video-error {
  font-size: 12px;
  color: #ef4444;
}

.sre-video-actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.sre-video-btn {
  padding: 4px 10px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  cursor: pointer;
}

.sre-video-btn-cancel { background: #fff; color: #374151; }
.sre-video-btn-ok { background: #6366f1; color: #fff; border-color: #6366f1; }

.sre-variable-btn {
  min-width: 36px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: 12px;
}

.sre-variable-popup {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 220;
  width: 260px;
  max-height: 260px;
  overflow-y: auto;
  padding: 8px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.sre-variable-title {
  margin: 0 0 6px;
  color: #6b7280;
  font-size: 12px;
  font-weight: 600;
}

.sre-variable-option {
  display: grid;
  width: 100%;
  gap: 2px;
  padding: 7px 8px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: #111827;
  cursor: pointer;
  text-align: left;
}

.sre-variable-option:hover {
  background: #eef2ff;
}

.sre-variable-label {
  font-size: 13px;
  font-weight: 600;
}

.sre-variable-option code {
  color: #4338ca;
  font-size: 12px;
  white-space: nowrap;
}

@keyframes sre-spin { to { transform: rotate(360deg); } }
.sre-spin { animation: sre-spin 0.8s linear infinite; }

/* Upload error */
.sre-upload-error {
  padding: 4px 10px;
  font-size: 12px;
  color: #ef4444;
  background: #fef2f2;
  border-bottom: 1px solid #fecaca;
}

/* Editor content area */
.sre-editor {
  min-height: 120px;
  max-height: 260px;
  overflow-y: auto;
  padding: 10px 12px;
  font-size: 14px;
  color: #111827;
  outline: none;
  scrollbar-width: thin;
  scrollbar-color: #d1d5db transparent;
}

.sre-editor::-webkit-scrollbar { width: 6px; }
.sre-editor::-webkit-scrollbar-track { background: transparent; }
.sre-editor::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }

:deep(.ProseMirror) {
  outline: none;
  min-height: 100px;
}

:deep(.ProseMirror p) { margin: 0 0 6px; }
:deep(.ProseMirror h2) { font-size: 18px; font-weight: 700; margin: 8px 0 4px; }
:deep(.ProseMirror h3) { font-size: 15px; font-weight: 600; margin: 6px 0 4px; }
:deep(.ProseMirror a) { color: #2563eb; text-decoration: underline; }

:deep(.survey-variable-token) {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  gap: 6px;
  margin: 0 2px;
  padding: 2px 6px;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  background: #eff6ff;
  color: #1e3a8a;
  font-size: 12px;
  font-weight: 600;
  vertical-align: baseline;
  white-space: nowrap;
}

:deep(.survey-variable-token code) {
  color: #475569;
  font-size: 11px;
  font-weight: 500;
}

:deep(.ProseMirror .is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
  float: left;
  height: 0;
}

:deep(.ProseMirror img) {
  max-width: 100%;
  height: auto;
  border-radius: 4px;
  display: block;
}

:deep(.ProseMirror .survey-video) {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
}

:deep(.ProseMirror .survey-video iframe) {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: 0;
}
</style>
