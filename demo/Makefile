# Makefile para gestionar todas las demos del Performance Bundle
# Permite levantar cualquier demo individual o todas las demos

.PHONY: help list up-all down-all setup-all clean-all logs-all release-verify
.PHONY: up-symfony7 down-symfony7 restart-symfony7 build-symfony7 install-symfony7 setup-symfony7 clean-symfony7 logs-symfony7 shell-symfony7 update-bundle-symfony7 performance-sync-symfony7
.PHONY: up-symfony8 down-symfony8 restart-symfony8 build-symfony8 install-symfony8 setup-symfony8 clean-symfony8 logs-symfony8 shell-symfony8 update-bundle-symfony8 performance-sync-symfony8
.PHONY: performance-sync-all

# Detectar demos disponibles (carpetas que contienen un Makefile)
DEMOS := $(shell find . -maxdepth 2 -mindepth 2 -name "Makefile" -type f | sed 's|./\([^/]*\)/Makefile|\1|' | sort)

# Default target
help:
	@echo "Performance Bundle - Gestión de Demos"
	@echo ""
	@echo "Uso: make <target>"
	@echo ""
	@echo "Demos disponibles:"
	@for demo in $(DEMOS); do \
		echo "  $$demo"; \
	done
	@echo ""
	@echo "Comandos generales:"
	@echo "  list            Listar todas las demos disponibles"
	@echo "  up-all          Levantar todas las demos"
	@echo "  down-all        Bajar todas las demos"
	@echo "  setup-all       Configurar todas las demos (install + DB + fixtures)"
	@echo "  clean-all       Limpiar todas las demos"
	@echo "  logs-all        Ver logs de todas las demos"
	@echo ""
	@echo "Comandos por demo (ejemplo con symfony7):"
	@echo "  up-<demo>       Levantar una demo específica"
	@echo "  down-<demo>     Bajar una demo específica"
	@echo "  restart-<demo>  Reiniciar contenedores de una demo (docker compose restart)"
	@echo "  build-<demo>    Reconstruir imagen sin cache de una demo"
	@echo "  install-<demo>  Instalar dependencias (composer) en una demo"
	@echo "  setup-<demo>    Configurar una demo específica"
	@echo "  clean-<demo>    Limpiar una demo específica"
	@echo "  logs-<demo>     Ver logs de una demo específica"
	@echo "  shell-<demo>    Abrir shell en el contenedor PHP de una demo"
	@echo "  update-bundle-<demo>  Actualizar bundle desde path repo (ej.: update-bundle-symfony7)"
	@echo ""
	@echo "Performance Bundle (schema):"
	@echo "  performance-sync-symfony7   Sync routes_data + routes_data_records en demo symfony7"
	@echo "  performance-sync-symfony8   Sync routes_data + routes_data_records en demo symfony8"
	@echo "  performance-sync-all       Sync schema en todas las demos"
	@echo ""
	@echo "Ejemplos:"
	@echo "  make up-symfony7      # Levantar solo symfony7"
	@echo "  make up-symfony8      # Levantar solo symfony8"
	@echo "  make up-all           # Levantar todas las demos"
	@echo "  make down-symfony7    # Bajar solo symfony7"
	@echo "  make setup-symfony7   # Configurar symfony7 completamente"
	@echo "  release-verify       Start each demo, healthcheck (HTTP 200), then stop (for release-check)"

# Listar demos disponibles
list:
	@echo "Demos disponibles:"
	@for demo in $(DEMOS); do \
		echo "  - $$demo"; \
	done

# Levantar todas las demos
up-all:
	@echo "🚀 Levantando todas las demos..."
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		echo "📦 Levantando demo: $$demo"; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		$(MAKE) -C $$demo up || echo "❌ Error al levantar $$demo"; \
	done
	@echo ""
	@echo "✅ Proceso completado!"

# Release verify: for each demo, up → sleep → healthcheck (HTTP 200) → down
release-verify:
	@for demo in $(DEMOS); do \
		port=$$(grep '^PORT=' $$demo/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
		[ -z "$$port" ] && port=$$(case $$demo in symfony7) echo 8000;; symfony8) echo 8001;; *) echo 8000;; esac); \
		echo "🔍 Release verify: $$demo (port $$port)..."; \
		$(MAKE) up-$$demo; \
		echo "⏳ Waiting for $$demo to be ready..."; sleep 15; \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) down-$$demo 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "❌ $$demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "✅ $$demo OK (HTTP $$code)"; \
	done
	@echo "✅ All demos passed release-verify."

# Bajar todas las demos
down-all:
	@echo "🛑 Bajando todas las demos..."
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		echo "🛑 Bajando demo: $$demo"; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		$(MAKE) -C $$demo down || echo "❌ Error al bajar $$demo"; \
	done
	@echo ""
	@echo "✅ Proceso completado!"

# Configurar todas las demos
setup-all:
	@echo "🔧 Configurando todas las demos..."
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		echo "🔧 Configurando demo: $$demo"; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		$(MAKE) -C $$demo setup || echo "❌ Error al configurar $$demo"; \
	done
	@echo ""
	@echo "✅ Proceso completado!"

# Limpiar todas las demos
clean-all:
	@echo "🧹 Limpiando todas las demos..."
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		echo "🧹 Limpiando demo: $$demo"; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		$(MAKE) -C $$demo clean || echo "❌ Error al limpiar $$demo"; \
	done
	@echo ""
	@echo "✅ Proceso completado!"

# Ver logs de todas las demos
logs-all:
	@echo "📋 Logs de todas las demos (Ctrl+C para salir)..."
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		echo "📋 Logs de demo: $$demo"; \
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; \
		$(MAKE) -C $$demo logs & \
	done
	@wait

# Comandos dinámicos para cada demo
# Estos targets se generan automáticamente para cada demo encontrada

# Symfony 7
up-symfony7:
	@echo "🚀 Levantando demo: symfony7"
	@$(MAKE) -C symfony7 up

down-symfony7:
	@echo "🛑 Bajando demo: symfony7"
	@$(MAKE) -C symfony7 down

restart-symfony7:
	@$(MAKE) -C symfony7 restart

build-symfony7:
	@$(MAKE) -C symfony7 build

install-symfony7:
	@echo "📦 Instalando dependencias en demo: symfony7"
	@$(MAKE) -C symfony7 install

install-symfony8:
	@echo "📦 Instalando dependencias en demo: symfony8"
	@$(MAKE) -C symfony8 install

setup-symfony7:
	@echo "🔧 Configurando demo: symfony7"
	@$(MAKE) -C symfony7 setup

clean-symfony7:
	@echo "🧹 Limpiando demo: symfony7"
	@$(MAKE) -C symfony7 clean

logs-symfony7:
	@echo "📋 Logs de demo: symfony7"
	@$(MAKE) -C symfony7 logs

shell-symfony7:
	@echo "🐚 Abriendo shell en demo: symfony7"
	@$(MAKE) -C symfony7 shell

# Symfony 8
up-symfony8:
	@echo "🚀 Levantando demo: symfony8"
	@$(MAKE) -C symfony8 up

down-symfony8:
	@echo "🛑 Bajando demo: symfony8"
	@$(MAKE) -C symfony8 down

restart-symfony8:
	@$(MAKE) -C symfony8 restart

build-symfony8:
	@$(MAKE) -C symfony8 build

setup-symfony8:
	@echo "🔧 Configurando demo: symfony8"
	@$(MAKE) -C symfony8 setup

clean-symfony8:
	@echo "🧹 Limpiando demo: symfony8"
	@$(MAKE) -C symfony8 clean

logs-symfony8:
	@echo "📋 Logs de demo: symfony8"
	@$(MAKE) -C symfony8 logs

shell-symfony8:
	@echo "🐚 Abriendo shell en demo: symfony8"
	@$(MAKE) -C symfony8 shell

update-bundle-symfony7:
	@$(MAKE) -C symfony7 update-bundle

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

# Performance Bundle: sync schema en demos (routes_data + routes_data_records)
performance-sync-symfony7:
	@echo "🔄 Syncing Performance Bundle schema en demo: symfony7"
	@$(MAKE) -C symfony7 performance-sync
	@echo "✅ Schema sync symfony7 listo!"

performance-sync-symfony8:
	@echo "🔄 Syncing Performance Bundle schema en demo: symfony8"
	@$(MAKE) -C symfony8 performance-sync
	@echo "✅ Schema sync symfony8 listo!"

performance-sync-all:
	@echo "🔄 Syncing Performance Bundle schema en todas las demos..."
	@$(MAKE) performance-sync-symfony7
	@$(MAKE) performance-sync-symfony8
	@echo "✅ Schema sync en todas las demos completado!"
