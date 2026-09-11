/*
 * Генератор PowerPoint-отчёта из данных дашборда.
 * Дизайн повторяет фирменный шаблон агентства (розовый фон, винные акценты, Verdana).
 * Запуск:  node pptx/generate.cjs <input.json> <output.pptx>
 * Вызывается автоматически из ReportPptxController.
 */
const fs = require("fs");
const path = require("path");
const pptxgen = require("pptxgenjs");

const [, , inPath, outPath] = process.argv;
const D = JSON.parse(fs.readFileSync(inPath, "utf8"));

/* ---- палитра шаблона ---- */
const BG = "E6C4C4";       // пыльно-розовый фон
const WINE = "701023";     // основной винный
const WINE2 = "781124";    // винный чипов/заголовков
const CHIP2 = "933549";    // альтернативный чип
const PINK = "D19393";     // розовый бейдж
const WHITE = "FFFFFF";
const DARK = "000000";
const FONT = "Verdana";

const ASSETS = path.join(__dirname, "assets");
const A = (f) => path.join(ASSETS, f);
const hasAsset = (f) => fs.existsSync(A(f));

const p = new pptxgen();
p.layout = "LAYOUT_WIDE"; // 13.33 x 7.5
const S = p.ShapeType;

const fmt = (n) => Math.round(Number(n) || 0).toLocaleString("ru-RU");
const plural = (n, forms) => {
  const a = Math.abs(n) % 100, b = a % 10;
  if (a > 10 && a < 20) return forms[2];
  if (b > 1 && b < 5) return forms[1];
  if (b === 1) return forms[0];
  return forms[2];
};
const KEYS = Object.keys(D.current || {}).filter((k) => D.current[k]);
const PLAT_SHORT = { vk: "ВК", ig: "инстаграм", max: "Макс", yt: "YouTube", tg: "Телеграм" };
const monthOf = (label) => (label || "").split(" ")[0]; // «Июнь 2026» → «Июнь»

function slide() {
  const s = p.addSlide();
  s.background = { color: BG };
  // пустой <a:ln> у фигур PowerPoint дорисовывает контуром из темы —
  // всем текстовым блокам явно задаём полностью прозрачную линию
  const origText = s.addText.bind(s);
  s.addText = (txt, opts = {}) => origText(txt, { line: { color: BG, transparency: 100, width: 0.25 }, ...opts });
  return s;
}

function logo(s, x, y) {
  if (hasAsset("logo-black.png")) {
    s.addImage({ path: A("logo-black.png"), x, y, w: 1.55, h: 0.57 });
  }
}

// n — номер сферы на слайде (1..3): каждая вставка ссылается на свой файл,
// повторное использование одного изображения на слайде PowerPoint не открывает
function sphere(s, x, y, w, n = 1) {
  const f = n === 1 ? "sphere.png" : `sphere-${n}.png`;
  if (hasAsset(f)) {
    s.addImage({ path: A(f), x, y, w, h: w * 0.987 });
  }
}

// заголовок из двух цветов: часть слов винным
function title(s, runs, opts) {
  s.addText(
    runs.map(([t, wine]) => ({ text: t, options: { color: wine ? WINE : DARK } })),
    { x: 0.99, y: 1.53, w: 11.9, h: 1.15, fontFace: FONT, fontSize: 60, margin: 0, ...opts }
  );
}

function chip(s, x, y, w, text, fill, opts = {}) {
  s.addShape(S.roundRect, { x, y, w, h: 0.49, rectRadius: 0.24, fill: { color: fill }, line: { color: fill, width: 0.25 } });
  s.addText(text, {
    x, y, w, h: 0.49, align: "center", valign: "middle",
    fontFace: FONT, fontSize: 16, color: WHITE, margin: 0, ...opts,
  });
}

// белая карточка с «тире»-буллетами; bullets: массив массивов текстовых ранов
function noteCard(s, x, y, w, h, bullets) {
  s.addShape(S.roundRect, { x, y, w, h, rectRadius: 0.15, fill: { color: WHITE }, line: { color: WHITE, width: 0.25 } });
  let ty = y + 0.33;
  bullets.forEach((runs) => {
    const plain = runs.map((r) => r[0]).join("");
    // каждая явная строка переносится отдельно — считаем построчно
    const lines = plain.split("\n").reduce((a, l) => a + Math.max(1, Math.ceil(l.length / 55)), 0);
    const bh = lines * 0.22 + 0.06;
    // винная стрелка-указатель, как в шаблоне
    s.addShape(S.line, { x: x + 0.25, y: ty + 0.11, w: 0.37, h: 0, line: { color: WINE, width: 1.5, endArrowType: "triangle" } });
    s.addText(
      runs.map(([t, wine]) => ({ text: t, options: { color: wine ? WINE : DARK, bold: !!wine } })),
      { x: x + 0.72, y: ty - 0.02, w: w - 1.0, h: bh + 0.1, fontFace: FONT, fontSize: 12, margin: 0, valign: "top" }
    );
    ty += bh + 0.24;
  });
}

// столбики по месяцам (винная карточка рисуется отдельно, до декоративной сферы)
function barCard(s, x, y, w, h, rows) {
  const vals = rows.map((r) => Number(r.v) || 0);
  const n = vals.length;
  if (!n) return;
  const max = Math.max(...vals), min = Math.min(...vals);
  const slot = 0.745, barW = 0.69;
  const x0 = x + Math.max(0.3, (w - (n * slot - 0.055)) / 2);
  const bottom = y + h - 0.05;
  const labelH = 0.22;
  const minH = 0.5, maxH = h - 1.0; // место под подпись значения сверху
  vals.forEach((v, i) => {
    const t = max === min ? 0.6 : (v - min) / (max - min);
    const bh = minH + t * (maxH - minH);
    const bx = x0 + i * slot;
    s.addShape(S.roundRect, { x: bx, y: bottom - bh, w: barW, h: bh, rectRadius: 0.12, fill: { color: WHITE }, line: { color: WHITE, width: 0.25 } });
    s.addText(fmt(v), {
      x: bx - 0.06, y: bottom - bh - 0.32, w: barW + 0.12, h: 0.27, align: "center",
      fontFace: FONT, fontSize: 10, color: WHITE, margin: 0,
    });
    s.addText(rows[i].k, {
      x: bx - 0.06, y: bottom - labelH - 0.02, w: barW + 0.12, h: labelH, align: "center",
      fontFace: FONT, fontSize: 7, color: DARK, margin: 0,
    });
  });
}

/* ============ 1. ОБЛОЖКА ============ */
{
  const s = slide();
  if (hasAsset("panel-wine.png")) s.addImage({ path: A("panel-wine.png"), x: 8.33, y: 0, w: 5.0, h: 7.5 });
  logo(s, 1.17, 0.84);
  s.addText(
    [{ text: "Отчёт ", options: { color: DARK } }, { text: "SMM", options: { color: WINE } }],
    { x: 1.03, y: 2.8, w: 7.0, h: 1.21, fontFace: FONT, bold: true, fontSize: 66, margin: 0 }
  );
  s.addText("Для " + (D.client || ""), { x: 1.07, y: 4.08, w: 6.5, h: 0.57, fontFace: FONT, fontSize: 28, color: DARK, margin: 0 });
  const period = D.period || "";
  const bw = Math.max(1.72, period.length * 0.14 + 0.5);
  s.addShape(S.roundRect, { x: 1.17, y: 5.88, w: bw, h: 0.72, rectRadius: 0.2, fill: { color: PINK }, line: { color: PINK, width: 0.25 } });
  s.addText(period, { x: 1.17, y: 5.88, w: bw, h: 0.72, align: "center", valign: "middle", fontFace: FONT, fontSize: 16, color: DARK, margin: 0 });
}

/* ============ 2. МЫ СДЕЛАЛИ ============ */
if ((D.tasks || []).length) {
  const s = slide();
  sphere(s, 7.4, 1.68, 3.39, 1);
  sphere(s, 3.64, 4.97, 2.25, 2);
  sphere(s, 10.6, 5.3, 2.4, 3);
  logo(s, 1.16, 0.84);
  s.addText(
    [{ text: "Мы ", options: { color: DARK } }, { text: "сделали", options: { color: WINE } }],
    { x: 0.99, y: 2.24, w: 7.0, h: 1.11, fontFace: FONT, fontSize: 60, margin: 0 }
  );
  let cx = 1.16, cy = 3.75;
  D.tasks.slice(0, 16).forEach((t, i) => {
    const text = t.title || "";
    const w = Math.min(5.5, text.length * 0.115 + 0.6);
    if (cx + w > 12.4) { cx = 1.16; cy += 0.61; }
    chip(s, cx, cy, w, text, i % 2 ? CHIP2 : WINE2);
    cx += w + 0.13;
  });
}

/* ============ 3. ДИНАМИКА ПО ПЛОЩАДКАМ (таблица) ============ */
KEYS.forEach((pk) => {
  const cur = D.current[pk] || {};
  const prev = D.previous && D.previous.stats ? D.previous.stats[pk] : null;
  const s = slide();
  s.addText(
    [
      { text: "Динамика ", options: { color: DARK } },
      { text: "показателей", options: { color: WINE } },
      { text: " " + (PLAT_SHORT[pk] || pk), options: { color: DARK } },
    ],
    { x: 0.99, y: 0.36, w: 11.2, h: 1.92, fontFace: FONT, fontSize: 54, margin: 0 }
  );
  logo(s, 10.62, 0.6);

  const C = [
    { x: 1.16, w: 2.85 },  // метрика
    { x: 4.12, w: 3.01 },  // пред. месяц
    { x: 7.19, w: 3.01 },  // тек. месяц
    { x: 10.3, w: 1.87 },  // дельта
  ];
  const headY = 2.94, headH = 0.45;
  const heads = ["", D.prev_month || "—", monthOf(D.period), ""];
  C.forEach((c, i) => {
    s.addShape(S.roundRect, { x: c.x, y: headY, w: c.w, h: headH, rectRadius: 0.18, fill: { color: WINE }, line: { color: WINE, width: 0.25 } });
    if (heads[i]) {
      s.addText(heads[i], { x: c.x, y: headY, w: c.w, h: headH, align: "center", valign: "middle", fontFace: FONT, bold: true, fontSize: 16, color: WHITE, margin: 0 });
    }
  });

  const metrics = [["subs", "Подписчики"], ["views", "Просмотры"], ["reach", "Охваты"], ["inter", "Взаимодействия"]];
  metrics.forEach(([k, label], r) => {
    const y = 3.48 + r * 0.88, h = 0.78;
    const cell = (ci, text, o = {}) => {
      const cf = o.fill || WHITE;
      s.addShape(S.roundRect, { x: C[ci].x, y, w: C[ci].w, h, rectRadius: 0.18, fill: { color: cf }, line: { color: cf, width: 0.25 } });
      s.addText(text, {
        x: C[ci].x + (o.align === "left" ? 0.25 : 0), y, w: C[ci].w - (o.align === "left" ? 0.25 : 0), h,
        align: o.align || "center", valign: "middle", fontFace: FONT, fontSize: 16,
        bold: !!o.bold, color: o.color || DARK, margin: 0,
      });
    };
    cell(0, label, { align: "left", bold: true });
    cell(1, prev ? fmt(prev[k]) : "—");
    cell(2, fmt(cur[k]));
    if (prev) {
      const d = (Number(cur[k]) || 0) - (Number(prev[k]) || 0);
      if (d > 0) cell(3, "+" + fmt(d), { fill: WINE, color: WHITE });
      else cell(3, fmt(d), {});
    } else {
      cell(3, "—", {});
    }
  });
});

/* ============ 4. ОБНОВЛЕНИЯ ПО ПЛОЩАДКАМ (работа с сообществом) ============ */
// community: {vk: [{caption, image}], ig: [...]}; старый формат (плоский список) считаем ВК
const COMMUNITY = Array.isArray(D.community) ? { vk: D.community } : (D.community || {});
Object.keys(COMMUNITY).forEach((pk) => {
  const items = (COMMUNITY[pk] || []).filter((c) => c.caption || c.image);
  const platName = PLAT_SHORT[pk] || pk;
  for (let i = 0; i < items.length; i += 2) {
    const pair = items.slice(i, i + 2);
    const s = slide();
    s.addText(
      [{ text: "Обновления", options: { color: WINE } }, { text: " " + platName, options: { color: DARK } }],
      { x: 0.5, y: 1.3, w: 11.0, h: 1.11, fontFace: FONT, fontSize: platName.length > 6 ? 54 : 60, margin: 0 }
    );
    logo(s, 0.67, 0.5);
    const slots = [{ x: 0.67, w: 5.66 }, { x: 6.67, w: 5.85 }];
    pair.forEach((c, j) => {
      const sl = pair.length === 1 ? { x: 0.67, w: 8.0 } : slots[j];
      let imgY = 3.2;
      if (c.caption) {
        // высота чипа под длину подписи (14pt Verdana ≈ 0.105"/символ)
        const lines = Math.max(1, Math.ceil((c.caption.length * 0.105) / (sl.w - 0.5)));
        const ch = 0.45 + (lines - 1) * 0.3;
        s.addShape(S.roundRect, { x: sl.x, y: 2.64, w: sl.w, h: ch, rectRadius: 0.18, fill: { color: WINE }, line: { color: WINE, width: 0.25 } });
        s.addText(c.caption, { x: sl.x + 0.2, y: 2.64, w: sl.w - 0.4, h: ch, align: "center", valign: "middle", fontFace: FONT, fontSize: 14, color: WHITE, margin: 0 });
        imgY = 2.64 + ch + 0.11;
      }
      if (c.image && fs.existsSync(c.image)) {
        const ih = 6.95 - imgY;
        s.addImage({ path: c.image, x: sl.x, y: imgY, w: sl.w, h: ih, sizing: { type: "contain", w: sl.w, h: ih } });
      }
    });
  }
});

/* ============ 5. МЕТРИКИ ПО МЕСЯЦАМ + ПОСТЫ (по площадкам) ============ */
const METRICS = [["subs", "Подписчики"], ["views", "Просмотры"], ["inter", "Взаимодействия"]];

KEYS.forEach((pk) => {
  const platName = PLAT_SHORT[pk] || pk;

  METRICS.forEach(([mk, mName]) => {
    const rows = (D.series || [])
      .map((r) => ({ k: r.k, v: r[pk] ? Number(r[pk][mk]) || 0 : 0 }));
    if (rows.length < 2 || rows.every((r) => !r.v)) return;

    const s = slide();
    s.addText(
      [{ text: mName, options: { color: WINE } }, { text: " " + platName, options: { color: DARK } }],
      { x: 0.99, y: 2.06, w: 11.9, h: 1.11, fontFace: FONT, fontSize: mName.length + platName.length > 20 ? 54 : 60, margin: 0 }
    );
    logo(s, 1.16, 0.5);

    // порядок слоёв как в шаблоне: винная карточка → сфера между карточками →
    // белая карточка → столбики → большие сферы поверх краёв слайда
    s.addShape(S.roundRect, { x: 7.18, y: 3.75, w: 5.0, h: 2.74, rectRadius: 0.15, fill: { color: WINE }, line: { color: WINE, width: 0.25 } });
    sphere(s, 5.96, 2.99, 1.96, 1);

    // тексты белой карточки: выводы из редактора отчёта,
    // при их отсутствии — автотексты из цифр
    const bullets = [];
    const notes = (D.metric_notes && D.metric_notes[pk] && D.metric_notes[pk][mk]) || [];
    if (notes.length) {
      notes.slice(0, 3).forEach((t) => bullets.push([[t, false]]));
    } else {
      const cur = rows[rows.length - 1];
      const prevRow = rows.length > 1 ? rows[rows.length - 2] : null;
      const best = rows.reduce((a, b) => (b.v > a.v ? b : a), rows[0]);
      const b1 = [[`${mName} за ${cur.k.toLowerCase()}: `, false], [fmt(cur.v), true]];
      if (prevRow) {
        const d = cur.v - prevRow.v;
        b1.push([` (${d >= 0 ? "+" : ""}${fmt(d)} к месяцу «${prevRow.k}»)`, false]);
      }
      bullets.push(b1);
      if (best.k !== cur.k) {
        bullets.push([["Лучший результат за период: ", false], [`${best.k} — ${fmt(best.v)}`, true]]);
      }
    }
    if (pk === "vk" && mk === "subs" && bullets.length < 3 && D.current.vk && Number(D.current.vk.leads) > 0) {
      bullets.push([[fmt(D.current.vk.leads), true], [` переходов на сайт из ВК за ${monthOf(D.period).toLowerCase()}`, false]]);
    }

    noteCard(s, 1.16, 3.75, 5.87, 2.74, bullets);
    barCard(s, 7.18, 3.75, 5.0, 2.74, rows);
    sphere(s, 9.53, -1.48, 4.95, 2);
    sphere(s, 2.55, 5.52, 4.32, 3);
  });

  // посты и сторис площадки — отдельными слайдами, как в шаблоне
  const items = (D.content || []).filter((c) => c.platform === pk);
  contentSlide(platName, items.filter((c) => c.kind !== "story"), false);
  contentSlide(platName, items.filter((c) => c.kind === "story"), true);
});

// слайд «Посты …» / «Сторис …»: белая карточка со списком + скриншоты в рамке телефона
function contentSlide(platName, items, isStory) {
  if (!items.length) return;
  const s = slide();
  s.addText(
    [{ text: isStory ? "Сторис" : "Посты", options: { color: WINE } }, { text: " " + platName, options: { color: DARK } }],
    { x: 0.99, y: 1.53, w: 11.0, h: 1.11, fontFace: FONT, fontSize: 60, margin: 0 }
  );
  logo(s, 1.16, 0.5);
  sphere(s, 5.75, 3.86, 1.96, 1); // под белой карточкой, выглядывает справа

  const bullets = [];
  const list = [[isStory ? "Самые популярные сторис:" : "Самые популярные посты:", false]];
  items.slice(0, 4).forEach((c) => {
    const v = Number(c.views) || 0;
    list.push([`\n– ${c.title}${v ? ` — ${fmt(v)} ${plural(v, ["просмотр", "просмотра", "просмотров"])}` : ""}`, false]);
  });
  bullets.push(list);
  const insight = items.map((c) => c.insight).find((t) => t && t.trim());
  if (insight) bullets.push([[insight, false]]);
  noteCard(s, 1.16, 3.06, 5.87, 2.9, bullets);
  sphere(s, 8.44, -2.41, 4.95, 2);
  sphere(s, 1.49, 5.15, 4.32, 3);

  // скриншоты в рамке телефона: фото под рамкой, в области «экрана»;
  // геометрия из шаблона: рамка 2.78×5.38, экран со смещением 0.23/0.18
  const photos = items.map((c) => c.image).filter((f) => f && fs.existsSync(f)).slice(0, 2);
  const slots = photos.length === 1 ? [{ x: 8.4 }] : [{ x: 6.97 }, { x: 9.88 }];
  photos.forEach((f, i) => {
    const fx = slots[i].x;
    s.addImage({ path: f, x: fx + 0.23, y: 3.14, w: 2.32, h: 5.07, sizing: { type: "cover", w: 2.32, h: 5.07 } });
    const frame = i === 0 ? "phone-frame.png" : "phone-frame-2.png";
    if (hasAsset(frame)) {
      s.addImage({ path: A(frame), x: fx, y: 2.96, w: 2.78, h: 5.38 });
    }
  });
}

/* ============ 6. ВЫВОД ============ */
// слайд в стиле обложки: винная панель справа, розовый бейдж, текст слева
function finalSlide(badge, badgeW, render) {
  const s = slide();
  if (hasAsset("panel-wine.png")) s.addImage({ path: A("panel-wine.png"), x: 8.33, y: 0, w: 5.0, h: 7.5 });
  logo(s, 0.92, 0.84);
  s.addShape(S.roundRect, { x: 0.92, y: 2.26, w: badgeW, h: 0.75, rectRadius: 0.2, fill: { color: PINK }, line: { color: PINK, width: 0.25 } });
  s.addText(badge, { x: 0.92, y: 2.26, w: badgeW, h: 0.75, align: "center", valign: "middle", fontFace: FONT, fontSize: 28, color: DARK, margin: 0 });
  render(s);
}

if (D.summary) {
  finalSlide("Вывод", 1.96, (s) => {
    s.addText(D.summary, { x: 0.92, y: 3.34, w: 7.01, h: 3.4, fontFace: FONT, fontSize: 16, color: DARK, lineSpacingMultiple: 1.15, margin: 0, valign: "top" });
  });
}

/* ============ 6a. ПЛАН НА СЛЕДУЮЩИЙ МЕСЯЦ ============ */
{
  const plan = (D.plan_next || "").split("\n").filter(Boolean);
  if (plan.length) {
    finalSlide("План на следующий месяц", 4.6, (s) => {
      s.addText(plan.map((t) => "– " + t).join("\n"), { x: 0.92, y: 3.34, w: 7.01, h: 3.4, fontFace: FONT, fontSize: 16, color: DARK, lineSpacingMultiple: 1.35, margin: 0, valign: "top" });
    });
  }
}

/* ============ 7. КОНТАКТЫ ============ */
{
  const s = slide();
  if (hasAsset("panel-waves.png")) s.addImage({ path: A("panel-waves.png"), x: 7.6, y: 0, w: 5.74, h: 7.5 });
  logo(s, 0.79, 0.79);
  s.addText("Контакты", { x: 0.63, y: 1.83, w: 5.0, h: 1.01, fontFace: FONT, fontSize: 54, color: WINE2, margin: 0 });

  const rows = [
    ["icon-address.png", "г. Тула, ул. Генерала Маргелова, 5А, 2 эт., оф. 30", 16, false],
    ["icon-mail.png", "info@istinavm.ru", 16, false],
    ["icon-site.png", "истина-маркетинга.рф", 16, false],
    ["icon-phone.png", "+7 967 469-69-96", 20, true],
    ["icon-clock.png", "ПН-ПТ с 9:00 до 18:00", 16, false],
  ];
  let y = 3.32;
  rows.forEach(([icon, text, size, phone]) => {
    const h = phone ? 0.66 : 0.55;
    if (hasAsset(icon)) s.addImage({ path: A(icon), x: 0.79, y: y + (h - 0.33) / 2, w: 0.33, h: 0.33 });
    s.addText(text, {
      x: 1.27, y, w: 6.2, h, fontFace: FONT, fontSize: size, valign: "middle",
      bold: phone, color: phone ? WINE2 : DARK, margin: 0,
    });
    y += h + 0.05;
  });
}

p.writeFile({ fileName: outPath }).then(() => console.log("OK " + outPath));


