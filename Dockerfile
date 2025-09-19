# Dockerfile para AuraAdmin
FROM php:8.2-apache

# Instalar dependencias del sistema necesarias para Composer y PHP
RUN apt-get update \
    && apt-get install -y git zip unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Limpiar el directorio por defecto de Apache y copiar archivos del proyecto
RUN rm -rf /var/www/html/*
COPY . /var/www/html/

# Crear .env si no existe (para evitar errores)
RUN if [ ! -f /var/www/html/.env ]; then \
    echo "DB_HOST=localhost" > /var/www/html/.env && \
    echo "DB_NAME=aura" >> /var/www/html/.env && \
    echo "DB_USER=root" >> /var/www/html/.env && \
    echo "DB_PASS=" >> /var/www/html/.env; \
    fi

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Instalar dependencias PHP
WORKDIR /var/www/html
RUN composer install

# Permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Configurar directorio de sesiones PHP
RUN mkdir -p /tmp/sessions && chown www-data:www-data /tmp/sessions && chmod 755 /tmp/sessions
RUN echo "session.save_path = '/tmp/sessions'" >> /usr/local/etc/php/conf.d/sessions.ini

# Configurar logs de PHP para debugging
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/logging.ini && \
    echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/logging.ini && \
    echo "display_errors = On" >> /usr/local/etc/php/conf.d/logging.ini && \
    echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/logging.ini && \
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/logging.ini && \
    touch /var/log/php_errors.log && \
    chown www-data:www-data /var/log/php_errors.log

# Configurar ServerName de Apache para evitar warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configuración de Apache para servir index.php por defecto y permitir .htaccess
RUN echo "<Directory /var/www/html/>\n    AllowOverride All\n    Require all granted\n</Directory>\nDirectoryIndex index.php" > /etc/apache2/conf-available/auraadmin.conf && a2enconf auraadmin

EXPOSE 80

# Script de inicio que configura el puerto y inicia Apache
RUN printf '#!/bin/bash\n\
echo "=== Configurando Apache ==="\n\
if [ ! -z "$PORT" ]; then\n\
  echo "Listen $PORT" > /etc/apache2/ports.conf\n\
  sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf\n\
  echo "Apache configurado para puerto: $PORT"\n\
else\n\
  echo "Usando puerto por defecto: 80"\n\
fi\n\
\n\
echo "=== Iniciando aplicación ==="\n\
echo "PORT: $PORT"\n\
echo "Variables de entorno:"\n\
env | grep -E "(DB_|MYSQL_|PORT)" || echo "No hay variables DB/MYSQL"\n\
\n\
echo "=== Iniciando Apache ==="\n\
exec apache2-foreground\n' > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]