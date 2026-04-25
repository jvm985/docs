FROM php:8.4-fpm

# Install system dependencies
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

# Install R and dependencies
RUN apt-get update && apt-get install -y \
    r-base \
    && rm -rf /var/lib/apt/lists/*

# Install R packages (explicitly including dependencies)
RUN Rscript -e "install.packages(c('jsonlite', 'rmarkdown', 'knitr', 'yaml', 'htmltools', 'caTools', 'bitops'), repos='https://cloud.r-project.org')"

# Install TeX Live and Pandoc
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

# Versoepel TeX Live beveiliging om schrijven naar ../ mappen toe te staan (nodig voor gedeelde projecten)
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Install dependencies and build assets
RUN composer install --no-interaction --optimize-autoloader --no-dev
RUN npm install && npm run build

# Forceer LaTeX permissies via een eigen configuratiebestand
RUN mkdir -p /var/www/texmf && \
    echo "openout_any = a" > /var/www/texmf/texmf.cnf && \
    echo "openin_any = a" >> /var/www/texmf/texmf.cnf

# Adjust permissions
RUN mkdir -p /var/www/storage/app/workspaces && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public /var/www/texmf && \
    chmod -R 775 /var/www/storage

EXPOSE 9000
CMD ["php-fpm"]
