FROM dbsnoop/php:latest

RUN apt install vim cron -y

COPY ./src /app/src
COPY ./helpers /app/helpers
COPY ./vendor /app/vendor
COPY ./app.php /app/
COPY ./bootstrap.php /app/
COPY ./composer.json /app/
COPY ./composer.lock /app/
COPY ./bin/dbsnoop /bin
COPY ./helpers/root /var/spool/cron/crontabs

WORKDIR /app

CMD [ "php", "./helpers/Run.php"]
#CMD ["/bin/bash"]