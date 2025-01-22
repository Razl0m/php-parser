FROM php:8.4.3-cli-alpine3.20

# Install Composer   
COPY --from=composer/composer:2.2.25-bin /composer /usr/bin/composer

# Copy the application code
COPY . /app-parser

# Set the working directory
WORKDIR /app-parser/src/

# Install the application dependencies
RUN composer install --no-dev

# Start the application
CMD [ "php", "index.php" ]

EXPOSE 443