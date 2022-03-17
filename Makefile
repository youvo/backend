default: help

## help		:	Print commands help.
.PHONY: help
help : Makefile
	@sed -n 's/^##//p' $<

## mm-on		:	Maintenance mode on.
.PHONY: mm-on
mm-on:
	@vendor/bin/drush sset system.maintenance_mode 1
	@vendor/bin/drush cr --quiet
	@echo "Maintenance mode on."

## mm-off		:	Maintenance mode off.
.PHONY: mm-off
mm-off:
	@vendor/bin/drush sset system.maintenance_mode 0
	@vendor/bin/drush cr --quiet
	@echo "Maintenance mode off."

## cr		:	Clear caches.
.PHONY: cr
cr:
	@vendor/bin/drush cr --quiet
	@echo "Caches cleared."

## warm		:	Warm caches.
.PHONY: warm
warm:
	@vendor/bin/drush warmer:enqueue jsonapi --run-queue

## install	:	(Re-)install Drupal.
.PHONY: install
install:
	@if [[ ${PWD} == *"_dev/backend"* ]] ; then \
  	if $(MAKE) -s confirm-install 2>/dev/null ; then \
  	  ./scripts/install-development.sh ;	\
  	fi \
	else \
		echo "Command not available outside of development environment." ;	\
	fi

.PHONY: confirm-install
confirm-install:
	@if [[ -z "$(CI)" ]]; then \
  	echo "Execution will reset current installation. Are you sure? [y/n] > " ; \
		REPLY="" ; read -r ; \
		if [[ ! $$REPLY =~ ^[Yy]$$ ]]; then exit 1; else exit 0; fi \
	fi


## rebuild	:	Calculate rebuild token.
.PHONY: rebuild
rebuild:
	@echo "/core/rebuild.php?"\
	"$(shell ./web/core/scripts/rebuild_token_calculator.sh)"

## restart-php	:	Restart Uberspace PHP.
.PHONY: restart-php
restart-php:
	@uberspace tools restart php

