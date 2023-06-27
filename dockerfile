FROM rdanieli/php74_zts

ENV PHP_MEMORY_LIMIT=-1

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --install-dir=/bin --filename=composer

RUN apt install gnupg -y && \
    curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - &&\
    curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list &&\
    apt-get update &&\
    ACCEPT_EULA=Y apt-get install -y msodbcsql18

RUN chmod +rwx /etc/ssl/openssl.cnf && sed -i 's/TLSv1.2/TLSv1/g' /etc/ssl/openssl.cnf && sed -i 's/SECLEVEL=2/SECLEVEL=1/g' /etc/ssl/openssl.cnf

COPY . /app

COPY ./bin/dbsnoop /bin

WORKDIR /app

CMD [ "php", "./helpers/Run.php"]
#CMD ["/bin/bash"]