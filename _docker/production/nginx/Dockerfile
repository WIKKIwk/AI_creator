FROM nginx:1.26.0-alpine

COPY ./_docker/production/nginx/conf.d /etc/nginx/conf.d

COPY ./public /app/public

EXPOSE 80
