# ============================================================
# Clínica Sorriso e Vida — comandos do dia a dia
#
#   make up       sobe o ambiente (local inclui postgres + evolution)
#   make down     derruba tudo
#   make build    atualiza SÓ o back (app/queue/scheduler) sem derrubar o resto
#   make help     lista tudo
# ============================================================

# Detecta o ambiente pelo .env: local carrega também o compose local
APP_ENV := $(shell grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2)

ifeq ($(APP_ENV),local)
COMPOSE := docker compose -f docker-compose.yml -f docker-compose.local.yml
else
COMPOSE := docker compose -f docker-compose.yml
endif

# Serviços PHP — os únicos recriados no make build
BACK_SERVICES := app queue scheduler

.DEFAULT_GOAL := help

.PHONY: help up down build restart ps logs shell tinker composer artisan \
        migrate fresh seed test pint assets

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Sobe o ambiente completo
	$(COMPOSE) up -d

down: ## Derruba o ambiente
	$(COMPOSE) down

build: ## Rebuilda e recria SÓ o back (nginx/redis/postgres/evolution ficam de pé)
	$(COMPOSE) up -d --build --no-deps $(BACK_SERVICES)

restart: ## Reinicia só o back, sem rebuild
	$(COMPOSE) restart $(BACK_SERVICES)

ps: ## Status dos containers
	$(COMPOSE) ps

logs: ## Acompanha os logs (use s=app para um serviço específico)
	$(COMPOSE) logs -f $(s)

shell: ## Abre um shell no container app
	$(COMPOSE) exec app sh

tinker: ## Abre o tinker
	$(COMPOSE) exec app php artisan tinker

composer: ## Roda composer no container (ex: make composer c="require foo/bar")
	$(COMPOSE) exec app composer $(c)

artisan: ## Roda artisan no container (ex: make artisan c="route:list")
	$(COMPOSE) exec app php artisan $(c)

migrate: ## Roda as migrations
	$(COMPOSE) exec app php artisan migrate

fresh: ## APAGA TUDO e recria o banco com seeders (bloqueado fora de local)
ifneq ($(APP_ENV),local)
	$(error "make fresh é só para ambiente local (APP_ENV=$(APP_ENV))")
endif
	$(COMPOSE) exec app php artisan migrate:fresh --seed

seed: ## Roda os seeders
	$(COMPOSE) exec app php artisan db:seed

test: ## Roda a suíte de testes (Pest)
	$(COMPOSE) exec app php artisan test

pint: ## Formata o código (Laravel Pint)
	$(COMPOSE) exec app vendor/bin/pint

assets: ## Compila os assets do front (Vite) via container node
	$(COMPOSE) run --rm assets sh -c "npm ci && npm run build"
