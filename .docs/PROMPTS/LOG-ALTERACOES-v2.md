# LOG DE ALTERAÇÕES — SenhaBaixa

**Sistema de Agendamento e Disparo Automático via Telegram**
Projeto em Laravel · Curitiba/PR · 2026

---

## 📋 Histórico de Versões

| Data | Versão | Área | Descrição | Responsável |
|---|---|---|---|---|
| 23/09/2026 | 1.0.0 | Estrutura | Projeto Laravel criado e pré-configurado | Jackson Dos Santos |
| 23/09/2026 | 1.1.0 | Documentação | Prompt de implementação v1 criado (entendimento inicial) | Jackson Dos Santos |
| 24/09/2026 | 2.0.0 | Fluxo | ✅ Atualização conforme diretrizes da cliente: visitante NÃO precisa de conta Telegram; texto livre = "senha"; ordem de envio = posição; disparo automático no horário exato; vários grupos por agendamento | Jackson Dos Santos |
| 24/09/2026 | 2.0.1 | Técnico | Socialite apenas para conta do robô/admin; rotas públicas de agendamento; cálculo de posição por grupo+horário; grupos pré-cadastrados pelo admin; remoção de login do visitante | Jackson Dos Santos |
| [próxima] | 2.1.0 | Implementação | Instalação Socialite + migrations (grupos, agendamentos) + controllers + rotas + scheduler + filas + telas Blade + envio via Telegram Bot API | — |

---

## 🔄 Próximas Implementações

- [ ] Instalação do Socialite e provider Telegram (apenas para o robô)
- [ ] Criação de migrations: `grupos`, `agendamentos`
- [ ] Models e relacionamentos
- [ ] Rotas públicas (agendamento) e protegidas (admin)
- [ ] Controladores: Agendamento, Grupo, Admin, Telegram
- [ ] Job de disparo + Scheduler a cada minuto
- [ ] Telas Blade com identidade visual Telegram (responsivas)
- [ ] Cálculo e exibição da posição
- [ ] Área administrativa completa (grupos, agendamentos, configurações)
- [ ] Testes de fluxo completo com link de teste para a cliente
- [ ] Validação e tratamento de erros de envio

---

## 📌 Decisões Confirmadas com a Cliente (24/09/2026)

1. **Visitante não precisa de conta no Telegram** — acessa por link, agenda pelo navegador
2. **Texto livre** — o que a pessoa digita é o que aparece no grupo (ex: `Roberta lallison x8`)
3. **Ordem = posição** — quem aparece primeiro no grupo é a primeira senha
4. **Disparo no horário exato** — envios antes do horário "queimam" e não valem
5. **Vários grupos** — a pessoa escolhe o grupo de destino a cada agendamento
6. **Sem apresentação na porta** — a equipe organiza o caderno pela ordem no grupo
7. **Link de convite** — a pessoa pode encaminhar o link para outras agendarem

---

*Este arquivo será atualizado a cada implementação concluída, mantendo o histórico de evolução do sistema.*
