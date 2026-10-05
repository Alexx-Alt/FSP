# Локальная MySQL

В PowerShell из корня проекта:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/configure-local-db.ps1
```

Скрипт переключает Laravel на MySQL и базу `fsp_test2`. Существующие адрес сервера, порт, имя пользователя и пароль сохраняются. Проверь их в локальном `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fsp_test2
DB_USERNAME=root
DB_PASSWORD=твой_пароль
```

После изменения пароля выполни `php artisan config:clear`.
Проверка подключения без изменения таблиц:

```powershell
php artisan db:show
```

База должна быть заранее создана в MySQL. Сессии и кеш используют файлы и не требуют дополнительных таблиц. Не запускай стандартные миграции Laravel на этой базе: таблицы `users` и `jobs` из твоего SQL уже существуют и отличаются от стандартных.

Текущий этап — оформление страниц. Для работающего входа потребуется адаптировать модель и авторизацию Laravel к полям `user_id`, `username`, `password_hash` и отсутствующим служебным столбцам.
