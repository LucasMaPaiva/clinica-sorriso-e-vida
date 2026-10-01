# Clínica Sorriso e Vida — Agenda Odontológica

Sistema independente de agendamento odontológico com painel administrativo e atendimento automatizado pelo WhatsApp.

## Manual de uso

- [Manual em PDF para recepção e pacientes](docs/Manual-de-Uso-Clinica-Sorriso-e-Vida.pdf)
- [Guia em PDF para teste em dupla e Evolution](docs/Guia-de-Teste-em-Dupla.pdf)
- [Fonte editável do manual](docs/manual/manual.html)
- [Fonte editável do guia de teste](docs/partner-test/guide.html)

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
make install
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

## Publicar em homologação

O instalador constrói imagens imutáveis com o código, `vendor` e assets dentro
delas. Não é necessário instalar Composer, criar `vendor` ou corrigir UID/GID no
servidor.

```bash
cp .env.example .env
nano .env
make install
```

Antes de executar, configure no `.env`:

- `APP_ENV=staging` (ou `production` na publicação definitiva);
- `APP_URL` com o domínio HTTPS;
- senhas fortes em `DB_PASSWORD` e `ADMIN_SEED_PASSWORD`;
- `EVOLUTION_API_KEY` e `EVOLUTION_WEBHOOK_TOKEN` aleatórios;
- `EVOLUTION_ALLOWED_NUMBERS` somente com os testadores.

O comando cria a rede `proxy_network` quando necessário, gera `APP_KEY`, força
as opções seguras de homologação, constrói as imagens, executa migrations e
seeders, otimiza o Laravel e reinicia os workers.

Para atualizações futuras, use novamente `git pull && make install`. O ambiente
(`local`, `staging` ou `production`) e todas as portas vêm exclusivamente do
`.env`. Não rode `composer install` manualmente no servidor: a imagem de
homologação já contém o `vendor`.
