# Použití oficiálního PHP obrazu s Apachem
FROM php:8.1-apache

# Instalace rozšíření pro MySQL (pokud používáš databázi)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Nastavení pracovní složky na Apache serveru
WORKDIR /var/www/html

# Kopírování kódu do kontejneru
COPY vozovy_park/ /var/www/html/

# Udělení správných oprávnění
RUN chown -R www-data:www-data /var/www/html

# Otevření portu 80
EXPOSE 80

# Spuštění Apache serveru
CMD ["apache2-foreground"]
