FROM php:8.4-fpm

# Install system dependencies (Heavy, stable layer)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    gnupg \
    libuv1-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    libfontconfig1-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# Install R and dependencies (Very heavy layer)
RUN apt-get update && apt-get install -y \
    r-base \
    && rm -rf /var/lib/apt/lists/*

RUN Rscript -e "install.packages(c('jsonlite', 'rmarkdown', 'knitr', 'yaml', 'htmltools', 'caTools', 'bitops'), repos='https://cloud.r-project.org')"

# Install TeX Live (The heaviest layer)
RUN apt-get update && apt-get install -y \
    texlive-latex-base \
    texlive-latex-extra \
    texlive-fonts-recommended \
    texlive-fonts-extra \
    texlive-science \
    texlive-xetex \
    texlive-luatex \
    texlive-lang-european \
    latexmk \
    biber \
    pandoc \
    && rm -rf /var/lib/apt/lists/*

# LaTeX Permissions
RUN for f in $(find /etc/texmf /usr/share/texlive -name texmf.cnf); do \
        sed -i 's/openout_any = [pr]/openout_any = a/' $f || true; \
        sed -i 's/openin_any = [pr]/openin_any = a/' $f || true; \
        echo "openout_any = a" >> $f; \
        echo "openin_any = a" >> $f; \
    done

# Install Typst
RUN curl -L https://github.com/typst/typst/releases/latest/download/typst-x86_64-unknown-linux-musl.tar.xz | tar -xJ && \
    mv typst-x86_64-unknown-linux-musl/typst /usr/local/bin/ && \
    rm -rf typst-x86_64-unknown-linux-musl

# --- Configuration (Fast layers, at the end) ---

# Increase PHP memory limit for syncing
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www

RUN composer install --no-interaction --optimize-autoloader
RUN npm install && npm run build

RUN mkdir -p /var/www/texmf && \
    echo "openout_any = a" > /var/www/texmf/texmf.cnf && \
    echo "openin_any = a" >> /var/www/texmf/texmf.cnf

RUN mkdir -p /usr/share/fonts/truetype/Quicksand/static
COPY resources/fonts/*.ttf /usr/share/fonts/truetype/Quicksand/static/
RUN fc-cache -f -v

RUN mkdir -p /var/www/storage/app/workspaces /var/www/storage/app/public/outputs /var/www/public_shared/build && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public /var/www/texmf /var/www/public_shared

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
