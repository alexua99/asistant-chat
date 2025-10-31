import express from "express";
import cors from "cors";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import dotenv from "dotenv";
import OpenAI from "openai";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, ".env") });

const app = express();
app.use(cors());
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
      "Ты — консультант по eSIM. Отвечай только на темы, связанные с eSIM: " +
      "поддерживаемые устройства и операторы, покупка/подключение, установка через QR-код, перенос eSIM, " +
      "активация/деактивация, конфигурация на iOS/Android, устранение неполадок (ошибки активации, нет сети, APN, роуминг). " +
      "Если вопрос вне темы eSIM — вежливо откажись и предложи задать вопрос по eSIM. " +
      "Всегда отвечай на языке, на котором задан вопрос (auto-detect). Если язык неочевиден — отвечай на русском. " +
      "Отвечай кратко и по шагам."
    );

    // Простейшее определение языка последнего сообщения
    function detectLang(text) {
      if (/[А-Яа-яЁё]/.test(text)) return "Russian";
      if (/[A-Za-z]/.test(text)) return "English";
      return "Russian"; // дефолт
    }
    // Определяем язык ответа: если текст пустой/служебный, используем язык из истории
    function getLastLanguageFromHistory(hist) {
      if (!Array.isArray(hist)) return null;
      for (let i = hist.length - 1; i >= 0; i--) {
        const m = hist[i];
        if (!m || !m.content) continue;
        const lang = detectLang(String(m.content));
        if (lang) return lang;
      }
      return null;
    }

    const isServiceMessage = !message || message.toLowerCase() === 'start';
    const lastLang = getLastLanguageFromHistory(history);
    const replyLanguage = isServiceMessage && lastLang ? lastLang : detectLang(message);

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
    const isConversationStart = !history || history.length === 0;
    const onlyDigits = (v) => (typeof v === 'string' ? v.replace(/\D+/g, '') : '');
    const orderCandidate = onlyDigits(order || "");
    const hasOrderCandidate = orderCandidate.length >= 5;

    if (!hasOrderCandidate) {
      // нет номера заказа — подсказываем, где найти
      const hint = replyLanguage === "English"
        ? "Please enter your exact Order Number (digits only, 5+). You can find it in: email receipt, payment confirmation page, or your account order history. Example: 15622."
        : "Пожалуйста, введите точный номер заказа (только цифры, от 5). Его можно найти: в письме‑квитанции на email, на странице подтверждения оплаты, или в истории заказов в аккаунте. Пример: 15622.";
      return res.json({ reply: hint });
    }

    // есть кандидат номера — если не найдено точное совпадение, помогаем вводу
    if (hasOrderCandidate && matched.length === 0) {
      const notFound = replyLanguage === "English"
        ? `Order not found: ${orderCandidate}. Please check digits (no spaces) and try again.`
        : `Заказ не найден: ${orderCandidate}. Проверьте номер (без пробелов) и попробуйте снова.`;
      return res.json({ reply: notFound });
    }

    // Если заказ найден и устройство ещё не известно — краткая сводка + запрос модели
    if (matched.length > 0 && !effMake && !effModel) {
      const o = matched[0];
      const parts = [
        `Order summary:`,
        `- Order: ${o["Order Number "] || o["order_number"]}`,
        `- Email: ${o["email"]}`,
        `- Country: ${o["GEO"]}`,
        `- Plan: ${o["Data"]}`,
        `- Price: ${(o["Price "] ?? o["Price"] ?? o["price"] ?? "")} ${o["Currency"] || ""}`,
        o["Commission "] ? `- Commission: ${o["Commission "]}` : null,
        o["Coupon"] ? `- Coupon: ${o["Coupon"]}` : null,
        o["Referring site"] ? `- Source: ${o["Referring site"]}` : null,
        `- ICCID: ${o["ICCID"]}`,
        ""
      ].filter(Boolean);
      const listInfo = parts.join("\n");
      const askDevice = replyLanguage === "English"
        ? "Please tell me which device you use (manufacturer and model)."
        : "Подскажите, каким устройством вы пользуетесь (производитель и модель)?";
      return res.json({ reply: listInfo, followUp: askDevice });
    }

    const messages = [
      { role: "system", content: systemPrompt },
      geoLine ? { role: "system", content: geoLine + "Учитывай локальные особенности операторов и роуминга этой страны. Не раскрывай источник определения страны." } : null,
      ordersContext ? { role: "system", content: ordersContext } : null,
      deviceContext ? { role: "system", content: deviceContext } : null,
      { role: "system", content: `Always answer in ${replyLanguage}.` },
      ...(Array.isArray(history) ? history : []),
      { role: "user", content: message }
    ].filter(Boolean);


    // На этом этапе: либо заказ не найден (уже обработано), либо найден и устройство известно — подмешиваем факты заказа и устройства в ответы модели

    const completion = await client.chat.completions.create({
      model: "gpt-4o-mini",
      messages
    });

    const text = completion.choices?.[0]?.message?.content ?? "";
    res.json({ reply: text.trim() });
  } catch (err) {
    console.error("/chat error:", err?.message || err);
    res.status(500).json({ error: "internal_error" });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});


