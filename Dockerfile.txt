FROM php:8.2-apache

# On copie tous les fichiers du projet dans le dossier web d'Apache
COPY . /var/www/html

# Active le module rewrite d’Apache (optionnel, utile si tu as des routes dynamiques)
RUN a2enmod rewrite

# Donne les droits d’écriture à Apache (utile pour modifier les fichiers .json)
RUN chown -R www-data:www-data /var/www/html

# Expose le port 80 (par défaut pour Apache)
EXPOSE 80

# Démarre Apache
CMD ["apache2-foreground"]
