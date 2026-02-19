---
trigger: always_on
---

# Role update: UI/UX Enforcer & Laravel Architect

ATENÇÃO: A partir de agora, todo desenvolvimento de interface CRUD deve seguir estritamente o "GOLD STANDARD" estabelecido no módulo de `Admin/Users`. Não crie telas de CRUD usando padrões genéricos.

# 1. New UI/UX Standard: The "PMBOK CRUD Pattern"
Todas as telas de listagem e gerenciamento (Stakeholders, Riscos, Projetos) devem seguir estas regras visuais e comportamentais:

## A. Page Layout & Header
- **Container:** `AuthenticatedLayout` > `div.py-12` > `div.max-w-7xl`.
- **Toolbar Superior:** Flexbox `justify-between`.
  - **Esquerda:** Ícone Lucide (h-6 w-6 text-gray-600) + Título (h1 text-lg font-semibold).
  - **Direita:**
    1. **Toggle:** Botão tipo "Pill" para filtros rápidos (ex: Ativos/Inativos).
    2. **Search:** Input com ícone de lupa (`pl-9`), largura fixa (`w-64`) e `debounce` de 300ms.
    3. **Primary Action:** Botão "Novo [Entidade]" (`bg-primary text-primary-foreground`).

## B. Data Tables (TanStack Table)
- **Engine:** Use `@tanstack/vue-table` obrigatoriamente.
- **Estilo:** `div.rounded-lg.border.bg-white.shadow-sm`.
- **Headers:** `border-b`, cursor pointer para ordenação, ícones `ArrowUpDown`.
- **Rows:** `hover:bg-muted/50`. Células com `p-4 align-middle`.
- **Actions Column (Direita):**
  - Use botões `variant="ghost" size="icon"`.
  - Ícones: `Pencil` (Edit), `Trash2` (Delete - Red), `RefreshCcw` (Restore - Green).
  - **Lógica:** Oculte botões de delete para registros protegidos (ex: Admin Principal).
- **Pagination:** Footer fixo com contador ("Mostrando X de Y") e botões Outline (Anterior/Próximo).

## C. Forms Strategy: "Off-Canvas" (Side Sheet)
NÃO use modais centralizados nem páginas separadas para Create/Edit.
- **Componente:** Painel deslizante lateral (`fixed inset-y-0 right-0 z-50 bg-white shadow-xl w-[400px]`).
- **Comportamento:**
  - `Overlay` escuro (`bg-black/50`) que fecha ao clicar.
  - Animação de entrada (Slide from right).
- **Inertia:** Use `useForm` dentro do componente do painel.
- **Scroll:** O corpo do formulário deve ter `overflow-y-auto` se for longo.

## D. Feedback & Interactions
- **Toasts:** Use para sucesso/erro (`fixed bottom-6 right-6`). Auto-dismiss 4s.
- **Modais de Confirmação:** Use APENAS para ações destrutivas (Delete/Archive).
- **State Preservation:** Em filtros e paginação, use sempre `router.get(url, {}, { preserveState: true, preserveScroll: true })`.

# 2. Technical Implementation Rules (Laravel + Vue)
- **Directory Structure:**
  - Pages: `resources/js/Pages/[Module]/Index.vue` (Contém a Tabela e o Off-Canvas).
  - Components: `resources/js/Components/ui/...` (Shadcn components).
- **Language:**
  - Código (Variáveis, Funções): **INGLÊS** (`showCreatePanel`, `fetchUsers`).
  - Interface (Labels, Placeholders, Toasts): **PORTUGUÊS (PT-BR)**.
- **Icons:** Use `lucide-vue-next`.

# 3. Example Task
Ao criar o CRUD de "Stakeholders", não pergunte o design. Replique exatamente a estrutura de `Users/Index.vue`, alterando apenas as colunas da tabela e os campos do formulário Off-Canvas.