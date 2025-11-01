#!/bin/bash

# Максимально простой быстрый туннель БЕЗ токена
# Используйте: npm run quick-tunnel

echo "🚀 Быстрый Cloudflare туннель (БЕЗ ТОКЕНА)"
echo ""

if ! command -v cloudflared &> /dev/null; then
    echo "❌ cloudflared не установлен"
    exit 1
fi

echo "Запуск сервера..."
npm start &
SERVER_PID=$!
sleep 3

echo ""
echo "✅ Сервер запущен"
echo ""
echo "🌐 Создание публичного URL (без токена)..."
echo ""
echo "=========================================="
echo "⚠️  ВНИМАНИЕ: Публичный URL появится ниже!"
echo "Ищите строку с 'https://' и 'trycloudflare.com'"
echo "=========================================="
echo ""

# Самый простой способ - только --url, ничего больше
cloudflared tunnel --url http://localhost:3000 2>&1 | while IFS= read -r line; do
    echo "$line"
    
    # Выделяем URL когда найдем
    if echo "$line" | grep -q "trycloudflare.com"; then
        URL=$(echo "$line" | grep -o 'https://[^ ]*\.trycloudflare\.com')
        if [ ! -z "$URL" ]; then
            echo ""
            echo "════════════════════════════════════════════"
            echo "✅ ВАШ ПУБЛИЧНЫЙ URL:"
            echo "   $URL"
            echo ""
            echo "🌍 Откройте эту ссылку в браузере чтобы получить доступ к серверу"
            echo "════════════════════════════════════════════"
            echo ""
        fi
    fi
done

# Остановка сервера при выходе
trap "kill $SERVER_PID 2>/dev/null" EXIT

