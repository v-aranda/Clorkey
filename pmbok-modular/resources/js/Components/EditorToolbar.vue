<script setup>
import { ref } from 'vue';
import {
    Undo, Redo, Bold, Italic, Strikethrough, Eraser,
    Heading1, Heading2, Heading3, List, ListOrdered, Quote,
    Baseline, Highlighter, AlignLeft, AlignCenter, AlignRight, AlignJustify,
    Table as TableIcon, Trash2, Columns, Rows, Plus, Minus, PaintBucket,
    Combine, SplitSquareHorizontal,
    Link2, ListTodo, Paintbrush, Code, Minus as MinusIcon, ImageIcon, MoreHorizontal
} from 'lucide-vue-next';

const props = defineProps({
    editor: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['open-link-modal']);

// Image insertion
const addImage = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            props.editor.chain().focus().setImage({ src: ev.target.result }).run();
        };
        reader.readAsDataURL(file);
    };
    input.click();
};

// Table Insert Grid
const tableGridRows = ref(0);
const tableGridCols = ref(0);

// Dock dropdown state
const openDockDropdown = ref(null);
const dockDropdownStyle = ref({});
const dockBtnRect = ref(null);

// Copy formatting state
const copiedFormat = ref(null);

const copyFormatting = () => {
    if (!props.editor) return;
    const marks = props.editor.state.selection.$head.marks();
    const attrs = {};
    marks.forEach(mark => {
        attrs[mark.type.name] = mark.attrs;
    });
    copiedFormat.value = attrs;
};

const applyFormatting = () => {
    if (!props.editor || !copiedFormat.value) return;
    const chain = props.editor.chain().focus().unsetAllMarks();
    for (const [markName, markAttrs] of Object.entries(copiedFormat.value)) {
        if (markName === 'bold') chain.setBold();
        else if (markName === 'italic') chain.setItalic();
        else if (markName === 'strike') chain.setStrike();
        else if (markName === 'textStyle' && markAttrs.color) chain.setColor(markAttrs.color);
        else if (markName === 'highlight') chain.setHighlight(markAttrs);
        else if (markName === 'link') chain.setLink(markAttrs);
    }
    chain.run();
    copiedFormat.value = null;
};

const updateDropdownPosition = () => {
    if (!dockBtnRect.value) return;
    const rect = dockBtnRect.value;
    const viewportH = window.innerHeight;

    const dropdownEl = document.querySelector('[data-dock-dropdown]');
    const dropdownH = dropdownEl ? dropdownEl.offsetHeight : 400;

    const wouldOverflow = rect.top + dropdownH > viewportH - 16;

    if (wouldOverflow) {
        dockDropdownStyle.value = {
            position: 'fixed',
            left: `${rect.right + 8}px`,
            bottom: `${viewportH - rect.bottom}px`,
            top: 'auto',
        };
    } else {
        dockDropdownStyle.value = {
            position: 'fixed',
            left: `${rect.right + 8}px`,
            top: `${rect.top}px`,
            bottom: 'auto',
        };
    }
};

const toggleDockDropdown = (name, event) => {
    if (openDockDropdown.value === name) {
        openDockDropdown.value = null;
        dockBtnRect.value = null;
        return;
    }

    if (event) {
        const btn = event.currentTarget;
        if (btn) {
            dockBtnRect.value = btn.getBoundingClientRect();
        }
    }

    dockDropdownStyle.value = {
        position: 'fixed',
        left: '-9999px',
        top: '0px',
        visibility: 'hidden',
    };
    openDockDropdown.value = name;

    requestAnimationFrame(() => {
        dockDropdownStyle.value.visibility = 'visible';
        updateDropdownPosition();
    });
};

const closeDockDropdowns = () => {
    openDockDropdown.value = null;
    dockBtnRect.value = null;
};

// Hover dropdown (for heading)
const hoverTimeout = ref(null);

const openDockDropdownHover = (name, event) => {
    if (hoverTimeout.value) {
        clearTimeout(hoverTimeout.value);
        hoverTimeout.value = null;
    }
    if (openDockDropdown.value === name) return;

    if (event) {
        const btn = event.currentTarget;
        if (btn) dockBtnRect.value = btn.getBoundingClientRect();
    }

    dockDropdownStyle.value = { position: 'fixed', left: '-9999px', top: '0px', visibility: 'hidden' };
    openDockDropdown.value = name;

    requestAnimationFrame(() => {
        dockDropdownStyle.value.visibility = 'visible';
        updateDropdownPosition();
    });
};

const closeDockDropdownHover = (name) => {
    hoverTimeout.value = setTimeout(() => {
        if (openDockDropdown.value === name) {
            openDockDropdown.value = null;
            dockBtnRect.value = null;
        }
    }, 200);
};

const cancelHoverClose = () => {
    if (hoverTimeout.value) {
        clearTimeout(hoverTimeout.value);
        hoverTimeout.value = null;
    }
};

// Cycle alignment: left → center → right → justify → left
const cycleAlignment = () => {
    const e = props.editor;
    if (e.isActive({ textAlign: 'left' })) e.chain().focus().setTextAlign('center').run();
    else if (e.isActive({ textAlign: 'center' })) e.chain().focus().setTextAlign('right').run();
    else if (e.isActive({ textAlign: 'right' })) e.chain().focus().setTextAlign('justify').run();
    else if (e.isActive({ textAlign: 'justify' })) e.chain().focus().setTextAlign('left').run();
    else e.chain().focus().setTextAlign('center').run();
};

// Cycle list: none → bullet → ordered → task → none
const cycleList = () => {
    const e = props.editor;
    if (e.isActive('taskList')) { e.chain().focus().toggleTaskList().run(); }
    else if (e.isActive('orderedList')) { e.chain().focus().toggleOrderedList().run(); e.chain().focus().toggleTaskList().run(); }
    else if (e.isActive('bulletList')) { e.chain().focus().toggleBulletList().run(); e.chain().focus().toggleOrderedList().run(); }
    else { e.chain().focus().toggleBulletList().run(); }
};
</script>

<template>
    <div class="hidden md:block flex-shrink-0 sticky top-6 pt-4 pb-4 z-30">
        <!-- Dropdown backdrop -->
        <div v-if="openDockDropdown" class="fixed inset-0 z-40" @click="closeDockDropdowns"></div>

        <div class="relative z-50 flex flex-col items-center bg-white border border-gray-200 rounded-xl shadow-sm p-1.5 mx-2 w-10">

            <!-- ═══ Group 1: Undo / Redo ═══ -->
            <div class="flex flex-col gap-0.5 w-full">
                <button @click="editor.chain().focus().undo().run()"
                    :disabled="!editor.can().chain().focus().undo().run()"
                    class="flex items-center justify-center w-full h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors disabled:opacity-25"
                    title="Desfazer">
                    <Undo class="w-4 h-4" />
                </button>
                <button @click="editor.chain().focus().redo().run()"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    class="flex items-center justify-center w-full h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors disabled:opacity-25"
                    title="Refazer">
                    <Redo class="w-4 h-4" />
                </button>
            </div>

            <div class="w-6 h-px bg-gray-200 my-1"></div>

            <!-- ═══ Group 2: Formatting ═══ -->
            <div class="flex flex-col gap-0.5 w-full">
                <button @click="editor.chain().focus().unsetAllMarks().run()"
                    class="flex items-center justify-center w-full h-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Limpar formatação">
                    <Eraser class="w-4 h-4" />
                </button>
                <!-- Copy Formatting -->
                <button @click="copiedFormat ? applyFormatting() : copyFormatting()"
                    :class="{ 'bg-amber-100 text-amber-700 ring-1 ring-amber-300': copiedFormat, 'text-gray-500': !copiedFormat }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    :title="copiedFormat ? 'Aplicar formatação (clique para colar)' : 'Copiar formatação'">
                    <Paintbrush class="w-4 h-4" />
                </button>
            </div>

                        <div class="w-6 h-px bg-gray-200 my-1"></div>

            <div class="flex flex-col gap-0.5 w-full">
                <!-- Title (hover dropdown, horizontal) -->
                <div @mouseleave="closeDockDropdownHover('heading')">
                    <button @mouseenter="openDockDropdownHover('heading', $event)"
                        class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                        :class="{ 'bg-primary/10 !text-primary': editor.isActive('heading'), 'text-gray-500': !editor.isActive('heading') }"
                        title="Título">
                        <Heading1 v-if="editor.isActive('heading', { level: 1 }) || !editor.isActive('heading')" class="w-4 h-4" />
                        <Heading2 v-else-if="editor.isActive('heading', { level: 2 })" class="w-4 h-4" />
                        <Heading3 v-else-if="editor.isActive('heading', { level: 3 })" class="w-4 h-4" />
                    </button>
                    <Teleport to="body">
                        <div v-if="openDockDropdown === 'heading'"
                            data-dock-dropdown
                            class="fixed flex items-center bg-white border border-gray-200 rounded-lg shadow-lg z-[9999] p-1 gap-0.5"
                            :style="dockDropdownStyle"
                            @mouseenter="cancelHoverClose"
                            @mouseleave="closeDockDropdownHover('heading')"
                            @click.stop>
                            <button @click="editor.chain().focus().toggleHeading({ level: 1 }).run(); closeDockDropdowns()"
                                :class="{ 'bg-primary/10 text-primary': editor.isActive('heading', { level: 1 }) }"
                                class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-50 transition-colors text-gray-700"
                                title="Título 1">
                                <Heading1 class="w-4 h-4" />
                            </button>
                            <button @click="editor.chain().focus().toggleHeading({ level: 2 }).run(); closeDockDropdowns()"
                                :class="{ 'bg-primary/10 text-primary': editor.isActive('heading', { level: 2 }) }"
                                class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-50 transition-colors text-gray-700"
                                title="Título 2">
                                <Heading2 class="w-4 h-4" />
                            </button>
                            <button @click="editor.chain().focus().toggleHeading({ level: 3 }).run(); closeDockDropdowns()"
                                :class="{ 'bg-primary/10 text-primary': editor.isActive('heading', { level: 3 }) }"
                                class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-50 transition-colors text-gray-700"
                                title="Título 3">
                                <Heading3 class="w-4 h-4" />
                            </button>
                        </div>
                    </Teleport>
                </div>
                <button @click="editor.chain().focus().toggleBold().run()"
                    :class="{ 'bg-primary/10 text-primary': editor.isActive('bold'), 'text-gray-500': !editor.isActive('bold') }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Negrito">
                    <Bold class="w-4 h-4" />
                </button>
                <button @click="editor.chain().focus().toggleItalic().run()"
                    :class="{ 'bg-primary/10 text-primary': editor.isActive('italic'), 'text-gray-500': !editor.isActive('italic') }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Itálico">
                    <Italic class="w-4 h-4" />
                </button>
                <button @click="editor.chain().focus().toggleStrike().run()"
                    :class="{ 'bg-primary/10 text-primary': editor.isActive('strike'), 'text-gray-500': !editor.isActive('strike') }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Tachado">
                    <Strikethrough class="w-4 h-4" />
                </button>
                <!-- Color Picker -->
                <div class="relative flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
                    title="Cor do Texto">
                    <Baseline class="w-4 h-4 text-gray-500 pointer-events-none" />
                    <div class="w-4 h-[3px] absolute bottom-[5px] rounded-full pointer-events-none"
                        :style="{ backgroundColor: editor.getAttributes('textStyle').color || '#000000' }"></div>
                    <input type="color" @input="editor.chain().focus().setColor($event.target.value).run()"
                        :value="editor.getAttributes('textStyle').color || '#000000'"
                        class="opacity-0 absolute inset-0 w-full h-full cursor-pointer" />
                </div>
                <!-- Highlight Picker -->
                <div class="relative flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
                    title="Cor de Fundo (Highlight)">
                    <Highlighter class="w-4 h-4 text-gray-500 pointer-events-none" />
                    <div class="w-4 h-[3px] absolute bottom-[5px] rounded-full pointer-events-none"
                        :style="{ backgroundColor: editor.getAttributes('highlight').color || '#ffff00' }"></div>
                    <input type="color"
                        @input="editor.chain().focus().toggleHighlight({ color: $event.target.value }).run()"
                        :value="editor.getAttributes('highlight').color || '#ffff00'"
                        class="opacity-0 absolute inset-0 w-full h-full cursor-pointer" />
                </div>
                <!-- Link -->
                <button @click="emit('open-link-modal')"
                    :class="{ 'bg-primary/10 text-primary': editor.isActive('link'), 'text-gray-500': !editor.isActive('link') }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Link">
                    <Link2 class="w-4 h-4" />
                </button>
                
            </div>

            <div class="w-6 h-px bg-gray-200 my-1"></div>

            <!-- ═══ Group 3: Alignment, Title, List ═══ -->
            <div class="flex flex-col gap-0.5 w-full">
                <!-- Alignment (cycle on click) -->
                <button @click="cycleAlignment()"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors text-gray-500"
                    title="Alinhamento (clique para alternar)">
                    <AlignLeft v-if="editor.isActive({ textAlign: 'left' })" class="w-4 h-4" />
                    <AlignCenter v-else-if="editor.isActive({ textAlign: 'center' })" class="w-4 h-4" />
                    <AlignRight v-else-if="editor.isActive({ textAlign: 'right' })" class="w-4 h-4" />
                    <AlignJustify v-else-if="editor.isActive({ textAlign: 'justify' })" class="w-4 h-4" />
                    <AlignLeft v-else class="w-4 h-4" />
                </button>

                <!-- List (cycle on click) -->
                <button @click="cycleList()"
                    :class="{ 'bg-primary/10 text-primary': editor.isActive('bulletList') || editor.isActive('orderedList') || editor.isActive('taskList'), 'text-gray-500': !editor.isActive('bulletList') && !editor.isActive('orderedList') && !editor.isActive('taskList') }"
                    class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors"
                    title="Lista (clique para alternar)">
                    <ListTodo v-if="editor.isActive('taskList')" class="w-4 h-4" />
                    <ListOrdered v-else-if="editor.isActive('orderedList')" class="w-4 h-4" />
                    <List v-else class="w-4 h-4" />
                </button>
            </div>

            <div class="w-6 h-px bg-gray-200 my-1"></div>

            <!-- ═══ Group 4: Table + More ═══ -->
            <div class="flex flex-col gap-0.5 w-full">

                <!-- Insert Table (when NOT inside a table) -->
                <div v-if="!editor.isActive('table')">
                    <button @click.stop="toggleDockDropdown('tableInsert', $event)"
                        class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors text-gray-500"
                        :class="{ 'bg-primary/10 !text-primary': openDockDropdown === 'tableInsert' }"
                        title="Inserir Tabela">
                        <TableIcon class="w-4 h-4" />
                    </button>
                    <Teleport to="body">
                        <div v-if="openDockDropdown === 'tableInsert'"
                            data-dock-dropdown
                            class="fixed bg-white border border-gray-200 rounded-lg shadow-lg z-[9999] p-3"
                            :style="dockDropdownStyle"
                            @click.stop>
                            <div class="text-xs font-semibold text-gray-500 text-center uppercase tracking-wide mb-2 h-4">
                                {{ tableGridRows > 0 ? `${tableGridRows} × ${tableGridCols}` : 'Inserir Tabela' }}
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(8, 19px); gap: 3px;">
                                <template v-for="r in 8" :key="'r'+r">
                                    <div v-for="c in 8" :key="'c'+c"
                                        @mouseenter.stop="tableGridRows = r; tableGridCols = c"
                                        @click.stop="editor.chain().focus().insertTable({ rows: r, cols: c, withHeaderRow: true }).run(); closeDockDropdowns(); tableGridRows = 0; tableGridCols = 0;"
                                        style="width: 19px; height: 19px;"
                                        class="border rounded-[2px] transition-colors cursor-pointer"
                                        :class="r <= tableGridRows && c <= tableGridCols ? 'bg-primary/30 border-primary/60' : 'bg-gray-100 border-gray-200 hover:border-gray-300'">
                                    </div>
                                </template>
                            </div>
                        </div>
                    </Teleport>
                </div>

                <!-- Table Edit Options (when INSIDE a table) -->
                <template v-else>
                    <!-- Table Options Dropdown -->
                    <div>
                        <button @click.stop="toggleDockDropdown('tableOptions', $event)"
                            class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 transition-colors"
                            :class="{ 'bg-primary/10 text-primary': openDockDropdown === 'tableOptions', 'text-primary': openDockDropdown !== 'tableOptions' }"
                            title="Opções da Tabela">
                            <TableIcon class="w-4 h-4" />
                        </button>
                        <Teleport to="body">
                            <div v-if="openDockDropdown === 'tableOptions'"
                                data-dock-dropdown
                                class="fixed w-52 bg-white border border-gray-200 rounded-xl shadow-lg z-[9999] py-1.5"
                                :style="dockDropdownStyle"
                                @click.stop>
                                <div class="px-3 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Colunas</div>
                                <button @click="editor.chain().focus().addColumnBefore().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-emerald-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md mx-0">
                                    <div class="relative"><Columns class="w-4 h-4 text-emerald-600" /><Plus class="w-3 h-3 absolute -bottom-1 -right-1 text-emerald-700 bg-white rounded-full bg-opacity-80" /></div> Inserir Antes
                                </button>
                                <button @click="editor.chain().focus().addColumnAfter().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-emerald-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md">
                                    <div class="relative"><Columns class="w-4 h-4 text-emerald-600" /><Plus class="w-3 h-3 absolute -bottom-1 -right-1 text-emerald-700 bg-white rounded-full bg-opacity-80" /></div> Inserir Depois
                                </button>
                                <button @click="editor.chain().focus().deleteColumn().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-red-50 text-sm text-red-500 w-full text-left transition-colors rounded-md">
                                    <div class="relative"><Columns class="w-4 h-4 text-red-500" /><Minus class="w-3 h-3 absolute -bottom-1 -right-1 text-red-700 bg-white rounded-full bg-opacity-80" /></div> Excluir Coluna
                                </button>
                                <div class="h-px bg-gray-100 my-1.5 mx-2"></div>
                                <div class="px-3 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Linhas</div>
                                <button @click="editor.chain().focus().addRowBefore().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-blue-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md">
                                    <div class="relative"><Rows class="w-4 h-4 text-blue-600" /><Plus class="w-3 h-3 absolute -bottom-1 -right-1 text-blue-700 bg-white rounded-full bg-opacity-80" /></div> Inserir Acima
                                </button>
                                <button @click="editor.chain().focus().addRowAfter().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-blue-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md">
                                    <div class="relative"><Rows class="w-4 h-4 text-blue-600" /><Plus class="w-3 h-3 absolute -bottom-1 -right-1 text-blue-700 bg-white rounded-full bg-opacity-80" /></div> Inserir Abaixo
                                </button>
                                <button @click="editor.chain().focus().deleteRow().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-red-50 text-sm text-red-500 w-full text-left transition-colors rounded-md">
                                    <div class="relative"><Rows class="w-4 h-4 text-red-500" /><Minus class="w-3 h-3 absolute -bottom-1 -right-1 text-red-700 bg-white rounded-full bg-opacity-80" /></div> Excluir Linha
                                </button>
                                <div class="h-px bg-gray-100 my-1.5 mx-2"></div>
                                <div class="px-3 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Células</div>
                                <button @click="editor.chain().focus().mergeCells().run(); closeDockDropdowns()"
                                    :disabled="!editor.can().mergeCells()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-violet-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md disabled:opacity-30 disabled:cursor-not-allowed">
                                    <Combine class="w-4 h-4 text-violet-500" /> Mesclar
                                </button>
                                <button @click="editor.chain().focus().splitCell().run(); closeDockDropdowns()"
                                    :disabled="!editor.can().splitCell()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-violet-50 text-sm text-gray-600 w-full text-left transition-colors rounded-md disabled:opacity-30 disabled:cursor-not-allowed">
                                    <SplitSquareHorizontal class="w-4 h-4 text-violet-500" /> Dividir
                                </button>
                                <div class="h-px bg-gray-100 my-1.5 mx-2"></div>
                                <button @click="editor.chain().focus().deleteTable().run(); closeDockDropdowns()"
                                    class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-red-50 text-sm text-red-500 font-medium w-full text-left transition-colors rounded-md">
                                    <Trash2 class="w-4 h-4" /> Excluir Tabela
                                </button>
                            </div>
                        </Teleport>
                    </div>

                    <!-- Cell / Border color -->
                    <div class="flex flex-col gap-0.5">
                        <!-- Cell Background Color -->
                        <div class="relative flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
                            title="Cor da Célula">
                            <PaintBucket class="w-4 h-4 text-gray-500 pointer-events-none" />
                            <div class="w-4 h-[3px] absolute bottom-[5px] rounded-full pointer-events-none"
                                :style="{ backgroundColor: editor.getAttributes('tableCell').backgroundColor || '#ffffff' }">
                            </div>
                            <input type="color"
                                @input="editor.chain().focus().setCellAttribute('backgroundColor', $event.target.value).run()"
                                :value="editor.getAttributes('tableCell').backgroundColor || '#ffffff'"
                                class="opacity-0 absolute inset-0 w-full h-full cursor-pointer" />
                        </div>
                    </div>
                </template>

                <!-- More options (3 dots) -->
                <div>
                    <button @click.stop="toggleDockDropdown('moreOptions', $event)"
                        class="flex items-center justify-center w-full h-8 rounded-lg hover:bg-gray-100 hover:text-primary transition-colors text-gray-500"
                        :class="{ 'bg-primary/10 !text-primary': openDockDropdown === 'moreOptions' || editor.isActive('blockquote') || editor.isActive('codeBlock') }"
                        title="Mais opções">
                        <MoreHorizontal class="w-4 h-4" />
                    </button>
                    <Teleport to="body">
                        <div v-if="openDockDropdown === 'moreOptions'"
                            data-dock-dropdown
                            class="fixed w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-[9999] py-1"
                            :style="dockDropdownStyle"
                            @click.stop>
                            <button @click="editor.chain().focus().toggleBlockquote().run(); closeDockDropdowns()"
                                :class="{ 'bg-primary/10 text-primary': editor.isActive('blockquote') }"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-md mx-1 text-sm hover:bg-gray-50 transition-colors text-gray-700 w-[calc(100%-0.5rem)]">
                                <Quote class="w-4 h-4" /> Citação
                            </button>
                            <button @click="editor.chain().focus().toggleCodeBlock().run(); closeDockDropdowns()"
                                :class="{ 'bg-primary/10 text-primary': editor.isActive('codeBlock') }"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-md mx-1 text-sm hover:bg-gray-50 transition-colors text-gray-700 w-[calc(100%-0.5rem)]">
                                <Code class="w-4 h-4" /> Bloco de Código
                            </button>
                            <button @click="editor.chain().focus().setHorizontalRule().run(); closeDockDropdowns()"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-md mx-1 text-sm hover:bg-gray-50 transition-colors text-gray-700 w-[calc(100%-0.5rem)]">
                                <MinusIcon class="w-4 h-4" /> Linha Horizontal
                            </button>
                            <button @click="addImage(); closeDockDropdowns()"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-md mx-1 text-sm hover:bg-gray-50 transition-colors text-gray-700 w-[calc(100%-0.5rem)]">
                                <ImageIcon class="w-4 h-4" /> Imagem
                            </button>
                        </div>
                    </Teleport>
                </div>
            </div>

        </div>
    </div>
</template>
