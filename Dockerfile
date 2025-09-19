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

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Instalar dependencias PHP
WORKDIR /var/www/html
RUN composer install

# Permisos
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Configuración de Apache para servir index.php por defecto y permitir .htaccess
RUN echo "<Directory /var/www/html/>\n    AllowOverride All\n    Require all granted\n</Directory>\nDirectoryIndex index.php" > /etc/apache2/conf-available/auraadmin.conf && a2enconf auraadmin

EXPOSE 80

CMD ["apache2-foreground"]
