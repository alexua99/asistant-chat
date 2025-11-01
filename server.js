import express from "express";
import cors from "cors";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import dotenv from "dotenv";
import OpenAI from "openai";
import { franc } from "franc";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, ".env") });

const app = express();

// Настройка для работы через Cloudflare прокси
app.set('trust proxy', true);

// CORS: разрешаем доступ откуда угодно
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-Forwarded-For'],
  credentials: false
}));

app.use(express.json());
app.use(express.static(path.join(__dirname, "public")));

const apiKeyRaw = (process.env.OPENAI_API_KEY || process.env.OPENAI_API || process.env.OPENAI_KEY || "").trim();
const apiKey = apiKeyRaw.replace(/^['"]|['"]$/g, "");
if (!apiKey) {
  console.error("OPENAI_API_KEY не найден. Укажите его в .env рядом с server.js");
  console.error(`Ожидаемый путь .env: ${path.join(__dirname, ".env")}`);
  process.exit(1);
}

const client = new OpenAI({ apiKey });

// ===== Orders CSV load/cache =====
function resolveOrdersCsvPath() {
  const p1 = path.join(__dirname, "@order.csv");
  const p2 = path.join(__dirname, "order.csv");
  if (fs.existsSync(p1)) return p1;
  return p2;
}
let ordersCsvPath = resolveOrdersCsvPath();
let cachedOrders = [];
let lastLoadedAt = 0;

function canonicalKey(name) {
  return String(name || "")
    .replace(/\u00A0/g, " ") // non-breaking space → space
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
}

function parseCsv(text) {
  const lines = text.split(/\r?\n/).filter(Boolean);
  if (lines.length === 0) return [];
  const headerRaw = lines[0].split(",");
  const header = headerRaw.map((h) => String(h).replace(/\u00A0/g, " ").trim());
  const headerCanon = header.map(canonicalKey);
  const rows = [];
  for (let i = 1; i < lines.length; i++) {
    const cols = lines[i].split(",").map((c) => String(c).replace(/\u00A0/g, " ").trim());
    const row = {};
    header.forEach((h, idx) => {
      const val = cols[idx] ?? "";
      row[h] = val; // сохранить оригинальным ключом
    });
    // также сохранить каноническими ключами
    headerCanon.forEach((ck, idx) => {
      const val = cols[idx] ?? "";
      if (ck) row[ck] = val;
    });
    rows.push(row);
  }
  return rows;
}

function loadOrdersSync() {
  ordersCsvPath = resolveOrdersCsvPath();
  if (!fs.existsSync(ordersCsvPath)) return [];
  const raw = fs.readFileSync(ordersCsvPath, "utf8");
  return parseCsv(raw);
}

function ensureOrdersFresh(maxAgeMs = 10 * 60 * 1000) {
  const now = Date.now();
  if (now - lastLoadedAt > maxAgeMs || cachedOrders.length === 0) {
    cachedOrders = loadOrdersSync();
    lastLoadedAt = now;
  }
}

function findOrders({ email, orderNumber, iccid }) {
  ensureOrdersFresh();

  const norm = (v) => (typeof v === 'string' ? v.trim() : '');
  const onlyDigits = (v) => v.replace(/\D+/g, '');

  const emailQ = norm(email).toLowerCase();
  const orderQ = onlyDigits(norm(orderNumber));
  const iccidQ = onlyDigits(norm(iccid));

  const useEmail = emailQ.length > 0;
  const useOrder = orderQ.length >= 5;  // жесткая проверка на длину
  const useIccid = iccidQ.length >= 10; // ICCID длинный

  return cachedOrders.filter((o) => {
    const get = (obj, keys) => keys.map((k) => obj[k]).find((v) => typeof v !== "undefined" && v !== null && String(v).length > 0);
    const emailVal = (get(o, ["email", "e_mail", "email_address"]) || '').toLowerCase();
    const orderVal = onlyDigits(get(o, ["Order Number ", "Order Number", "order_number", "ordernumber"]) || '');
    const iccidVal = onlyDigits(get(o, ["ICCID", "iccid"]) || '');

    // Приоритет строгого сравнения:
    // 1) Если указан orderNumber -> совпадение ТОЛЬКО по номеру заказа
    if (useOrder) {
      return orderVal === orderQ;
    }
    // 2) Иначе, если указан ICCID -> совпадение ТОЛЬКО по ICCID
    if (useIccid) {
      return iccidVal === iccidQ;
    }
    // 3) Иначе, если указан email -> совпадение ТОЛЬКО по email
    if (useEmail) {
      return emailVal === emailQ;
    }
    // 4) Ничего не указано — не совпадает
    return false;
  });
}

app.get("/orders", (req, res) => {
  const { email, order, iccid } = req.query || {};
  ensureOrdersFresh();
  const results = findOrders({ email, orderNumber: order, iccid });
  res.json({ count: results.length, results });
});

// Получение IP клиента из заголовков/соединения
function getClientIp(req) {
  const xff = req.headers["x-forwarded-for"];
  if (typeof xff === "string" && xff.length > 0) {
    return xff.split(",")[0].trim();
  }
  const remote = req.socket?.remoteAddress || req.ip || "";
  // Удаляем префикс ::ffff:
  return remote.replace(/^::ffff:/, "");
}

// Определение страны по IP через публичное API
async function detectCountryByIp(ip) {
  try {
    // Если локальный/приватный IP — пропускаем
    if (!ip || ip.startsWith("127.") || ip === "::1" || ip.startsWith("10.") || ip.startsWith("192.168.")) {
      return null;
    }
    const resp = await fetch(`https://ipapi.co/${ip}/json/`, { method: "GET" });
    if (!resp.ok) return null;
    const data = await resp.json();
    if (!data) return null;
    return {
      countryName: data.country_name || null,
      countryCode: data.country || null
    };
  } catch {
    return null;
  }
}

app.post("/chat", async (req, res) => {
  try {
    const { message, history } = req.body || {};
    if (!message || typeof message !== "string") {
      return res.status(400).json({ error: "message is required" });
    }

    const systemPrompt = (
      "Ты — дружелюбный и профессиональный консультант по eSIM. " +
      "Твоя специализация: eSIM технологии, подключение, установка и устранение неполадок.\n\n" +
      "ОСНОВНЫЕ ТЕМЫ, на которые отвечай:\n" +
      "- Поддерживаемые устройства (iPhone, Samsung, Google Pixel и др.)\n" +
      "- Операторы и тарифные планы eSIM\n" +
      "- Покупка и активация eSIM\n" +
      "- Установка eSIM через QR-код\n" +
      "- Перенос eSIM между устройствами\n" +
      "- Активация и деактивация eSIM\n" +
      "- Настройка на iOS и Android\n" +
      "- Устранение неполадок (ошибки активации, нет сети, проблемы с APN, роуминг)\n" +
      "- Совместимость устройств и операторов\n\n" +
      "ЕСЛИ ВОПРОС НЕ ПО ТЕМЕ eSIM:\n" +
      "1. Вежливо признай вопрос и кратко ответь (1-2 предложения)\n" +
      "2. Мягко направь к теме eSIM: 'Я специализируюсь на eSIM технологиях. Чем могу помочь с установкой или настройкой eSIM?'\n" +
      "3. Не будь грубым, но будь настойчивым в возврате к теме\n" +
      "4. Если это приветствие или общий вопрос — отвечай дружелюбно, но сразу предлагай помощь с eSIM\n\n" +
      "ПРИМЕРЫ реакций на вопросы не по теме:\n" +
      "- 'Спасибо за вопрос! Я консультант по eSIM. Могу помочь с установкой eSIM на ваше устройство. Какая у вас модель телефона?'\n" +
      "- 'Интересный вопрос! Моя специализация — eSIM. Может быть, у вас есть вопросы по настройке eSIM или вы хотите подключить eSIM?'\n\n" +
      "СТИЛЬ ОБЩЕНИЯ:\n" +
      "- КРИТИЧЕСКИ ВАЖНО: ВСЕГДА отвечай на ОДНОМ И ТОМ ЖЕ ЯЗЫКЕ на протяжении ВСЕГО диалога\n" +
      "- Язык диалога определяется из первых сообщений пользователя и НЕ МЕНЯЕТСЯ\n" +
      "- Поддерживай ВСЕ языки мира (400+ языков)\n" +
      "- НИКОГДА не переключай язык в середине диалога\n" +
      "- БУДЬ ЕСТЕСТВЕННЫМ: Общайся как настоящий человек, а не робот\n" +
      "- Избегай шаблонных фраз типа 'Пожалуйста, введите', 'Рекомендуется', 'Необходимо'\n" +
      "- Говори просто и понятно, как в обычном разговоре\n" +
      "- Помни контекст предыдущих сообщений - не повторяй уже сказанное\n" +
      "- Если пользователь уже что-то спрашивал или делал - учитывай это в ответе\n" +
      "- КРАТКОСТЬ: отвечай кратко, но информативно (2-3 предложения максимум)\n" +
      "- Дружелюбный тон, но без излишней формальности\n" +
      "- Используй эмодзи умеренно и только когда уместно\n" +
      "- Будь профессиональным, но теплым и человечным\n" +
      "- ВАЖНО: Никогда не смешивай языки в одном ответе"
    );

    // Определение языка сообщения (через библиотеку franc + доп. проверки)
    function detectLang(text) {
      if (!text || typeof text !== 'string') return null;
      
      const t = text.trim();
      if (t.length === 0) return null;
      
      // Используем franc для определения языка (поддерживает 400+ языков)
      try {
        const langCode = franc(t, { minLength: 3 });
        
        // Маппинг кодов ISO 639-3 в понятные названия для промпта
        const langMap = {
          'rus': 'Russian',
          'eng': 'English',
          'spa': 'Spanish',
          'fra': 'French',
          'deu': 'German',
          'ita': 'Italian',
          'por': 'Portuguese',
          'tur': 'Turkish',
          'pol': 'Polish',
          'ces': 'Czech',
          'ell': 'Greek',
          'cmn': 'Chinese',
          'jpn': 'Japanese',
          'kor': 'Korean',
          'ara': 'Arabic',
          'hin': 'Hindi',
          'vie': 'Vietnamese',
          'tha': 'Thai',
          'ind': 'Indonesian',
          'nld': 'Dutch',
          'swe': 'Swedish',
          'nor': 'Norwegian',
          'dan': 'Danish',
          'fin': 'Finnish',
          'ron': 'Romanian',
          'ukr': 'Ukrainian',
          'bul': 'Bulgarian',
          'hrv': 'Croatian',
          'srp': 'Serbian',
          'slk': 'Slovak',
          'hun': 'Hungarian',
          'heb': 'Hebrew',
          'fas': 'Persian',
          'urd': 'Urdu',
          'ben': 'Bengali',
          'tam': 'Tamil',
          'tel': 'Telugu',
          'mar': 'Marathi',
          'zho': 'Chinese'
        };
        
        // Если определили язык с высокой уверенностью
        if (langCode && langCode !== 'und') {
          // Используем маппинг если есть, иначе возвращаем код языка
          if (langMap[langCode]) {
            return langMap[langCode];
          }
          // Если языка нет в маппинге, возвращаем код - модель OpenAI понимает ISO коды
          return langCode;
        }
        
        // Fallback для коротких текстов или неопределенных
        // Проверяем специфичные паттерны для важных языков
        if (/[А-Яа-яЁё]/.test(t)) return "Russian";
        if (/[\u4e00-\u9fff]/.test(t)) return "Chinese";
        if (/[\u3040-\u309f\u30a0-\u30ff]/.test(t)) return "Japanese";
        if (/[\uac00-\ud7a3]/.test(t)) return "Korean";
        if (/[\u0600-\u06ff]/.test(t)) return "Arabic";
        
        // Если только латиница без спецсимволов — английский
        if (/^[A-Za-z0-9\s\.,!?;:'"\-\(\)]+$/.test(t)) return "English";
        
      } catch (err) {
        console.error("Language detection error:", err);
      }
      
      // Дефолт
      return null;
    }
    // Определяем язык ответа: стабильно используем язык из первых сообщений диалога
    function getConversationLanguage(hist, currentMessage) {
      if (!Array.isArray(hist)) hist = [];
      
      // Приоритет 1: Определяем язык из первых 3 сообщений пользователя в диалоге
      // Это обеспечивает стабильность - язык устанавливается в начале и не меняется
      const userMessages = [];
      for (const m of hist) {
        if (m?.role === 'user' && m?.content) {
          userMessages.push(String(m.content));
        }
      }
      // Добавляем текущее сообщение
      if (currentMessage) userMessages.push(currentMessage);
      
      // Анализируем первые сообщения пользователя для определения языка
      let conversationLang = null;
      const langVotes = {};
      
      // Смотрим первые 3 сообщения пользователя
      for (let i = 0; i < Math.min(3, userMessages.length); i++) {
        const lang = detectLang(userMessages[i]);
        if (lang) {
          langVotes[lang] = (langVotes[lang] || 0) + 1;
        }
      }
      
      // Выбираем язык, который встречался чаще всего в первых сообщениях
      let maxVotes = 0;
      for (const [lang, votes] of Object.entries(langVotes)) {
        if (votes > maxVotes) {
          maxVotes = votes;
          conversationLang = lang;
        }
      }
      
      // Если язык определился - возвращаем его (это стабильный язык диалога)
      if (conversationLang) return conversationLang;
      
      // Приоритет 2: Если история пуста, используем язык текущего сообщения
      if (hist.length === 0) {
        return detectLang(currentMessage) || "Russian";
      }
      
      // Приоритет 3: Дефолт
      return "Russian";
    }

    const isServiceMessage = !message || message.toLowerCase() === 'start';
    const replyLanguage = getConversationLanguage(history, message);
    
    // Если это начало диалога, добавляем инструкцию поприветствовать пользователя на его языке
    const isConversationStart = !history || history.length === 0;

    // Небольшой извлекатель устройства из свободного текста (ru/en)
    function extractDeviceFromText(text) {
      if (!text) return {};
      const t = String(text).toLowerCase();
      // Apple iPhone
      const appleWords = /(iphone|айфон|айфон|iphon|iph0ne)/i;
      const iphoneModel = t.match(/(?:iphone|айфон)\s*(\d{1,2}\s*(?:pro\s*max|pro|max)?)/i);
      if (appleWords.test(t)) {
        const model = iphoneModel ? iphoneModel[1].replace(/\s+/g, ' ').trim() : undefined;
        return { make: 'Apple', model: model ? `iPhone ${model.toUpperCase()}` : undefined };
      }
      // Samsung Galaxy
      const samsungWords = /(samsung|самсунг)/i;
      const galaxyModel = t.match(/galaxy\s*([a-z]?\d{1,3}\s*(?:ultra|plus|fe)?)/i);
      if (samsungWords.test(t) || /galaxy/i.test(t)) {
        const model = galaxyModel ? galaxyModel[1].toUpperCase().replace(/\s+/g, ' ') : undefined;
        return { make: 'Samsung', model: model ? `Galaxy ${model}` : undefined };
      }
      // Google Pixel
      const pixelModel = t.match(/pixel\s*(\d{1,2}\s*(?:pro|xl)?)/i);
      if (/google\s*pixel|pixel/i.test(t)) {
        const model = pixelModel ? pixelModel[1].toUpperCase().replace(/\s+/g, ' ') : undefined;
        return { make: 'Google', model: model ? `Pixel ${model}` : undefined };
      }
      // Xiaomi
      if (/xiaomi|mi\s|redmi|шiaomi|сяоми/i.test(t)) {
        return { make: 'Xiaomi' };
      }
      // Huawei
      if (/huawei|honor|хуавей|хонор/i.test(t)) {
        return { make: 'Huawei' };
      }
      return {};
    }

    // Определяем страну пользователя
    const ip = getClientIp(req);
    const geo = await detectCountryByIp(ip);
    const geoLine = geo && (geo.countryName || geo.countryCode)
      ? `User country: ${geo.countryName || ""} ${geo.countryCode ? `(${geo.countryCode})` : ""}. `
      : "";

    // Ищем заказы по дополнительным полям
    let { email, order, iccid, deviceMake, deviceModel } = req.body || {};

    // Если пользователь ввёл цифры в основном сообщении, распознаём как order/iccid
    if (!email && !order && !iccid) {
      const digitRuns = String(message).match(/\d{4,}/g) || [];
      // выбираем самое длинное числовое вхождение
      const longest = digitRuns.sort((a,b) => b.length - a.length)[0];
      if (longest) {
        if (longest.length >= 10) {
          iccid = longest; // длинная последовательность — ICCID
        } else if (longest.length >= 5) {
          order = longest; // короткая — номер заказа
        }
      }
    }
    const matched = findOrders({ email, orderNumber: order, iccid });
    const orderFacts = matched.slice(0, 3).map((o) => (
      `Order ${o["Order Number "]} for ${o["email"]}: GEO=${o["GEO"]}, Data=${o["Data"]}, Price=${o["Price "]} ${o["Currency"]}, ICCID=${o["ICCID"]}`
    ));
    const ordersContext = orderFacts.length > 0
      ? `User orders (top ${orderFacts.length}): ${orderFacts.join(" | ")}. Use these facts to personalize activation guidance. If ICCID mismatches device/operator, warn the user.`
      : "";

    // Контекст устройства, если указан
    // Пытаемся извлечь устройство из текущего сообщения, если не передано полями
    const extracted = (!deviceMake && !deviceModel) ? extractDeviceFromText(message) : {};
    const effMake = deviceMake || extracted.make;
    const effModel = deviceModel || extracted.model;

    const deviceContext = (effMake || effModel)
      ? `User device: ${effMake ? `make=${effMake}` : ""} ${effModel ? `model=${effModel}` : ""}. Provide instructions specific to this device and common pitfalls for this vendor.`
      : "";

    // Режим: пока нет ТОЧНОГО совпадения по Order Number — не ведём другой диалог, помогаем найти номер
    // isConversationStart уже определен выше
    const onlyDigits = (v) => (typeof v === 'string' ? v.replace(/\D+/g, '') : '');
    const orderCandidate = onlyDigits(order || "");
    const hasOrderCandidate = orderCandidate.length >= 5;
    
    // Проверяем, просит ли пользователь помощи
    const helpKeywords = {
      ru: ['помоги', 'помощь', 'как найти', 'не могу найти', 'не знаю', 'где найти', 'где взять', 'что делать'],
      en: ['help', 'how to find', "can't find", "don't know", 'where to find', 'where is', 'what to do', 'assist'],
      es: ['ayuda', 'cómo encontrar', 'no puedo encontrar', 'no sé', 'dónde encontrar'],
      fr: ['aide', 'comment trouver', "je ne trouve pas", "je ne sais pas", 'où trouver'],
      de: ['hilfe', 'wie finden', 'kann nicht finden', 'weiß nicht', 'wo finden']
    };
    
    const messageLower = message.toLowerCase();
    const asksForHelp = Object.values(helpKeywords).some(keywords => 
      keywords.some(keyword => messageLower.includes(keyword))
    );
    
    // Подсчитываем неудачные попытки ввода номера
    let failedAttempts = 0;
    if (Array.isArray(history)) {
      // Считаем сообщения пользователя где был введен номер, но заказ не найден
      for (let i = history.length - 1; i >= 0; i--) {
        const msg = history[i];
        if (msg?.role === 'user') {
          const msgDigits = onlyDigits(msg.content || '');
          if (msgDigits.length >= 5) {
            // Проверяем был ли ответ о том что заказ не найден
            if (i + 1 < history.length) {
              const nextMsg = history[i + 1];
              if (nextMsg?.role === 'assistant' && 
                  (nextMsg.content?.toLowerCase().includes('not found') || 
                   nextMsg.content?.toLowerCase().includes('не найден') ||
                   nextMsg.content?.toLowerCase().includes('не найдено'))) {
                failedAttempts++;
              }
            }
          }
        }
      }
    }
    
    // Помогаем только если попросили помощи ИЛИ было 2+ неудачных попытки
    const shouldProvideHelp = asksForHelp || failedAttempts >= 2;

    if (!hasOrderCandidate) {
      // нет номера заказа — используем AI для естественного ответа с учетом контекста
      const hasEmail = email && email.trim().length > 0;
      const hasIccid = iccid && iccid.trim().length >= 10;
      
      // Собираем контекст из истории
      const conversationContext = Array.isArray(history) && history.length > 0
        ? `Previous conversation: ${history.slice(-4).map(m => `${m.role}: ${m.content}`).join(' | ')}`
        : 'This is the start of conversation.';
      
      let contextPrompt = `You are a helpful eSIM consultant assistant. User needs to provide Order Number but hasn't yet.\n\n`;
      contextPrompt += `${conversationContext}\n\n`;
      contextPrompt += `Current user message: "${message}"\n\n`;
      
      if (hasEmail) {
        const emailMatches = findOrders({ email, orderNumber: null, iccid: null });
        if (emailMatches.length > 0) {
          const orderNumbers = emailMatches.slice(0, 3).map(o => o["Order Number "] || o["order_number"]).join(", ");
          contextPrompt += `I found orders for email ${email}: ${orderNumbers}. Guide user naturally.`;
        } else {
          contextPrompt += `User provided email ${email} but no orders found. Help them check email or find order another way.`;
        }
      } else if (hasIccid) {
        const iccidMatches = findOrders({ email: null, orderNumber: null, iccid });
        if (iccidMatches.length > 0) {
          const orderNumbers = iccidMatches.map(o => o["Order Number "] || o["order_number"]).join(", ");
          contextPrompt += `Found orders for ICCID: ${orderNumbers}. Help user use this.`;
        }
      } else {
        // Если пользователь уже спрашивал о помощи или открыл почту
        if (asksForHelp || messageLower.includes('почт') || messageLower.includes('email') || messageLower.includes('открыл')) {
          contextPrompt += `User is actively looking for order number. They mentioned opening email/mail. Give specific, step-by-step guidance on WHERE exactly in the email to look (subject line, body, attachments). Be conversational, not robotic.`;
        } else {
          contextPrompt += `Briefly and naturally ask for Order Number or help them find it. Be friendly, not formal.`;
        }
      }
      
      contextPrompt += `\n\nRespond in ${replyLanguage} naturally, as a real assistant would. Don't use robotic phrases like "Please enter" or templates. Be conversational and helpful.`;
      
      const hintCompletion = await client.chat.completions.create({
        model: "gpt-4o-mini",
        messages: [
          { 
            role: "system", 
            content: `You are a friendly eSIM consultant assistant. Always respond in ${replyLanguage}. Be natural, conversational, and helpful. Avoid robotic phrases and templates. Speak like a real person helping a customer.` 
          },
          ...(Array.isArray(history) ? history.slice(-3) : []), // Последние 3 сообщения для контекста
          { role: "user", content: contextPrompt }
        ],
        max_tokens: 150
      });
      
      const hint = hintCompletion.choices?.[0]?.message?.content?.trim();
      
      return res.json({ reply: hint });
    }

    // есть кандидат номера — если не найдено точное совпадение
    if (hasOrderCandidate && matched.length === 0) {
      // Используем AI для естественного ответа с учетом контекста
      ensureOrdersFresh();
      const similarOrders = cachedOrders.filter((o) => {
        const orderVal = String(o["Order Number "] || o["order_number"] || "").replace(/\D+/g, '');
        return (orderVal.length >= orderCandidate.length && orderVal.endsWith(orderCandidate)) || 
               (orderCandidate.length >= 4 && orderVal.includes(orderCandidate));
      }).slice(0, 2);
      
      const hasEmail = email && email.trim().length > 0;
      const hasIccid = iccid && iccid.trim().length >= 10;
      
      const conversationContext = Array.isArray(history) && history.length > 0
        ? `Previous conversation: ${history.slice(-4).map(m => `${m.role}: ${m.content}`).join(' | ')}`
        : '';
      
      let helpContext = `User entered order number "${orderCandidate}" but it wasn't found in the system.\n\n`;
      helpContext += `${conversationContext}\n\n`;
      helpContext += `Current message: "${message}"\n\n`;
      
      if (similarOrders.length > 0) {
        const similarNums = similarOrders.map(o => o["Order Number "] || o["order_number"]).join(", ");
        helpContext += `Found similar order numbers: ${similarNums}. Suggest checking if one matches.\n`;
      }
      
      if (hasEmail) {
        const emailMatches = findOrders({ email, orderNumber: null, iccid: null });
        if (emailMatches.length > 0) {
          const orderNumbers = emailMatches.slice(0, 3).map(o => o["Order Number "] || o["order_number"]).join(", ");
          helpContext += `Found orders for their email: ${orderNumbers}. Help them use correct number.\n`;
        }
      }
      
      helpContext += `Respond naturally in ${replyLanguage}, like a real person helping. Don't use templates or robotic phrases. Be helpful and friendly.`;
      
      const notFoundCompletion = await client.chat.completions.create({
        model: "gpt-4o-mini",
        messages: [
          { 
            role: "system", 
            content: `You are a friendly eSIM consultant assistant. Always respond in ${replyLanguage}. Be natural and conversational. Avoid templates and robotic phrases. Speak like a real person.` 
          },
          ...(Array.isArray(history) ? history.slice(-3) : []),
          { role: "user", content: helpContext }
        ],
        max_tokens: 120
      });
      
      const notFound = notFoundCompletion.choices?.[0]?.message?.content?.trim();
      
      return res.json({ reply: notFound });
    }

    // Если заказ найден и устройство ещё не известно — краткая сводка + запрос модели
    if (matched.length > 0 && !effMake && !effModel) {
      const o = matched[0];
      
      // Генерируем краткую сводку заказа на нужном языке через AI
      const summaryPrompt = `Brief order summary in ${replyLanguage}. Key info only: Order ${o["Order Number "] || o["order_number"]}, Email ${o["email"]}, Country ${o["GEO"]}, Plan ${o["Data"]}, Price ${(o["Price "] ?? o["Price"] ?? o["price"] ?? "")} ${o["Currency"] || ""}, ICCID ${o["ICCID"]}. Max 3-4 short lines.`;
      
      const summaryCompletion = await client.chat.completions.create({
        model: "gpt-4o-mini",
        messages: [
          { role: "system", content: `You are an eSIM consultant. Always respond in ${replyLanguage}. Be very brief. Max 4 short lines.` },
          { role: "user", content: summaryPrompt }
        ],
        max_tokens: 80
      });
      
      const listInfo = summaryCompletion.choices?.[0]?.message?.content?.trim() || 
        `Order summary:\n- Order: ${o["Order Number "] || o["order_number"]}\n- Email: ${o["email"]}\n- Country: ${o["GEO"]}\n- Plan: ${o["Data"]}\n- Price: ${(o["Price "] ?? o["Price"] ?? o["price"] ?? "")} ${o["Currency"] || ""}\n- ICCID: ${o["ICCID"]}`;
      
      // Запрос устройства на нужном языке (кратко)
      const askDevicePrompt = `Ask user in ${replyLanguage} which device they use (manufacturer and model). One short sentence.`;
      
      const askDeviceCompletion = await client.chat.completions.create({
        model: "gpt-4o-mini",
        messages: [
          { role: "system", content: `You are an eSIM consultant. Always respond in ${replyLanguage}. Be very brief - one sentence only.` },
          { role: "user", content: askDevicePrompt }
        ],
        max_tokens: 30
      });
      
      const askDevice = askDeviceCompletion.choices?.[0]?.message?.content?.trim() || 
        (replyLanguage === "English"
          ? "Please tell me which device you use (manufacturer and model)."
          : "Подскажите, каким устройством вы пользуетесь (производитель и модель)?");
      
      return res.json({ reply: listInfo, followUp: askDevice });
    }

    const messages = [
      { role: "system", content: systemPrompt },
      geoLine ? { role: "system", content: geoLine + "Учитывай локальные особенности операторов и роуминга этой страны. Не раскрывай источник определения страны." } : null,
      ordersContext ? { role: "system", content: ordersContext } : null,
      deviceContext ? { role: "system", content: deviceContext } : null,
      { role: "system", content: `CRITICAL LANGUAGE RULE: You MUST respond ONLY in ${replyLanguage} language for this ENTIRE conversation. This is the established conversation language and MUST NOT change. Use ${replyLanguage} consistently regardless of what language appears in individual messages. If ${replyLanguage} is a language code (like 'fra', 'spa'), use that language. Never switch languages mid-conversation.` },
      ...(Array.isArray(history) ? history : []),
      { role: "user", content: message }
    ].filter(Boolean);


    // На этом этапе: либо заказ не найден (уже обработано), либо найден и устройство известно — подмешиваем факты заказа и устройства в ответы модели

    const completion = await client.chat.completions.create({
      model: "gpt-4o-mini",
      messages,
      max_tokens: 200  // Ограничиваем длину ответа для краткости
    });

    const text = completion.choices?.[0]?.message?.content ?? "";
    res.json({ reply: text.trim() });
  } catch (err) {
    console.error("/chat error:", err?.message || err);
    res.status(500).json({ error: "internal_error" });
  }
});

const PORT = process.env.PORT || 3000;
const HOST = process.env.HOST || '0.0.0.0';
app.listen(PORT, HOST, () => {
  console.log(`Server running on http://${HOST}:${PORT}`);
});



