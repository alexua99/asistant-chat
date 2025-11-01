#!/bin/bash

# Простой запуск сервера с Cloudflare туннелем (без токена)

echo "🚀 Запуск сервера с Cloudflare туннелем"
echo ""

# Проверка cloudflared
if ! command -v cloudflared &> /dev/null; then
    echo "❌ cloudflared не установлен"
    echo "Установите его:"
    echo "  wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O /usr/local/bin/cloudflared"
    echo "  chmod +x /usr/local/bin/cloudflared"
    exit 1
fi

# Запуск сервера в фоне
echo "Запуск сервера на порту 3000..."
npm start &
SERVER_PID=$!

# Ждем немного, чтобы сервер успел запуститься
sleep 2

echo ""
echo "✅ Сервер запущен (PID: $SERVER_PID)"
echo ""
echo "🌐 Запуск Cloudflare туннеля..."
echo "   (Без токена, временный URL будет создан автоматически)"
echo ""
echo "=========================================="
echo "Ваш сервер будет доступен по URL, который появится ниже"
echo "URL будет в формате: https://xxxxx.trycloudflare.com"
echo "=========================================="
echo ""

# Запуск туннеля БЕЗ конфигурации и БЕЗ токена
# Используем только --url для быстрого туннеля
# Переменные окружения гарантируют что не будут использоваться конфиги
echo "Запуск быстрого туннеля (без токена и конфигурации)..."
unset CLOUDFLARED_CONFIG_PATH
cloudflared tunnel --url http://localhost:3000 2>&1 | while IFS= read -r line; do
    # Показываем все строки
    echo "$line"
    
    # Если найдем URL - выделяем его
    if [[ "$line" == *"trycloudflare.com"* ]]; then
        echo ""
        echo "=========================================="
        echo "✅ ВАШ ПУБЛИЧНЫЙ URL:"
        echo "$line" | grep -o 'https://[^ ]*trycloudflare.com[^ ]*' | head -1
        echo "=========================================="
        echo ""
    fi
done

# При остановке (Ctrl+C) - остановим сервер
trap "kill $SERVER_PID 2>/dev/null" EXIT

