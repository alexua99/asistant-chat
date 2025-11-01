# Assistant Chat

Chat application powered by OpenAI.

## Setup

### 1. Install Dependencies

```bash
npm install
```

### 2. Configure Environment Variables

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Get your OpenAI API key:
   - Go to [OpenAI Platform](https://platform.openai.com/api-keys)
   - Sign up or log in
   - Create a new API key

3. Add your API key to `.env`:
   ```env
   OPENAI_API_KEY=sk-proj-your_actual_api_key_here
   ```

### 3. Run the Application

```bash
npm start
```

Or:

```bash
node index.js
```

## Cloudflare Tunnel Setup (для доступа из интернета)

### 🚀 Самый простой способ (БЕЗ ТОКЕНА, БЕЗ НАСТРОЙКИ)

**⚠️ ВАЖНО:** Если вы получили ошибку "Cannot determine default origin certificate path" или "error parsing tunnel ID", значит вы запустили не ту команду!

**Для работы БЕЗ токена используйте:**

```bash
npm run quick-tunnel
```

**НЕ используйте:** `npm run tunnel` (это требует токен и настройку)

---

**Как запустить (БЕЗ ТОКЕНА):**

1. **Установите Cloudflare Tunnel (один раз):**
   ```bash
   wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O /usr/local/bin/cloudflared
   chmod +x /usr/local/bin/cloudflared
   ```

2. **Запустите:**
   ```bash
   npm run quick-tunnel
   ```
   
   ✅ **Это всё!** Не нужно никаких токенов, авторизации или настройки.
   
   Скрипт автоматически:
   - Запустит сервер на порту 3000
   - Создаст Cloudflare туннель **БЕЗ ТОКЕНА**
   - Покажет вам публичный URL типа `https://xxxxx.trycloudflare.com`
   
   **URL будет работать пока запущен скрипт.**

### Вариант 2: Запуск вручную (в двух терминалах)

Если хотите контролировать процессы отдельно:

1. **В первом терминале запустите сервер:**
   ```bash
   npm start
   ```

2. **Во втором терминале запустите туннель:**
   ```bash
   npm run tunnel-quick
   ```
   
   Это создаст временный URL типа `https://xxxxx.trycloudflare.com`

### Вариант 3: Настройка с постоянным доменом (требует Cloudflare аккаунт)

Если нужен постоянный домен через Cloudflare (не временный URL):

1. **Установите Cloudflare Tunnel** (если еще не установлен)

2. **Запустите скрипт настройки:**
   ```bash
   npm run setup-cloudflare
   ```
   
   ⚠️ **Если возникла ошибка при авторизации**, используйте альтернативный метод:
   ```bash
   npm run setup-cloudflare-alt
   ```

3. **Запуск сервера и туннеля:**
   
   В первом терминале запустите сервер:
   ```bash
   npm start
   ```
   
   Во втором терминале запустите Cloudflare Tunnel:
   ```bash
   npm run tunnel
   ```

### Вариант 4: Настройка через веб-интерфейс Cloudflare

Если `cloudflared tunnel login` не работает:

1. Зайдите в [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Выберите **Zero Trust** → **Networks** → **Tunnels**
3. Нажмите **Create a tunnel**
4. Выберите **Cloudflared**
5. Дайте имя туннелю (например: `assistant-chat`)
6. После создания нажмите **Configure** на туннеле
7. На странице конфигурации добавьте **Public Hostname**:
   - **Subdomain**: `chat` (или любой другой)
   - **Domain**: выберите ваш домен
   - **Service**: `http://localhost:3000`
8. Скопируйте **токен туннеля** и используйте его для запуска, или отредактируйте `cloudflare-config.yaml` с ID туннеля

### Ручная настройка

1. **Войдите в Cloudflare:**
   ```bash
   cloudflared tunnel login
   ```

2. **Создайте туннель:**
   ```bash
   cloudflared tunnel create assistant-chat
   ```

3. **Настройте DNS:**
   ```bash
   cloudflared tunnel route dns assistant-chat chat.yourdomain.com
   ```

4. **Отредактируйте `cloudflare-config.yaml`:**
   - Замените `YOUR_TUNNEL_ID` на ID вашего туннеля
   - Замените `your-domain.com` на ваш домен

5. **Запустите туннель:**
   ```bash
   npm run tunnel
   ```

### Проверка работы

После запуска туннеля ваш сервер будет доступен по адресу, который вы указали при настройке (например, `https://chat.yourdomain.com`).

## Important Notes

- **Never commit `.env` file** - it contains your secret API key
- `.env.example` is a template file that should be committed to the repository
- Each developer needs to create their own `.env` file with their own API key
- Cloudflare Tunnel обеспечивает безопасный доступ без необходимости открывать порты в файрволе
- Сервер уже настроен для работы за Cloudflare (CORS, trust proxy)

