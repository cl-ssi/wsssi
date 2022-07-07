# Servicio de Salud Iquique

# WS-SSI - Sistema proxy de webservices

## Dependencias Windows con WSL2
- [Instalar WSL2](https://docs.microsoft.com/es-es/windows/wsl/install)
- [Instalar Docker Desktop](https://docs.docker.com/desktop/windows/install)
- Instalar Git en WSL2 (ej: ```$ sudo apt-get install git```)

## Dependencias Mac
- [Instalar Docker Desktop](https://www.docker.com/get-started/)
- Instalar Git

## Instalación
- Abrir un terminal de WSL (opcional [Instalar Windows Terminal](https://docs.microsoft.com/es-es/windows/terminal/))
- ```git clone https://github.com/cl-ssi/wsssi```
- ```cd wsssi```
- ```cp .env.example .env```
- Configurar usuarios .env
- ```docker build -t wsssi docker/dev```
- ```docker run --rm -it -v $(pwd):/var/www/html -p 8000:8000 -d --name wsssi wsssi```
- ```docker exec -it wsssi /bin/bash```
- Esto abrirá un contenedor con nuestra aplicación
- ```su tic```
- ```composer install```
- ```php artisan key:generate```
- ```php -S 0.0.0.0:8000 -t public```
- Pudese usar el alias ```$ serve``` para este último comando, ver todos los alias: ```$ alias```

## Para detener el contenedor
- ```docker stop wsssi```
## Abrir el navegador
- Ir a http://localhost:8000