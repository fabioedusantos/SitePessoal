FROM node:22-alpine AS build

WORKDIR /app
COPY app/package.json app/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY app/ ./
ARG VITE_API_BASE=/api/v1
ARG VITE_SITE_URL=https://fbsantos.com.br
ENV VITE_API_BASE=${VITE_API_BASE} \
    VITE_SITE_URL=${VITE_SITE_URL}
RUN npm run build

FROM nginx:1.30.5-alpine
COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
