
BASIC_SPECS := basic blocks builtins data helpers partials regressions \
		 string-params subexpressions track-ids whitespace-control
SPECS := $(BASIC_SPECS)

all: spec export

spec: node_modules
	node ../vendor/jbboehr/handlebars-spec/bin/index handlebars.js/bench/templates/index.js -o spec/bench.json
	$(foreach var, $(SPECS), node ../vendor/jbboehr/handlebars-spec/bin/index handlebars.js/spec/$(var).js -o spec/$(var).json;)

export: node_modules
	node ../vendor/jbboehr/handlebars-spec/bin/export spec/bench.json -o export/bench.json
	$(foreach var, $(BASIC_SPECS), node ../vendor/jbboehr/handlebars-spec/bin/export spec/$(var).json -o export/$(var).json;)

check_changes:
	@git status --porcelain | grep 'spec/' && return 1 || return 0

stubs:
	$(foreach var, $(SPECS), php ../vendor/jbboehr/handlebars-spec/bin/stubs.php spec/$(var).json patch/$(var).json;)

test: jshint test_node test_php

test_node:
	@echo ---------- Testing spec against handlebars.js ---------- 
	node ../vendor/jbboehr/handlebars-spec/bin/runner.js

test_php:
	@echo ---------- Linting PHP code ---------- 
	php ../vendor/jbboehr/handlebars-spec/bin/lint.php $(foreach var,$(SPECS),spec/$(var).json)

jshint: node_modules
	./node_modules/.bin/jshint  bin/*.js

node_modules:
	npm install

.PHONY: all spec export check_changes test test_node test_php
