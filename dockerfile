FROM rdanieli/php74_zts

ENV PHP_MEMORY_LIMIT=-1

COPY . /app

COPY ./bin/dbsnoop /bin

WORKDIR /app

CMD [ "php", "./helpers/Run.php"]