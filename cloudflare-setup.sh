#!/bin/bash

# Скрипт для настройки Cloudflare Tunnel
# Требуется: cloudflared должен быть установлен

echo "🚀 Настройка Cloudflare Tunnel"
echo ""

# Проверка установки cloudflared
if ! command -v cloudflared &> /dev/null; then
    echo "❌ cloudflared не установлен"
    echo "Установите его:"
    echo "  Linux: wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared"
    echo "  или: sudo apt-get install cloudflared"
    exit 1
fi

echo "✅ cloudflared установлен"
echo ""

# Вход в Cloudflare (если еще не авторизован)
echo "Авторизация в Cloudflare..."
echo ""
echo "⚠️  Если возникнет ошибка авторизации, используйте альтернативный метод:"
echo "   npm run setup-cloudflare-alt"
echo ""
echo "Или настройте туннель через веб-интерфейс Cloudflare Dashboard"
echo ""
read -p "Нажмите Enter для продолжения или Ctrl+C для отмены..."
cloudflared tunnel login

echo ""
echo "Создание туннеля..."
read -p "Введите имя туннеля (например: assistant-chat): " TUNNEL_NAME

cloudflared tunnel create "$TUNNEL_NAME"

# Получаем ID туннеля
TUNNEL_ID=$(cloudflared tunnel list | grep "$TUNNEL_NAME" | awk '{print $1}' | tail -1)

if [ -z "$TUNNEL_ID" ]; then
    echo "❌ Не удалось получить ID туннеля"
    exit 1
fi

echo ""
echo "✅ Туннель создан с ID: $TUNNEL_ID"
echo ""

# Запрашиваем домен
read -p "Введите ваш домен (например: example.com): " DOMAIN
read -p "Введите поддомен для этого сервиса (например: chat) или нажмите Enter для основного домена: " SUBDOMAIN

if [ -z "$SUBDOMAIN" ]; then
    HOSTNAME="$DOMAIN"
else
    HOSTNAME="$SUBDOMAIN.$DOMAIN"
fi

echo ""
echo "Настройка DNS записи для $HOSTNAME..."

# Создаем маршрут (route)
cloudflared tunnel route dns "$TUNNEL_NAME" "$HOSTNAME"

# Обновляем конфигурационный файл
sed -i "s/YOUR_TUNNEL_ID/$TUNNEL_ID/g" cloudflare-config.yaml
sed -i "s|your-domain.com|$HOSTNAME|g" cloudflare-config.yaml
sed -i "s|www.your-domain.com|www.$HOSTNAME|g" cloudflare-config.yaml

echo ""
echo "✅ Настройка завершена!"
echo ""
echo "Для запуска туннеля используйте:"
echo "  npm run tunnel"
echo ""
echo "Или вручную:"
echo "  cloudflared tunnel --config cloudflare-config.yaml run"

