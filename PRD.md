# PRD — Lembrete Automático de Prazo de Tarefa

**Versão:** 1.0 | **Data:** 2026-04-25 | **Status:** Aprovado

## 1. Overview
O módulo de Agenda exibe lembretes na tela do dia. A feature de lembrete automático de prazo deve gerar um item na lista de lembretes e no cabeçalho do cronograma exatamente quando a data selecionada coincide com o `deadline` de uma tarefa.

**Problema:** a lógica atual ignora `deadline` e usa `resolution_time`/`resolution_unit`, exibindo a tarefa em todos os dias do intervalo.

## 2. Goals and Metrics
- Tarefa com `deadline = hoje` aparece nos lembretes do dia
- Nenhuma tarefa baseada em `resolution_time` aparece nos lembretes
- Tarefas com `status = done` não aparecem
- Badge "Vence hoje" visível na UI

## 3. Functional Requirements (MoSCoW)
**Must Have**
- M1. `deadline = data_selecionada` e `status != done` → aparece como lembrete
- M2. Lógica `resolution_time`/`resolution_unit` removida dos lembretes
- M3. Visível para criador e participantes
- M4. Independente de `start_time`
- M5. Deadlines passados com `status != done` continuam aparecendo

**Should Have**
- S1. Badge "Vence hoje" para `kind = task_deadline`
- S2. Tarefas recorrentes: cada ocorrência tem deadline próprio

**Won't Have**
- Notificações e-mail/push, badge no calendário, alertas antecipados

## 4. Scope
- `AgendaReminderController::index()` — substituir lógica resolution por deadline
- `ReminderList.vue` — badge para `kind = task_deadline`

## 5. User Stories com ACs
**US-01:** `task.deadline = date` e `status != done` → aparece; `status = done` → não aparece; data diferente → não aparece; participante → aparece
**US-02:** `kind = task_deadline` → renderiza badge "Vence hoje"
**US-03:** Cada ocorrência recorrente gera lembrete no seu deadline

## 6. Technical Constraints
- `whereDate('deadline', $request->date)` + `whereNotNull('deadline')` + `where('status', '!=', 'done')`
- Colunas `resolution_time`/`resolution_unit` mantidas no schema
