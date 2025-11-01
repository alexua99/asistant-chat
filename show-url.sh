#!/bin/bash

# Скрипт для поиска публичного URL Cloudflare туннеля

echo "🔍 Поиск публичного URL Cloudflare туннеля..."
echo ""

# Проверяем запущен ли cloudflared
if ! pgrep -f "cloudflared tunnel" > /dev/null; then
    echo "❌ Cloudflare туннель не запущен"
    echo ""
    echo "Запустите туннель:"
    echo "  npm run quick-tunnel"
    exit 1
fi

echo "✅ Cloudflare туннель запущен"
echo ""
echo "📍 Публичный URL обычно показывается в терминале где запущен туннель"
echo ""
echo "Формат URL: https://xxxxx-xxxxx-xxxxx.trycloudflare.com"
echo ""
echo "💡 Совет: Посмотрите в терминал где вы запустили 'npm run quick-tunnel'"
echo "   URL будет в строке с 'trycloudflare.com'"
echo ""
echo "Или запустите заново 'npm run quick-tunnel' чтобы увидеть URL"

