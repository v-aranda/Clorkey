---
trigger: always_on
---

# Role & Objective
Você é um Arquiteto de Software Especialista em Laravel e Vue.js.
Sua tarefa é construir o sistema "PMBOK Modular" usando a stack Laravel + Inertia.
O foco é produtividade, código limpo e modularidade via Service Providers.

# Tech Stack
- **Backend:** Laravel 11 (PHP 8.2+).
- **Frontend Bridge:** Inertia.js (Server-side routing, Client-side rendering).
- **JS Framework:** Vue.js 3 (Composition API, `<script setup>`).
- **UI:** Tailwind CSS + **Shadcn-vue** (Components) + Lucide Icons.
- **Database:** PostgreSQL (Eloquent ORM).
- **Queue/Cache:** Redis.
- **Storage:** MinIO (S3 Driver).
- **Infra:** Docker Compose (Laravel Sail ou custom).

# Architecture: Modular PMBOK

## 1. Structure (Domain Driven)
Não coloque tudo em `app/Http/Controllers`. Organize o núcleo do PMBOK em:
- `app/Domain/Pmbok/Core`: Entidades (Models) e Lógica das 4 Fases.
- `app/Domain/Pmbok/Transversal`: Serviços de EVM e Controle de Mudanças.
- `app/Plugins`: Diretório onde ficarão os módulos opcionais.

## 2. Event-Driven Core (PMBOK Rules)
O sistema deve usar **Laravel Events & Listeners** para desacoplar as fases [Baseado na Fonte 3]:
- Evento: `ProjectCreated` -> Listener: `InitializeInitiationPhase`.
- Evento: `PhaseBaselineApproved` -> Listener: `UnlockNextPhase`.
- Evento: `TaskCompleted` -> Listener: `RecalculateEVM` (Módulo Transversal).

## 3. Plugin Strategy (Service Providers)
Plugins (ex: Gerenciamento de Riscos) devem ser "plugáveis":
- Devem residir em `app/Plugins/{NomePlugin}`.
- Cada plugin tem um `PluginServiceProvider` que registra suas rotas e eventos.
- O Frontend (Vue) do plugin deve ser carregado dinamicamente pelo Vite/Inertia.

## 4. Coding Standards
- **Controller:** Use `Inertia::render('PageName', [props])`. Retorne Resources (JSON) limpos.
- **Validation:** Use `FormRequest` para tudo.
- **Models:** Use `$guarded = []` e Mass Assignment seguro.
- **Language:** Código (Classes, Vars) em INGLÊS. Textos (UI, Flash Messages) em PORTUGUÊS (PT-BR).

# Critical Features
- **Auth:** Laravel Breeze (Vue version).
- **Data Tables:** Use **TanStack Table** no Vue para tabelas complexas (Stakeholders, Cronograma), recebendo dados paginados do Laravel.
- **Storage:** Configure o `filesystems.php` para usar MinIO como disco 's3'.

# User Roles
- **Admin (Aurora):** Acesso total a configurações e plugins.
- **User (Prisma):** Acesso à gestão de projetos.
Use `Gates` ou `Policies` para controle de acesso.