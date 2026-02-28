import { ref, onBeforeUnmount } from 'vue';
import { useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TiptapLink from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Mention from '@tiptap/extension-mention';

/**
 * Creates a TipTap rich text editor with @mention support, image drag/drop/paste,
 * and link insertion.
 *
 * @param {Object} options
 * @param {import('vue').Ref<Array>} options.users - Reactive list of users for mention suggestions
 * @param {Function} [options.onMentionSelect] - Called with userId when a mention is selected
 * @param {string} [options.placeholder]
 */
export function useTiptapEditor({ users, onMentionSelect, placeholder = 'Descreva a tarefa... Use @ para mencionar participantes' } = {}) {
    const taskImageInput = ref(null);

    function createMentionSuggestion() {
        return {
            items: ({ query }) => {
                const list = users?.value ?? users ?? [];
                return list
                    .filter(u => u.name.toLowerCase().includes(query.toLowerCase()))
                    .slice(0, 5);
            },
            render: () => {
                let popup;

                return {
                    onStart: (propsData) => {
                        popup = document.createElement('div');
                        popup.className = 'mention-dropdown';
                        document.body.appendChild(popup);
                        updatePopup(propsData);
                    },
                    onUpdate: (propsData) => { updatePopup(propsData); },
                    onKeyDown: (propsData) => {
                        if (propsData.event.key === 'Escape') { popup?.remove(); return true; }
                        if (['ArrowDown', 'ArrowUp', 'Enter'].includes(propsData.event.key)) {
                            const items = popup?.querySelectorAll('.mention-item');
                            if (!items?.length) return false;
                            const active = popup?.querySelector('.mention-item.active');
                            let idx = active ? Array.from(items).indexOf(active) : -1;
                            if (propsData.event.key === 'ArrowDown') idx = (idx + 1) % items.length;
                            else if (propsData.event.key === 'ArrowUp') idx = idx <= 0 ? items.length - 1 : idx - 1;
                            else if (propsData.event.key === 'Enter') { if (active) { active.click(); return true; } return false; }
                            items.forEach(el => el.classList.remove('active'));
                            items[idx]?.classList.add('active');
                            return true;
                        }
                        return false;
                    },
                    onExit: () => { popup?.remove(); },
                };

                function updatePopup(propsData) {
                    if (!popup) return;
                    const { items, command, clientRect } = propsData;
                    const rect = clientRect?.();
                    if (rect) {
                        popup.style.position = 'fixed';
                        popup.style.left = rect.left + 'px';
                        popup.style.top = (rect.bottom + 4) + 'px';
                        popup.style.zIndex = '9999';
                    }
                    popup.innerHTML = items.length === 0
                        ? '<div class="mention-empty">Nenhum usu\u00e1rio encontrado</div>'
                        : items.map((item, i) => `
                            <div class="mention-item ${i === 0 ? 'active' : ''}" data-id="${item.id}" data-name="${item.name}">
                                <span class="mention-avatar">${item.name.charAt(0).toUpperCase()}</span>
                                <span class="mention-name">${item.name}</span>
                            </div>
                        `).join('');
                    popup.querySelectorAll('.mention-item').forEach(el => {
                        el.addEventListener('click', () => {
                            const id = parseInt(el.dataset.id);
                            const name = el.dataset.name;
                            command({ id, label: name });
                            if (onMentionSelect) onMentionSelect(id);
                        });
                    });
                }
            },
        };
    }

    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] }, link: false }),
            Image.configure({ inline: false, allowBase64: true, HTMLAttributes: { class: 'editor-image' } }),
            TiptapLink.configure({ openOnClick: false, HTMLAttributes: { class: 'editor-link' } }),
            Placeholder.configure({ placeholder }),
            Mention.configure({
                HTMLAttributes: { class: 'mention' },
                suggestion: createMentionSuggestion(),
            }),
        ],
        editorProps: {
            attributes: {
                class: 'prose prose-sm max-w-none focus:outline-none min-h-[120px] px-3 py-2',
            },
            handleDrop(view, event, slice, moved) {
                if (!moved && event.dataTransfer?.files?.length) {
                    const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
                    if (files.length === 0) return false;
                    event.preventDefault();
                    files.forEach(file => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const pos = view.posAtCoords({ left: event.clientX, top: event.clientY });
                            const node = view.state.schema.nodes.image.create({ src: e.target.result });
                            const tr = view.state.tr.insert(pos?.pos ?? view.state.selection.from, node);
                            view.dispatch(tr);
                        };
                        reader.readAsDataURL(file);
                    });
                    return true;
                }
                return false;
            },
            handlePaste(view, event) {
                const items = Array.from(event.clipboardData?.items || []);
                const images = items.filter(item => item.type.startsWith('image/'));
                if (images.length === 0) return false;
                event.preventDefault();
                images.forEach(item => {
                    const file = item.getAsFile();
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const node = view.state.schema.nodes.image.create({ src: e.target.result });
                        const tr = view.state.tr.replaceSelectionWith(node);
                        view.dispatch(tr);
                    };
                    reader.readAsDataURL(file);
                });
                return true;
            },
        },
        content: '',
    });

    onBeforeUnmount(() => { editor.value?.destroy(); });

    function triggerImageUpload() { taskImageInput.value?.click(); }

    function onImageSelected(e) {
        const file = e.target.files[0];
        if (!file || !editor.value) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            editor.value.chain().focus().setImage({ src: ev.target.result }).run();
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    }

    function insertLink() {
        const url = window.prompt('URL:');
        if (url) editor.value?.chain().focus().setLink({ href: url }).run();
    }

    return { editor, taskImageInput, triggerImageUpload, onImageSelected, insertLink };
}
