#!/bin/bash

# Скрипт для запуска туннеля с конфигурацией (требует токен)
# Используйте npm run quick-tunnel для работы БЕЗ токена

echo "⚠️  ВНИМАНИЕ: Эта команда требует настройки с токеном!"
echo ""
echo "Если вы хотите запустить туннель БЕЗ токена, используйте:"
echo "  npm run quick-tunnel"
echo ""
echo "Эта команда (npm run tunnel) используется для постоянного домена"
echo "и требует:"
echo "  1. Настройки туннеля в Cloudflare Dashboard"
echo "  2. Токена и credentials файла"
echo ""
read -p "Продолжить? (y/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Отменено. Используйте 'npm run quick-tunnel' для работы без токена."
    exit 0
fi

# Проверка наличия конфигурационного файла
if [ ! -f "cloudflare-config.yaml" ]; then
    echo "❌ Файл cloudflare-config.yaml не найден"
    echo "Используйте 'npm run quick-tunnel' для работы без конфигурации"
    exit 1
fi

# Проверка что конфиг не содержит плейсхолдеры
if grep -q "YOUR_TUNNEL_ID" cloudflare-config.yaml; then
    echo "❌ Конфигурационный файл не настроен!"
    echo "Заполните cloudflare-config.yaml или используйте 'npm run quick-tunnel'"
    exit 1
fi

# Запуск туннеля
cloudflared tunnel --config cloudflare-config.yaml run

