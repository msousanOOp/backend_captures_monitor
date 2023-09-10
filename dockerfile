FROM dbsnoop/php:latest

RUN apt install vim cron -y

COPY . /app

COPY ./bin/dbsnoop /bin

COPY ./helpers/root /var/spool/cron/crontabs
WORKDIR /app

CMD [ "php", "./helpers/Run.php"]
#CMD ["/bin/bash"]