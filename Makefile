DOCKER_IMAGE_NAME=docker.box:5000/blind-openings

build:
	docker build -t ${DOCKER_IMAGE_NAME}-web:latest --target web .
	docker build -t ${DOCKER_IMAGE_NAME}-php:latest --target php .
	docker build -t ${DOCKER_IMAGE_NAME}-panel-web:latest --target panel-web .
	docker build -t ${DOCKER_IMAGE_NAME}-panel-php:latest --target panel-php .
push:
	docker push ${DOCKER_IMAGE_NAME}-web:latest
	docker push ${DOCKER_IMAGE_NAME}-php:latest
	docker push ${DOCKER_IMAGE_NAME}-panel-web:latest
	docker push ${DOCKER_IMAGE_NAME}-panel-php:latest
