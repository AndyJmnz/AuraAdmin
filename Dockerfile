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

# Configuración de Apache para servir index.php por defecto y permitir .htaccess
RUN echo "<Directory /var/www/html/>\n    AllowOverride All\n    Require all granted\n</Directory>\nDirectoryIndex index.php" > /etc/apache2/conf-available/auraadmin.conf && a2enconf auraadmin

EXPOSE 80

# Configurar Apache para usar el puerto de Railway si está definido
RUN echo 'if [ ! -z "$PORT" ]; then sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf; fi' > /usr/local/bin/start-apache.sh && chmod +x /usr/local/bin/start-apache.sh

CMD ["/bin/bash", "-c", "/usr/local/bin/start-apache.sh && apache2-foreground"]
