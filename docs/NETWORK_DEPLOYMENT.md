# Развёртывание ИТ-МЕТЧ в локальной сети

## 1. Подготовка

Установите Docker Desktop или Docker Engine с Compose. Скопируйте пример окружения и создайте секреты:

```powershell
Copy-Item .env.example .env
npm run env:setup
```

В `.env` укажите адрес компьютера-сервера:

```env
APP_NAME="ИТ-МЕТЧ"
APP_URL=http://192.168.1.50
PUBLIC_PORT=80
APP_ENV=production
APP_DEBUG=false
```

IP можно узнать командой `ipconfig` (Windows) или `ip addr` (Linux).

## 2. Запуск в сети

```powershell
docker compose -f docker-compose.yml -f docker-compose.network.yml up -d --build
```

Проверка:

```powershell
docker compose -f docker-compose.yml -f docker-compose.network.yml ps
docker compose -f docker-compose.yml -f docker-compose.network.yml logs -f app gateway
```

Другие устройства открывают `http://192.168.1.50` (подставьте IP сервера).

## 3. Брандмауэр

Разрешите входящие TCP-подключения к `PUBLIC_PORT` только для профиля «Частная сеть». MySQL-порты наружу не публикуются: обе базы доступны лишь контейнерам проекта.

## 4. Интернет и HTTPS

Для публичного сервера задайте домен в `APP_URL`, установите `SESSION_SECURE_COOKIE=true` и поставьте перед gateway TLS-прокси/балансировщик (Caddy, Traefik, Nginx Proxy Manager или облачный load balancer). Не публикуйте порты 3306 обеих баз. Храните `.env` вне Git и делайте резервные копии обоих Docker volumes.

## 5. Остановка

```powershell
docker compose -f docker-compose.yml -f docker-compose.network.yml down
```

Команда без `-v` сохраняет данные. Параметр `-v` удалит обе базы и применяется только при осознанном полном сбросе.
