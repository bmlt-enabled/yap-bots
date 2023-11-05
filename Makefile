COMMIT := $(shell git rev-parse --short=8 HEAD)
ZIP_FILENAME := $(or $(ZIP_FILENAME), $(shell echo "$${PWD\#\#*/}.zip"))
BUILD_DIR := $(or $(BUILD_DIR),"build")
ZIP_FILE := build/yap-bots.zip
VENDOR_AUTOLOAD := vendor/autoload.php

ifeq ($(PROD)x, x)
	COMPOSER_ARGS := --prefer-dist --no-progress
else
	COMPOSER_ARGS := --no-dev
endif

help:  ## Print the help documentation
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

$(ZIP_FILE): $(VENDOR_AUTOLOAD)
	git archive --format=zip --output=${ZIP_FILENAME} $(COMMIT)
	zip -r ${ZIP_FILENAME} vendor/
	mkdir -p ${BUILD_DIR} && mv ${ZIP_FILENAME} ${BUILD_DIR}/

$(VENDOR_AUTOLOAD):
	composer install $(COMPOSER_ARGS)

.PHONY: composer
composer: $(VENDOR_AUTOLOAD) ## Runs composer install

.PHONY: build
build: $(ZIP_FILE)  ## Build

.PHONY: clean
clean:  ## clean
	rm -rf build

.PHONY: simulate
simulate: dev  ## Simulate
	ngrok http 8005

.PHONY: fmt
fmt: composer ## PHP Format
	vendor/squizlabs/php_codesniffer/bin/phpcbf

.PHONY: lint
lint: composer ## PHP Lint
	vendor/squizlabs/php_codesniffer/bin/phpcs

.PHONY: dev
dev:  ## Docker up
	docker-compose up
