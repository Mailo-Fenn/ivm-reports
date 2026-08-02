/*
 * Генератор PowerPoint-отчёта из данных дашборда.
 * Запуск:  node pptx/generate.js <input.json> <output.pptx>
 * Вызывается автоматически из ReportPptxController.
 */
const fs = require("fs");
const pptxgen = require("pptxgenjs");

const [, , inPath, outPath] = process.argv;
const D = JSON.parse(fs.readFileSync(inPath, "utf8"));

/* ---- палитра (фирменный винный) ---- */
const WINE = "4C181C", WINE_D = "3A1218", CREAM = "EAE3D4", CREAM_TX = "2A0F17",
      TX = "F3ECE0", MUT = "C9B3AC", ACCENT = "A5303B", GREEN = "84E05A", NEG = "F0A29B", DIM = "9C8983";
const FONT = "Arial";

const p = new pptxgen();
p.layout = "LAYOUT_WIDE";
const S = p.ShapeType, CH = p.ChartType;
const fmt = (n) => Math.round(Number(n) || 0).toLocaleString("ru-RU");
const PLAT = D.platformNames || { vk: "ВКонтакте", ig: "Инстаграм", max: "Макс" };
const KEYS = Object.keys(D.current || {}).filter((k) => D.current[k]);

const METR = {
  subs: "Подписчики", views: "Просмотры", reach: "Охваты", inter: "Взаимодействия",
  er: "ER %", leads: "Переходы", posts: "Посты", stories: "Сторис",
};

function slide(bg) {
  const s = p.addSlide();
  s.background = { color: bg || WINE };
  return s;
}
function logo(s, onCream) {
  const col = onCream ? WINE : TX;
  s.addShape(S.rect, { x: 0.55, y: 0.4, w: 0.035, h: 0.34, fill: { color: ACCENT } });
  s.addText("ИСТИНА", { x: 0.63, y: 0.4, w: 3, h: 0.3, fontFace: FONT, bold: true, fontSize: 13, color: col, margin: 0, charSpacing: 1 });
  s.addText("В МАРКЕТИНГЕ", { x: 0.64, y: 0.72, w: 3, h: 0.24, fontFace: FONT, fontSize: 8, color: onCream ? DIM : MUT, margin: 0, charSpacing: 2 });
}
function title(s, t, onCream) {
  s.addText(t, { x: 0.5, y: 0.42, w: 12.33, h: 0.7, align: "center", fontFace: FONT, bold: true, fontSize: 27, color: onCream ? WINE : TX, charSpacing: 1 });
}
function tile(s, x, y, w, h, value, label, delta, dGood) {
  s.addShape(S.roundRect, { x, y, w, h, rectRadius: 0.09, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  if (delta !== undefined && delta !== null) {
    s.addText(delta, { x: x + w - 1.7, y: y + 0.14, w: 1.55, h: 0.3, align: "right", fontFace: FONT, bold: true, fontSize: 11, color: dGood ? GREEN : NEG, margin: 0 });
  }
  s.addText(value, { x: x + 0.18, y: y + h * 0.26, w: w - 0.36, h: 0.6, fontFace: FONT, bold: true, fontSize: 25, color: TX, margin: 0 });
  s.addText(label, { x: x + 0.18, y: y + h - 0.5, w: w - 0.36, h: 0.42, fontFace: FONT, fontSize: 11, color: MUT, margin: 0 });
}
function sumPlat(stats, k) {
  return KEYS.reduce((a, p2) => a + (stats && stats[p2] ? Number(stats[p2][k] || 0) : 0), 0);
}
function deltaTxt(cur, prev, pp) {
  if (prev === null || prev === undefined) return [null, true];
  const d = cur - prev;
  if (pp) return [(d > 0 ? "+" : "") + Math.round(d) + " п.п.", d >= 0];
  return [(d > 0 ? "+" : "") + fmt(d), d >= 0];
}

/* ============ 1. ОБЛОЖКА ============ */
{
  const s = slide(WINE);
  logo(s);
  s.addText("ОТЧЁТ SMM", { x: 0.5, y: 2.5, w: 12.33, h: 1.3, align: "center", fontFace: FONT, bold: true, fontSize: 56, color: TX, charSpacing: 2 });
  s.addText("для " + (D.client || ""), { x: 0.5, y: 3.9, w: 12.33, h: 0.5, align: "center", fontFace: FONT, fontSize: 22, color: MUT });
  s.addShape(S.roundRect, { x: 10.75, y: 6.6, w: 2.05, h: 0.5, rectRadius: 0.1, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  s.addText(D.period || "", { x: 10.75, y: 6.6, w: 2.05, h: 0.5, align: "center", valign: "middle", fontFace: FONT, fontSize: 13, color: TX, margin: 0 });
}

/* ============ 2. ИТОГИ МЕСЯЦА ============ */
{
  const s = slide(WINE);
  logo(s); title(s, "ИТОГИ МЕСЯЦА");
  const prev = D.previous && D.previous.stats ? D.previous.stats : null;
  const cur = D.current;
  const vk = cur.vk || {};
  const tiles = [
    [fmt(sumPlat(cur, "views")), "Просмотры · все площадки", ...deltaTxt(sumPlat(cur, "views"), prev ? sumPlat(prev, "views") : null)],
    [fmt(sumPlat(cur, "inter")), "Взаимодействия", ...deltaTxt(sumPlat(cur, "inter"), prev ? sumPlat(prev, "inter") : null)],
    [fmt(vk.leads || 0), "Переходы на сайт (ВК)", ...deltaTxt(vk.leads || 0, prev && prev.vk ? prev.vk.leads : null)],
    [fmt(sumPlat(cur, "subs")), "База подписчиков", ...deltaTxt(sumPlat(cur, "subs"), prev ? sumPlat(prev, "subs") : null)],
    [(vk.er || 0) + "%", "ER ВКонтакте", ...deltaTxt(vk.er || 0, prev && prev.vk ? prev.vk.er : null, true)],
  ];
  const tw = 2.38, th = 1.85, gap = 0.16, x0 = 0.7;
  tiles.forEach((t, i) => tile(s, x0 + i * (tw + gap), 1.55, tw, th, t[0], t[1], t[2], t[3]));
  s.addShape(S.roundRect, { x: 0.7, y: 3.75, w: 11.93, h: 2.4, rectRadius: 0.09, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  s.addText("Комментарий агентства", { x: 0.95, y: 3.95, w: 11, h: 0.35, fontFace: FONT, bold: true, fontSize: 14, color: ACCENT, margin: 0 });
  s.addText(D.summary || "—", { x: 0.95, y: 4.35, w: 11.4, h: 1.6, fontFace: FONT, fontSize: 14, color: TX, lineSpacingMultiple: 1.2, margin: 0, valign: "top" });
  s.addText(D.previous && D.previous.label ? "Динамика — к предыдущему месяцу (" + D.previous.label + ")" : "", { x: 0.7, y: 6.55, w: 12, h: 0.3, align: "center", fontFace: FONT, italic: true, fontSize: 10, color: DIM, margin: 0 });
}

/* ============ 3. ЗАДАЧИ: ПЛАН / ФАКТ ============ */
if ((D.tasks || []).length) {
  const s = slide(WINE);
  logo(s); title(s, "ЗАДАЧИ: ПЛАН / ФАКТ");
  const rows = [[
    { text: "ЗАДАЧА", options: { bold: true, color: DIM, fontSize: 11 } },
    { text: "ПЛАН", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: "ФАКТ", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: "СТАТУС", options: { bold: true, color: DIM, fontSize: 11, align: "right" } },
  ]];
  D.tasks.forEach((t) => rows.push([
    { text: t.title, options: { color: TX, fontSize: 12 } },
    { text: t.plan || "—", options: { color: MUT, fontSize: 12, align: "center" } },
    { text: t.fact || "—", options: { color: TX, bold: true, fontSize: 12, align: "center" } },
    { text: "✓ " + (t.status || ""), options: { color: GREEN, bold: true, fontSize: 11, align: "right" } },
  ]));
  s.addTable(rows, { x: 0.7, y: 1.7, w: 11.93, colW: [6.5, 1.6, 1.6, 2.23], border: { type: "solid", color: "5A2530", pt: 1 }, rowH: 0.42, valign: "middle", fill: { color: WINE_D } });
}

/* ============ 4. РЕЗУЛЬТАТЫ ДЛЯ БИЗНЕСА ============ */
{
  const b = D.business || {};
  const vk = D.current.vk || {};
  const s = slide(WINE);
  logo(s); title(s, "РЕЗУЛЬТАТЫ ДЛЯ БИЗНЕСА");
  const tiles = [
    [fmt(vk.leads || b.site_clicks || 0), "Переходов на сайт из ВК"],
    [b.ad_clicks ? fmt(b.ad_clicks) : "—", "Переходов с рекламы"],
    [b.ad_price ? b.ad_price + " ₽" : "—", "Цена перехода"],
    [b.ad_budget ? fmt(b.ad_budget) + " ₽" : "—", "Бюджет размещения"],
  ];
  const tw = 2.95, th = 1.9, gap = 0.18, x0 = 0.7;
  tiles.forEach((t, i) => tile(s, x0 + i * (tw + gap), 1.75, tw, th, t[0], t[1]));
  s.addShape(S.roundRect, { x: 0.7, y: 3.95, w: 11.93, h: 2.2, rectRadius: 0.09, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  s.addText("Что это значит для клиента", { x: 0.95, y: 4.15, w: 11, h: 0.35, fontFace: FONT, bold: true, fontSize: 14, color: ACCENT, margin: 0 });
  s.addText("Соцсети работают как канал трафика на сайт. Заявки и продажи подставляются из вашей CRM — тогда в отчёте появляется стоимость заявки и окупаемость.", { x: 0.95, y: 4.6, w: 11.4, h: 1.2, fontFace: FONT, fontSize: 13.5, color: TX, lineSpacingMultiple: 1.2, margin: 0, valign: "top" });
}

/* ============ 5..N. ПЛОЩАДКИ ============ */
KEYS.forEach((pk) => {
  const cur = D.current[pk];
  const prev = D.previous && D.previous.stats ? D.previous.stats[pk] : null;
  const s = slide(WINE);
  logo(s); title(s, (PLAT[pk] || pk).toUpperCase() + " · ДИНАМИКА");

  const keys = ["subs", "views", "reach", "inter", "er", "leads", "posts", "stories"]
    .filter((k) => (cur[k] || 0) !== 0 || (prev && prev[k]));

  const rows = [[
    { text: "Показатель", options: { bold: true, color: DIM, fontSize: 11 } },
    { text: D.previous && D.previous.label ? D.previous.label : "Пред.", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: D.period || "Тек.", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: "Δ", options: { bold: true, color: DIM, fontSize: 11, align: "right" } },
  ]];
  keys.forEach((k) => {
    const isPp = k === "er";
    const [dt, good] = deltaTxt(cur[k], prev ? prev[k] : null, isPp);
    const f = (v) => (isPp ? Math.round(v) + "%" : fmt(v));
    rows.push([
      { text: METR[k], options: { color: TX, fontSize: 12 } },
      { text: prev ? f(prev[k]) : "—", options: { color: MUT, fontSize: 12, align: "center" } },
      { text: f(cur[k]), options: { color: TX, bold: true, fontSize: 12, align: "center" } },
      { text: dt || "—", options: { color: dt && good ? GREEN : NEG, bold: true, fontSize: 11, align: "right" } },
    ]);
  });
  s.addTable(rows, { x: 0.7, y: 1.65, w: 6.2, colW: [2.6, 1.4, 1.4, 0.8], border: { type: "solid", color: "5A2530", pt: 1 }, rowH: 0.44, valign: "middle", fill: { color: WINE_D } });

  // chart: просмотры по месяцам из series
  const labels = (D.series || []).map((r) => r.k);
  const values = (D.series || []).map((r) => (r[pk] ? r[pk].views : 0));
  if (labels.length > 1) {
    s.addChart(CH.bar, [{ name: "Просмотры", labels, values }], {
      x: 7.1, y: 1.65, w: 5.6, h: 4.6, barDir: "col", chartColors: [ACCENT], barGapWidthPct: 30,
      showLegend: false, showTitle: true, title: "Просмотры по месяцам", titleColor: TX, titleFontFace: FONT, titleFontSize: 13,
      showValue: true, dataLabelColor: TX, dataLabelFontFace: FONT, dataLabelFontSize: 8, dataLabelPosition: "outEnd",
      catAxisLabelColor: MUT, catAxisLabelFontFace: FONT, catAxisLabelFontSize: 9,
      valAxisHidden: true, catAxisLineShow: false, valAxisLineShow: false,
      catGridLine: { style: "none" }, valGridLine: { style: "none" },
    });
  }
});

/* ============ КОНТЕНТ ============ */
if ((D.content || []).length) {
  const s = slide(WINE);
  logo(s); title(s, "ТОП-КОНТЕНТ");
  const rows = [[
    { text: "Публикация", options: { bold: true, color: DIM, fontSize: 11 } },
    { text: "Просмотры", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: "Реакции", options: { bold: true, color: DIM, fontSize: 11, align: "center" } },
    { text: "Вывод", options: { bold: true, color: DIM, fontSize: 11 } },
  ]];
  D.content.slice(0, 6).forEach((c) => rows.push([
    { text: c.title, options: { color: TX, fontSize: 12 } },
    { text: fmt(c.views), options: { color: TX, bold: true, fontSize: 12, align: "center" } },
    { text: fmt(c.reactions), options: { color: MUT, fontSize: 12, align: "center" } },
    { text: c.insight || "", options: { color: MUT, fontSize: 11 } },
  ]));
  s.addTable(rows, { x: 0.7, y: 1.65, w: 11.93, colW: [4.2, 1.6, 1.4, 4.73], border: { type: "solid", color: "5A2530", pt: 1 }, rowH: 0.5, valign: "middle", fill: { color: WINE_D } });
}

/* ============ ВЫВОДЫ ============ */
{
  const s = slide(WINE);
  logo(s); title(s, "ВЫВОДЫ И ПЛАН");
  s.addShape(S.roundRect, { x: 0.7, y: 1.7, w: 6.0, h: 4.4, rectRadius: 0.09, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  s.addText("Выводы месяца", { x: 0.95, y: 1.9, w: 5.5, h: 0.35, fontFace: FONT, bold: true, fontSize: 14, color: ACCENT, margin: 0 });
  s.addText(D.summary || "—", { x: 0.95, y: 2.35, w: 5.5, h: 3.5, fontFace: FONT, fontSize: 13, color: TX, lineSpacingMultiple: 1.3, margin: 0, valign: "top" });
  s.addShape(S.roundRect, { x: 6.9, y: 1.7, w: 5.73, h: 4.4, rectRadius: 0.09, fill: { color: WINE_D }, line: { color: "5A2530", width: 1 } });
  s.addText("План на следующий месяц", { x: 7.15, y: 1.9, w: 5.3, h: 0.35, fontFace: FONT, bold: true, fontSize: 14, color: ACCENT, margin: 0 });
  const plan = (D.plan_next || "").split("\n").filter(Boolean);
  s.addText(plan.length ? plan.map((t) => ({ text: t, options: { bullet: { code: "2022" } } })) : "—",
    { x: 7.3, y: 2.35, w: 5.2, h: 3.5, fontFace: FONT, fontSize: 13, color: TX, lineSpacingMultiple: 1.3, paraSpaceAfter: 6, margin: 0, valign: "top" });
}

/* ============ КОНТАКТЫ ============ */
{
  const s = slide(WINE);
  s.addText("КОНТАКТЫ", { x: 0.7, y: 0.9, w: 6, h: 1, fontFace: FONT, bold: true, fontSize: 42, color: TX, charSpacing: 1 });
  const rows = [["Адрес", "г. Тула, ул. Генерала Маргелова, 5А, 2 эт., оф. 30"], ["Телефон", "+7 967 469-69-96"], ["Email", "info@istinavm.ru"], ["Сайт", "истина-маркетинга.рф"], ["Режим работы", "ПН–ПТ с 9:00 до 18:00"]];
  let y = 2.4;
  rows.forEach((r) => {
    s.addText(r[0] + ":", { x: 0.7, y, w: 3, h: 0.4, fontFace: FONT, bold: true, fontSize: 15, color: ACCENT, margin: 0 });
    s.addText(r[1], { x: 3.6, y, w: 9, h: 0.4, fontFace: FONT, fontSize: 15, color: TX, margin: 0 });
    y += 0.82;
  });
}

p.writeFile({ fileName: outPath }).then(() => console.log("OK " + outPath));
