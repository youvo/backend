default: help

## help	:	Print commands help.
.PHONY: help
help : Makefile
	@sed -n 's/^##//p' $<

## mm-on	:	Maintenance mode on.
.PHONY: mm-on
mm-on:
	@vendor/bin/drush sset system.maintenance_mode 1
	@vendor/bin/drush cr
	@echo "Maintenance mode on."

## mm-off	:	Maintenance mode off.
.PHONY: mm-off
mm-off:
	@vendor/bin/drush sset system.maintenance_mode 0
	@vendor/bin/drush cr
	@echo "Maintenance mode off."

## cr	:	Clear caches.
.PHONY: cr
cr:
	@vendor/bin/drush cr

