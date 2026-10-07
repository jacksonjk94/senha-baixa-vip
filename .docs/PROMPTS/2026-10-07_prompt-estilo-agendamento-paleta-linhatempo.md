# Prompt — Estilo Área de Agendamento: Paleta Telegram Elegante + Linha do Tempo

**Arquivo:** 2026-10-07_prompt-estilo-agendamento-paleta-linhatempo.md
**Data:** 07/10/2026 · Curitiba/PR
**Projeto:** AgendaFácil
**Tecnologia:** Tailwind CSS
**Arquivos afetados:** `resources/views/agendamento/*.blade.php` (e estilos globais, se necessário)

---

## 🎯 Objetivo

Aplicar identidade visual elegante, sóbria e profissional na área de agendamento, com paleta inspirada no Telegram em tom de azul mais escuro, e substituir as abas/navegação de etapas por uma **linha do tempo horizontal refinada**. Manter toda a estrutura funcional, campos, textos e ordem das etapas — apenas aprimorar o visual.

> **Instrução para o agente (OpenCode):** Leia as views existentes. Não altere lógica, rotas, campos ou textos. Apenas substitua as classes de estilo e a navegação de etapas pela linha do tempo descrita abaixo. Mantenha a responsividade.

---

## 🎨 Paleta de Cores Oficial

### Base — Telegram Escuro & Elegante

| Elemento | Classe Tailwind | Código Hex | Observação |
|---|---|---|---|
| Fundo da página | `bg-slate-50` | `#F8FAFC` | Limpo, levemente azulado |
| Primária / Botão principal / Etapa ativa | `bg-blue-900` / `text-blue-900` | `#1E3A8A` | Cabeçalho, CTA, etapa atual |
| Hover primário | `bg-blue-800` | `#1E40AF` | Hover de botões/links |
| Secundária / Destaque | `text-blue-700` / `border-blue-700` | `#1D4ED8` | Foco de input, detalhes |
| Suave / Fundo de card ativo | `bg-blue-50` | `#EFF6FF` | Card selecionado, etapa concluída |
| Texto principal | `text-slate-800` | `#1E293B` | Títulos, perguntas |
| Texto secundário / apoio | `text-slate-500` | `#64748B` | Ajudas, legendas, placeholders |
| Borda / Divisores | `border-slate-200` | `#E2E8F0` | Separadores, bordas padrão |
| Sucesso / Concluído | `bg-emerald-600` / `text-emerald-700` | `#059669` | Check de etapa pronta, linha concluída |
| Linha concluída | `bg-emerald-200` | `#A7F3D0` | Conector entre etapas concluídas |
| Linha pendente | `bg-slate-100` | `#F1F5F9` | Conector para etapas futuras |
| Linha atual | `bg-slate-200` | `#E2E8F0` | Conector até a etapa atual |
| Botão desativado | `bg-slate-100 text-slate-400 cursor-not-allowed` | `#F1F5F9 / #94A3B8` | Sem clique |

### Botões

| Estado | Classes Tailwind |
|---|---|
| **Primário / Ativo** | `bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-lg transition-all duration-200 shadow-sm` |
| **Secundário / Alternativo** | `bg-white border border-blue-200 text-blue-900 hover:bg-blue-50 font-medium rounded-lg transition-all duration-200` |
| **Desativado** | `bg-slate-100 text-slate-400 cursor-not-allowed rounded-lg` |

---

## 📐 Linha do Tempo — Etapas (substitui abas)

### Estrutura geral

- Layout horizontal no desktop → empilha em coluna abaixo de `md` (mobile)
- Etapas (manter nomes e ordem originais do projeto): ex. `Telefone → Código → Horário → Grupo → Confirmar → Concluído`
- Cada etapa tem **3 estados possíveis**: Concluída, Atual, Pendente
- Conectores (linhas) entre as etapas com cor conforme o estado

### Estado 1 — ✅ Concluída

```html
<div class="flex flex-col items-center flex-1">
  <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-sm font-semibold shadow-sm">
    ✓
  </div>
  <span class="mt-2 text-xs md:text-sm text-emerald-700 font-medium text-center">Telefone</span>
</div>
<!-- Conector à direita (se houver próxima etapa) -->
<div class="flex-1 h-0.5 bg-emerald-200 mx-1 md:mx-3 self-start mt-4"></div>
```

### Estado 2 — 🔵 Atual (etapa atual)

```html
<div class="flex flex-col items-center flex-1">
  <div class="w-10 h-10 rounded-full bg-blue-900 text-white flex items-center justify-center text-base font-bold shadow-md ring-4 ring-blue-100">
    2
  </div>
  <span class="mt-2 text-sm md:text-base text-blue-900 font-semibold text-center">Código</span>
</div>
<!-- Conector à direita (se houver próxima etapa) -->
<div class="flex-1 h-0.5 bg-slate-200 mx-1 md:mx-3 self-start mt-5"></div>
```

### Estado 3 — ⚪ Pendente

```html
<div class="flex flex-col items-center flex-1">
  <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-sm font-medium border border-slate-200">
    3
  </div>
  <span class="mt-2 text-xs md:text-sm text-slate-400 text-center">Horário</span>
</div>
<!-- Conector à direita (se houver próxima etapa) -->
<div class="flex-1 h-0.5 bg-slate-100 mx-1 md:mx-3 self-start mt-4"></div>
```

### Container da linha do tempo

```html
<div class="flex items-start justify-between w-full mb-8 px-1 md:px-2">
  <!-- Repetir: [Etapa] + [Conector] ... última etapa sem conector à direita -->
</div>
```

> **Mobile (< md):** manter horizontal mas com rótulos menores (`text-xs`) e conectores mais curtos; se quebrar, permitir scroll horizontal suave (`overflow-x-auto`).

---

## 🖼️ Estilo dos Componentes

### Card principal

```html
<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 px-6 md:px-8 py-8">
  <!-- conteúdo -->
</div>
```

### Cabeçalho do card

```html
<h1 class="text-2xl font-bold text-slate-800 mb-1">Título da etapa</h1>
<p class="text-slate-500 text-sm md:text-base mb-6">Texto de apoio explicativo.</p>
```

### Botões de opção (ex: "Primeiro agendamento" / "Já agendei")

- Container: `grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6`
- **Inativo:** `py-3 px-4 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-blue-50 hover:border-blue-200 transition-all duration-200 text-left`
- **Ativo:** `py-3 px-4 rounded-lg border-2 border-blue-900 bg-blue-50 text-blue-900 font-semibold shadow-sm text-left`

### Input de telefone

```html
<div class="flex items-center border border-slate-200 rounded-lg overflow-hidden focus-within:border-blue-700 focus-within:ring-1 focus-within:ring-blue-700 transition-all duration-200">
  <span class="px-3 py-3 bg-slate-50 border-r border-slate-200 text-slate-500 text-sm">+55</span>
  <input type="tel" class="flex-1 px-4 py-3 outline-none text-slate-800 text-sm" placeholder="(11) 90000-0000">
</div>
```

### Inputs em geral

- Classe base: `w-full px-4 py-3 border border-slate-200 rounded-lg outline-none text-slate-800 text-sm focus:border-blue-700 focus:ring-1 focus:ring-blue-700 transition-all duration-200`
- Label: `block text-sm font-medium text-slate-700 mb-1.5`
- Ajudas/erros: `text-xs text-slate-500 mt-1` / erro: `text-xs text-red-600 mt-1`

### Select / Grupo

- Mesma classe base do input; opção ativa com `bg-blue-50 border-blue-900`

### Botão Continuar / Avançar

```html
<button class="w-full py-3 rounded-lg bg-blue-900 hover:bg-blue-800 text-white font-semibold transition-all duration-200 mt-6 shadow-sm active:scale-[0.99]">
  Continuar
</button>
```

### Link secundário / Voltar

```html
<a class="text-sm text-blue-700 hover:text-blue-900 font-medium transition-colors">Voltar</a>
```

---

## 📋 Resumo de Aplicação

| Área | Ação |
|---|---|
| Fundo da página | `bg-slate-50` — limpo, leve |
| Navegação de etapas | Substituir abas pela linha do tempo (3 estados) |
| Card | Branco, `rounded-2xl`, borda sutil `border-slate-100`, sombra suave `shadow-sm` |
| Botões | Azul escuro `bg-blue-900` → hover `bg-blue-800` |
| Inputs | Foco com `border-blue-700` + `ring-1 ring-blue-700` |
| Texto | Hierarquia: `slate-800` título / `slate-500` apoio |
| Etapa concluída | `emerald-600` com check ✓ |
| Etapa atual | `blue-900` maior + `ring-4 ring-blue-100` |
| Etapa pendente | `slate-100` com número em `slate-400` |
| Responsividade | Linha do tempo compacta no mobile; card em `max-w-lg` |

---

## ❌ O que NÃO fazer

- ❌ Não usar gradientes chamativos, brilhos ou efeitos exagerados
- ❌ Não alterar estrutura de campos, textos, rotas ou ordem das etapas
- ❌ Não aplicar cores fora da paleta definida
- ❌ Não remover classes de responsividade
- ❌ Não usar roxo, roxo-azulado ou outras cores fora do espectro azul/teal escuro
- ❌ Não alterar nomes das etapas

---

## ✅ Critérios de Conclusão

- [ ] Linha do tempo substitui as abas/navegação antiga e funciona em mobile e desktop
- [ ] Paleta aplicada em todos os elementos sem cor fora do padrão
- [ ] Botões e inputs com hover/foco e transição suave (`transition-all duration-200`)
- [ ] 3 estados da linha do tempo funcionando (concluída / atual / pendente) com conectores coloridos corretamente
- [ ] Sem quebra de layout em telas pequenas
- [ ] Visual sóbrio, elegante e profissional — sem exagero de efeitos
- [ ] Código mantém a estrutura funcional existente, apenas aprimora o estilo
- [ ] `LOG-ALTERACOES.md` atualizado com esta alteração de estilo

---

*Fim do Prompt — 2026-10-07*
