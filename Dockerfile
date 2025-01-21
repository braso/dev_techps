FROM php:7.4-alpine

# Configurações de ambiente
ENV MAX_UPLOAD="100M"
ENV MAX_MEM="2048M"
ENV MAX_TIME="30"
ENV MAX_INPUTVARS="1000"
ENV MAX_LIFETIME="3600"
ENV MAX_INPUT_TIME="60"

# Configurações do PHP
RUN	echo "upload_max_filesize = ${MAX_UPLOAD}" >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini \
&&	echo "post_max_size = ${MAX_UPLOAD}" >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini \
&&	echo "memory_limit = ${MAX_MEM}" >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini \
&&	echo "max_execution_time = ${MAX_TIME}"   >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini \
&&	echo "max_input_vars = ${MAX_INPUTVARS}" >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini \
&&	echo "max_input_time = ${MAX_INPUT_TIME}" >> /usr/local/etc/php/conf.d/0-upload_large_dumps.ini

STOPSIGNAL SIGINT

# Instala dependências para PHP, Composer e PHPUnit
RUN	apk add --no-cache --virtual .build-deps \
    curl \
    postgresql-dev \
    sqlite-dev \
    unixodbc-dev \
    freetds-dev \
    git \
    unzip \
&&	docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
&&	docker-php-ext-install \
	mysqli \
	pdo \
	pdo_mysql \
	pdo_pgsql \
	pdo_sqlite \
	pdo_odbc \
	pdo_dblib \
&&	curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
&&	composer global require phpunit/phpunit:^9.0 \
&&	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)" \
&&	apk add --virtual .phpexts-rundeps $runDeps \
&&	apk del --no-network .build-deps

# Configuração do ambiente
RUN	addgroup -S adminer \
&&	adduser -S -G adminer adminer \
&&	mkdir -p /var/www/html \
&&	mkdir /var/www/html/plugins-enabled \
&&	chown -R adminer:adminer /var/www/html

WORKDIR /var/www/html

# Definição do usuário padrão e comando inicial
USER adminer
CMD [ "php", "-S", "[::]:80", "-t", "/var/www/html" ]

# Exposição da porta
EXPOSE 80
