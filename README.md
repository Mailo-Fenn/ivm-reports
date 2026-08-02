# Портал SMM-отчётности — ИВМ (v2)

Веб-приложение: **проекты (клиенты) → месячные отчёты → дашборд по площадкам**
с динамикой месяц-к-месяцу, графиками и **автоматической выгрузкой отчёта в PowerPoint**.

Стек: **Laravel 11 + Inertia + React 18 + Vite + Recharts**, генерация PPTX — **pptxgenjs (Node)**.
Дизайн — фирменный ИВМ: светлый крафт-фон с винными плашками.

## Что нового по сравнению с v1
- **Площадки как измерение** (ВКонтакте / Инстаграм / Макс): отдельные детальные страницы, свои KPI, графики за полгода, таблица «месяц к месяцу».
- **Динамика к предыдущему месяцу**: зелёные/красные дельты в углу карточек считаются относительно прошлого месяца (прошлого отчёта проекта), а не внутри месяца.
- **Разделы отчёта**: задачи (план/факт), результаты для бизнеса, топ-контент (посты/Reels), работа с сообществом, выводы, план на следующий месяц.
- **Выгрузка в PowerPoint** одной кнопкой — сервер сам собирает брендированную презентацию из данных отчёта.
- **Фирменный дизайн** (крафт + вино).
- **Понедельная статистика из v1 сохранена** — доступна в режиме редактирования (раздел «архив»).

## Требования
- PHP 8.2+ и Composer
- Node.js 18+ и npm (нужен и для сборки фронтенда, и для генерации PPTX)

## Запуск (локально)
```bash
composer install
npm install                       # ставит фронтенд-зависимости И pptxgenjs для выгрузки PPTX

cp .env.example .env
php artisan key:generate
php artisan migrate --seed         # таблицы + демо-проект NewStar (4 месяца данных)

# два терминала:
npm run dev
php artisan serve                  # http://localhost:8000
```
Демо-проект «NewStar» открывается с готовыми отчётами за март–июнь 2026.

## Как формируется PowerPoint
На странице отчёта — кнопка **«Скачать PowerPoint»**. Она вызывает маршрут
`GET /reports/{report}/pptx` → контроллер собирает данные отчёта в JSON и запускает
`node pptx/generate.cjs <data.json> <out.pptx>`. Генератор (`pptx/generate.cjs`) строит
брендированную презентацию: обложка, итоги месяца, задачи план/факт, результаты для бизнеса,
динамика по каждой площадке (таблица + нативный график), топ-контент, выводы, план, контакты.

Всё оформление — цветами (без внешних картинок), поэтому на сервере не нужен ни Python, ни headless-браузер.
Достаточно установленного Node.js (`node` в PATH) и выполненного `npm install`.

## Структура (новое выделено)
```
app/Http/Controllers/
  ProjectController.php
  ReportController.php          ← площадки, MoM, задачи, контент, блоки
  ReportPptxController.php      ← выгрузка PowerPoint  (новое)
app/Models/
  Project, Report (+previousReport, totals)
  PlatformStat, ReportTask, ContentItem   (новое)
  WeeklyStat                    ← сохранён из v1
database/migrations/            ← + platform_stats, report_tasks, content_items, поля reports
pptx/generate.cjs               ← генератор презентации (новое)
resources/js/Pages/Reports/Show.jsx   ← дашборд: обзор + площадки + редактор + PPTX
resources/css/app.css           ← фирменный крафт+вино
```

## Данные
- **platform_stats**: report_id, platform (vk/ig/max), subs, views, reach, inter, leads, posts, stories (ER считается автоматически)
- **report_tasks**: title, plan, fact, status
- **content_items**: platform, kind (post/reels/story), title, views, reactions, comments, reposts, insight
- **reports**: + plan_next, community, business (json)
- **weekly_stats**: без изменений (из v1)

Прод-сборка фронтенда: `npm run build`.
"# ivm-reports" 
