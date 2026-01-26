# Makefile para gestionar todas las demos del Performance Bundle
# Permite levantar cualquier demo individual o todas las demos

.PHONY: help list up-all down-all setup-all clean-all logs-all
.PHONY: up-symfony7 down-symfony7 setup-symfony7 clean-symfony7 logs-symfony7 shell-symfony7
.PHONY: up-symfony8 down-symfony8 setup-symfony8 clean-symfony8 logs-symfony8 shell-symfony8

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
	@echo "  setup-<demo>    Configurar una demo específica"
	@echo "  clean-<demo>    Limpiar una demo específica"
	@echo "  logs-<demo>     Ver logs de una demo específica"
	@echo "  shell-<demo>    Abrir shell en el contenedor PHP de una demo"
	@echo ""
	@echo "Ejemplos:"
	@echo "  make up-symfony7      # Levantar solo symfony7"
	@echo "  make up-symfony8      # Levantar solo symfony8"
	@echo "  make up-all           # Levantar todas las demos"
	@echo "  make down-symfony7    # Bajar solo symfony7"
	@echo "  make setup-symfony7   # Configurar symfony7 completamente"

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
