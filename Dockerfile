FROM php:8.4-apache

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite && a2enmod headers

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Create Apache config
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/conf-available/default.conf && \
a2enconf default

# Use port from environment or default to 8080
ENV PORT=8080
EXPOSE 8080

# Start Apache on specified port
CMD ["sh", "-c", "sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf && apache2-foreground"]
