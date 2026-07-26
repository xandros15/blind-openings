DOCKER_IMAGE_NAME=docker.box:5000/blind-openings

build:
	docker build -t ${DOCKER_IMAGE_NAME}-web:latest --target web .
	docker build -t ${DOCKER_IMAGE_NAME}-php:latest --target php .
push:
	docker push ${DOCKER_IMAGE_NAME}-web:latest
	docker push ${DOCKER_IMAGE_NAME}-php:latest
