# syntax=docker/dockerfile:1

# Stage 1: Composer dependencies
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

# Stage 2: Node assets
FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 3: PHP app on debian (needed for LaTeX/Pandoc/R)
FROM php:8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive

# System packages: tools, PHP build deps, LaTeX, Pandoc, R
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl ca-certificates unzip xz-utils git \
    libsqlite3-dev sqlite3 libxml2-dev libonig-dev libicu-dev libzip-dev libpng-dev \
    texlive-latex-base \
    texlive-latex-recommended \
    texlive-latex-extra \
    texlive-xetex \
    texlive-luatex \
    texlive-fonts-recommended \
    texlive-fonts-extra \
    texlive-lang-european \
    texlive-science \
    texlive-bibtex-extra \
    lmodern \
    fontconfig \
    pandoc \
    r-base \
    r-base-dev \
    libcurl4-openssl-dev libssl-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Project fonts (e.g. Quicksand, used by some imported LaTeX projects).
# Mirror the legacy Overleaf path so imported tex with explicit Path= works.
COPY resources/fonts/ /usr/local/share/fonts/project/
RUN mkdir -p /usr/share/fonts/truetype/Quicksand/static \
    && cp /usr/local/share/fonts/project/Quicksand-*.ttf /usr/share/fonts/truetype/Quicksand/static/ \
    && cp /usr/local/share/fonts/project/Asap-*.ttf /usr/share/fonts/truetype/Quicksand/static/ \
    && fc-cache -fv > /dev/null

# CTAN packages not shipped by Debian's texlive
RUN mkdir -p /usr/share/texlive/texmf-dist/tex/latex/soul \
    && curl -fsSL https://mirrors.ctan.org/macros/latex/contrib/soul.zip -o /tmp/soul.zip \
    && unzip -j -o /tmp/soul.zip 'soul/soul.sty' 'soul/soulutf8.sty' \
        -d /usr/share/texlive/texmf-dist/tex/latex/soul/ \
    && rm /tmp/soul.zip \
    && mktexlsr

# Typst (single static binary)
RUN curl -fsSL https://github.com/typst/typst/releases/latest/download/typst-x86_64-unknown-linux-musl.tar.xz -o /tmp/typst.tar.xz \
    && tar -xJf /tmp/typst.tar.xz -C /tmp \
    && mv /tmp/typst-x86_64-unknown-linux-musl/typst /usr/local/bin/typst \
    && chmod +x /usr/local/bin/typst \
    && rm -rf /tmp/typst*

# PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite mbstring xml intl zip

# R packages used by app
RUN R -e "install.packages(c('rmarkdown','jsonlite','knitr'), repos='https://cloud.r-project.org')"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && cp -r public public-image

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
