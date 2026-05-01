FROM php:8.3-apache

# Enable MySQL PDO driver
RUN docker-php-ext-install pdo_mysql

# Ensure only one Apache MPM is enabled.
RUN a2dismod mpm_event mpm_worker || true \
	&& a2enmod mpm_prefork

# Use a vhost that serves /public as the document root
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Optional: allow .htaccess if you add it later
RUN a2enmod rewrite

# Copy app
COPY . /var/www/html

# Render sets $PORT; make Apache listen on it
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["start.sh"]
