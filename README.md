# Clínica Sorriso e Vida — Agenda Odontológica

Sistema independente de agendamento odontológico com painel administrativo e atendimento automatizado pelo WhatsApp.

## Funcionalidades

- Pacientes, dentistas e procedimentos.
- Vínculo entre profissionais e procedimentos.
- Horários semanais configuráveis e bloqueios para folgas/férias.
- Agenda sem conflito, respeitando a duração de cada procedimento.
- Agendamento, consulta, confirmação, cancelamento e reagendamento pelo WhatsApp.
- Lembretes automáticos em até 24 horas e 2 horas antes.
- Painel Filament com agenda e indicadores do dia.
- PostgreSQL, Redis, filas, scheduler e Evolution API em Docker.

## Rodar localmente

```bash
cp .env.example .env
make up
make composer c="install"
make artisan c="key:generate"
make fresh
```

- Painel: <http://localhost:8001/admin>
- Evolution Manager: <http://localhost:8081/manager>
- Login inicial: `admin@sorrisoevida.com.br`
- Senha inicial: `password` (troque antes de publicar)

Os seeders criam uma dentista, quatro procedimentos e expediente de segunda a sexta, 8h–12h e 14h–18h. Substitua os exemplos no painel.

## WhatsApp

Preencha `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE` e `EVOLUTION_WEBHOOK_TOKEN`. Webhook:

```text
POST /api/webhooks/evolution?token=SEU_TOKEN
```

O container `queue` processa conversas e o `scheduler` dispara lembretes a cada 15 minutos.

## Regras

- `BOOKING_MINIMUM_NOTICE_HOURS`: antecedência mínima.
- `BOOKING_MAX_DAYS_AHEAD`: limite futuro da agenda.
- O horário é revalidado em transação antes de confirmar.
- Consultas canceladas permanecem no histórico.

## Testes

```bash
make test
```
