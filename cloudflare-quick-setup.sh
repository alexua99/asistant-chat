#!/bin/bash

# Быстрая настройка Cloudflare Tunnel через веб-интерфейс
# Альтернатива для случаев, когда cloudflared tunnel login не работает

echo "🔧 Альтернативная настройка Cloudflare Tunnel"
echo ""
echo "Если cloudflared tunnel login не работает, используйте этот метод:"
echo ""

# Проверка установки cloudflared
if ! command -v cloudflared &> /dev/null; then
    echo "❌ cloudflared не установлен"
    echo "Установите его:"
    echo "  wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared"
    exit 1
fi

echo "✅ cloudflared установлен"
echo ""
echo "=========================================="
echo "ВАРИАНТ 1: Быстрый туннель (для тестирования)"
echo "=========================================="
echo ""
echo "Для быстрого теста без настройки используйте:"
echo "  cloudflared tunnel --url http://localhost:3000"
echo ""
echo "Это создаст временный URL типа: https://xxxxx.trycloudflare.com"
echo ""

echo "=========================================="
echo "ВАРИАНТ 2: Настройка через веб-интерфейс Cloudflare"
echo "=========================================="
echo ""
echo "1. Зайдите в Cloudflare Dashboard: https://dash.cloudflare.com/"
echo "2. Выберите Zero Trust → Networks → Tunnels"
echo "3. Нажмите 'Create a tunnel'"
echo "4. Выберите 'Cloudflared'"
echo "5. Дайте имя туннелю (например: assistant-chat)"
echo "6. После создания нажмите 'Configure' на туннеле"
echo ""
echo "7. На странице конфигурации добавьте Public Hostname:"
echo "   - Subdomain: chat (или любой другой)"
echo "   - Domain: выберите ваш домен"
echo "   - Service: http://localhost:3000"
echo ""
echo "8. Скопируйте команду запуска, которая будет показана на странице"
echo "   или используйте токен из настроек туннеля"
echo ""
echo "=========================================="
echo "ВАРИАНТ 3: Использование API токена"
echo "=========================================="
echo ""

read -p "Хотите настроить через API токен? (y/n): " USE_API

if [ "$USE_API" = "y" ] || [ "$USE_API" = "Y" ]; then
    echo ""
    echo "1. Создайте API токен в Cloudflare Dashboard:"
    echo "   https://dash.cloudflare.com/profile/api-tokens"
    echo ""
    echo "2. Разрешения:"
    echo "   - Account → Cloudflare Tunnel → Edit"
    echo ""
    read -p "Введите API токен: " API_TOKEN
    read -p "Введите Account ID (находится в правом нижнем углу Dashboard): " ACCOUNT_ID
    read -p "Введите имя туннеля: " TUNNEL_NAME
    
    echo ""
    echo "Создание туннеля через API..."
    
    # Создаем туннель через API
    TUNNEL_RESPONSE=$(curl -s -X POST \
        "https://api.cloudflare.com/client/v4/accounts/$ACCOUNT_ID/cfd_tunnel" \
        -H "Authorization: Bearer $API_TOKEN" \
        -H "Content-Type: application/json" \
        --data "{\"name\":\"$TUNNEL_NAME\",\"config\":{\"ingress\":[{\"service\":\"http://localhost:3000\"}]}}")
    
    echo "$TUNNEL_RESPONSE"
    
    TUNNEL_ID=$(echo "$TUNNEL_RESPONSE" | grep -o '"id":"[^"]*' | cut -d'"' -f4)
    
    if [ -z "$TUNNEL_ID" ]; then
        echo "❌ Не удалось создать туннель. Проверьте токен и Account ID."
    else
        echo "✅ Туннель создан с ID: $TUNNEL_ID"
        echo ""
        echo "Получите токен для туннеля на странице настроек в Dashboard"
        echo "и используйте его в cloudflare-config.yaml"
    fi
fi

echo ""
echo "=========================================="
echo "После настройки запустите туннель:"
echo "  npm run tunnel"
echo "=========================================="

