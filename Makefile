DOCKER_IMAGE_NAME=docker.box:5000/blind-openings
COMPOSE_PROJECT_NAME ?= $(notdir $(CURDIR))
NETWORK_NAME := $(COMPOSE_PROJECT_NAME)_default
include .env
export

.PHONY: download-posters download-video

build:
	docker build -t ${DOCKER_IMAGE_NAME}-form-web:latest --target form-web .
	docker build -t ${DOCKER_IMAGE_NAME}-form-php:latest --target form-php .
	docker build -t ${DOCKER_IMAGE_NAME}-panel-web:latest --target panel-web .
	docker build -t ${DOCKER_IMAGE_NAME}-panel-php:latest --target panel-php .
push:
	docker push ${DOCKER_IMAGE_NAME}-form-web:latest
	docker push ${DOCKER_IMAGE_NAME}-form-php:latest
	docker push ${DOCKER_IMAGE_NAME}-panel-web:latest
	docker push ${DOCKER_IMAGE_NAME}-panel-php:latest

migrate-panel:
	docker compose exec panel-php php /app/vendor/bin/doctrine-migrations migrate -n

migrate-form:
	docker compose exec form-php php /app/vendor/bin/doctrine-migrations migrate -n

import:
	docker compose exec panel-php php /app/bin/import.php

fetch:
	docker compose exec panel-php php /app/bin/fetch.php

download-posters:
	@DB_HOST=$(DB_PANEL_HOST) \
	DB_NAME=$(DB_PANEL_NAME) \
	DB_USER=$(DB_PANEL_USER) \
	DB_PASS=$(DB_PANEL_PASS) \
	API_IMAGES=$(API_IMAGES) \
	docker run --rm --init \
	 	--user $(shell id -u):$(shell id -g) \
		--network $(NETWORK_NAME) \
		-v $(CURDIR)/tools:/app \
		-v $(CURDIR)/image:/image \
		-e DB_HOST -e DB_NAME -e DB_USER -e DB_PASS -e API_IMAGES \
		${DOCKER_IMAGE_NAME}-panel-php:latest \
		php /app/posterDownloader.php
	./tools/convertImages.sh ./image

download-video:
	@DB_HOST=$(DB_PANEL_HOST) \
	DB_NAME=$(DB_PANEL_NAME) \
	DB_USER=$(DB_PANEL_USER) \
	DB_PASS=$(DB_PANEL_PASS) \
	docker run --rm --init \
	 	--user $(shell id -u):$(shell id -g) \
		--network $(NETWORK_NAME) \
		-v $(CURDIR)/tools:/app \
		-v $(CURDIR)/video:/video \
		-e DB_HOST -e DB_NAME -e DB_USER -e DB_PASS -e API_IMAGES \
		${DOCKER_IMAGE_NAME}-panel-php:latest \
		php /app/videoDownloader.php
