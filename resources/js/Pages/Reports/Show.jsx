import axios from 'axios';
import React, { useState, useMemo, useRef, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import {
	ResponsiveContainer, AreaChart, Area, BarChart, Bar, LineChart, Line,
	XAxis, YAxis, CartesianGrid, Tooltip, Cell, LabelList,
} from 'recharts';
import {
	Users, Eye, Radar, Heart, Target, MousePointerClick, FileText, Film,
	TrendingUp, TrendingDown, Minus, ArrowRight, LayoutGrid, Pencil, Check, X,
	FileDown, Trash2, Plus, RefreshCw,
	Globe, UserRound, Link2, Wallet, Tag, BarChart3,
} from 'lucide-react';
import Layout from '../../Layout';

const K = { wine: '#4C181C', accent: '#A5303B', mut: '#7C6B60' };

const fInt = (n) => Math.round(Number(n) || 0).toLocaleString('ru-RU');
const pct = (n) => Math.round(Number(n) || 0) + '%';
const kAxis = (v) => (Math.abs(v) >= 1000 ? (v / 1000).toLocaleString('ru-RU', { maximumFractionDigits: Math.abs(v) < 10000 ? 1 : 0 }) + 'к' : String(v));
// подпись значения столбика: внутри у верхнего края, а если столбик слишком низкий — над ним
function BarValue({ x, y, width, height, value, fmt }) {
	if (!Number(value)) return null;
	const inside = height >= 20;
	return (
		<text
			className={'bar-label' + (inside ? ' in' : ' out')}
			x={x + width / 2}
			y={inside ? y + 14 : y - 5}
			textAnchor="middle"
			fontSize={11}
			fontWeight={700}
		>
			{(fmt || fInt)(value)}
		</text>
	);
}
const erOf = (o) => (o && o.subs ? +((o.inter / o.subs) * 100).toFixed(1) : 0);
const bizNum = (v) => Number(String(v ?? '').replace(/\s/g, '').replace(',', '.')) || 0;
const bizRate = (biz, key, mult = 1) => {
	const budget = bizNum(biz?.ad_budget), base = bizNum(biz?.[key]);
	return budget > 0 && base > 0 ? (budget / base) * mult : null;
};
const bizRateFmt = (biz, key, mult = 1) => {
	const r = bizRate(biz, key, mult);
	return r ? r.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + ' ₽' : '—';
};

const METR = {
	subs: { label: 'Подписчики', icon: Users, fmt: fInt },
	views: { label: 'Просмотры', icon: Eye, fmt: fInt },
	reach: { label: 'Охваты', icon: Radar, fmt: fInt },
	inter: { label: 'Взаимодействия', icon: Heart, fmt: fInt },
	er: { label: 'ER %', icon: Target, fmt: pct, pp: true },
	leads: { label: 'Переходы на сайт', icon: MousePointerClick, fmt: fInt },
	posts: { label: 'Посты', icon: FileText, fmt: fInt },
	stories: { label: 'Сторис', icon: Film, fmt: fInt },
};
const KEYS = {
	vk: ['subs', 'views', 'reach', 'inter', 'er', 'leads', 'posts', 'stories'],
	ig: ['subs', 'views', 'reach', 'inter', 'er', 'posts', 'stories'],
	max: ['subs', 'views', 'inter', 'er'],
	yt: ['subs', 'views', 'inter', 'er', 'posts'],
	tg: ['subs', 'views', 'inter', 'er', 'posts'],
};

// «Работа с сообществом» по площадкам: {vk: [{image, caption}], …}; старый плоский список — записи ВК
const normCommunity = (c) => (Array.isArray(c) ? (c.length ? { vk: c } : {}) : (c && typeof c === 'object' ? c : {}));

const emptyStat = () => ({ subs: 0, views: 0, reach: 0, inter: 0, leads: 0, posts: 0, stories: 0 });

const calcPlatformStatsFromWeeks = (weeks) => {
	const result = {};

	weeks.forEach(w => {
		if (!result[w.platform]) {
			result[w.platform] = emptyStat();
		}

		result[w.platform].views += Number(w.views) || 0;
		result[w.platform].reach += Number(w.reach) || 0;
		result[w.platform].inter += Number(w.inter) || 0;
		result[w.platform].leads += Number(w.leads) || 0;
		result[w.platform].posts += Number(w.posts) || 0;
		result[w.platform].stories += Number(w.stories) || 0;
	});

	// подписчики — общее число на конец недели: берём последнюю заполненную неделю, а не сумму
	Object.keys(result).forEach((p) => {
		const last = weeks
			.filter((w) => w.platform === p && Number(w.subs))
			.sort((a, b) => (a.position || 0) - (b.position || 0))
			.pop();
		result[p].subs = last ? Number(last.subs) : 0;
	});

	return result;
};

function useCountUp(target) {
	const [v, setV] = useState(target);
	const ref = useRef(target);
	useEffect(() => {
		const from = ref.current, to = Number(target) || 0;
		if (from === to) { setV(to); return; }
		let raf; const s = performance.now();
		const tick = (now) => {
			const t = Math.min(1, (now - s) / 600);
			const e = 1 - Math.pow(1 - t, 3);
			setV(from + (to - from) * e);
			if (t < 1) raf = requestAnimationFrame(tick); else ref.current = to;
		};
		raf = requestAnimationFrame(tick);
		return () => cancelAnimationFrame(raf);
	}, [target]);
	return v;
}
function Delta({ diff, pp }) {
	if (diff === null || diff === undefined) return <span className="chip-d flat"><Minus size={12} /> —</span>;
	const up = diff > 0.001, down = diff < -0.001;
	const Ico = up ? TrendingUp : down ? TrendingDown : Minus;
	const txt = pp ? (diff > 0 ? '+' : '') + Math.round(diff) + ' п.п.' : (diff > 0 ? '+' : '') + fInt(diff);
	return <span className={'chip-d ' + (up ? 'up' : down ? 'down' : 'flat')}><Ico size={12} /> {txt}</span>;
}
function KpiNum({ value, isPct }) {
	const v = useCountUp(value);
	return <div className="kpi-num">{isPct ? pct(v) : fInt(v)}</div>;
}
function Kpi({ mkey, value, prev }) {
	const meta = METR[mkey];
	const Ico = meta.icon;
	const diff = prev === null || prev === undefined ? null : value - prev;
	return (
		<div className="kpi">
			<div className="kpi-top"><span className="kpi-ico"><Ico size={15} /></span><Delta diff={diff} pp={meta.pp} /></div>
			<KpiNum value={value} isPct={meta.pp} />
			<div className="kpi-lbl">{meta.label}</div>
		</div>
	);
}
const AX = { fill: K.mut, fontSize: 11, fontFamily: 'Manrope' };
function Tip({ active, payload, label, fmt = fInt }) {
	if (!active || !payload?.length) return null;
	return <div className="tip"><div className="tip-l">{label}</div><div className="tip-v">{fmt(payload[0].value)}</div></div>;
}
function Bars({ data, dataKey, highlight, fmt }) {
	return (
		<div className="chart">
			<ResponsiveContainer width="100%" height="100%">
				<BarChart data={data} margin={{ top: 14, right: 4, left: -12, bottom: 0 }}>
					<CartesianGrid vertical={false} stroke="#D2CAB8" />
					<XAxis dataKey="k" tick={AX} axisLine={false} tickLine={false} />
					<YAxis tickFormatter={kAxis} tick={AX} axisLine={false} tickLine={false} width={38} />
					<Tooltip content={<Tip fmt={fmt} />} cursor={{ fill: 'rgba(76,24,28,.06)' }} />
					<Bar dataKey={dataKey} radius={[6, 6, 0, 0]} maxBarSize={40} isAnimationActive={false}>
						{data.map((d, i) => <Cell key={i} fill={i === highlight ? K.accent : '#8A5157'} />)}
						<LabelList dataKey={dataKey} content={<BarValue fmt={fmt} />} />
					</Bar>
				</BarChart>
			</ResponsiveContainer>
		</div>
	);
}
function AreaOne({ data, dataKey }) {
	return (
		<div className="chart">
			<ResponsiveContainer width="100%" height="100%">
				<AreaChart data={data} margin={{ top: 18, right: 22, left: -12, bottom: 0 }}>
					<defs><linearGradient id={'g' + dataKey} x1="0" y1="0" x2="0" y2="1">
						<stop offset="0%" stopColor={K.wine} stopOpacity={0.28} /><stop offset="100%" stopColor={K.wine} stopOpacity={0} />
					</linearGradient></defs>
					<CartesianGrid vertical={false} stroke="#D2CAB8" />
					<XAxis dataKey="k" tick={AX} axisLine={false} tickLine={false} />
					<YAxis tickFormatter={kAxis} tick={AX} axisLine={false} tickLine={false} width={38} />
					<Tooltip content={<Tip />} />
					<Area type="monotone" dataKey={dataKey} stroke={K.wine} strokeWidth={2.5} fill={'url(#g' + dataKey + ')'} dot={{ r: 3.5, fill: K.wine, strokeWidth: 0 }} activeDot={{ r: 5 }} isAnimationActive={false}>
						<LabelList dataKey={dataKey} position="top" offset={9} formatter={(v) => (Number(v) ? fInt(v) : '')} className="pt-label" />
					</Area>
				</AreaChart>
			</ResponsiveContainer>
		</div>
	);
}
function LineErr({ data }) {
	return (
		<div className="chart">
			<ResponsiveContainer width="100%" height="100%">
				<LineChart data={data} margin={{ top: 18, right: 22, left: -12, bottom: 0 }}>
					<CartesianGrid vertical={false} stroke="#D2CAB8" />
					<XAxis dataKey="k" tick={AX} axisLine={false} tickLine={false} />
					<YAxis tickFormatter={(v) => v + '%'} tick={AX} axisLine={false} tickLine={false} width={38} />
					<Tooltip content={<Tip fmt={(v) => v + '%'} />} />
					<Line type="monotone" dataKey="er" stroke={K.accent} strokeWidth={2.5} dot={{ r: 3.5, fill: K.accent, strokeWidth: 0 }} activeDot={{ r: 5 }} isAnimationActive={false}>
						<LabelList dataKey="er" position="top" offset={9} formatter={(v) => (Number(v) ? v + '%' : '')} className="pt-label" />
					</Line>
				</LineChart>
			</ResponsiveContainer>
		</div>
	);
}
function Panel({ eyebrow, title, note, children, light }) {
	return (
		<section className={'panel' + (light ? ' light' : '')}>
			{(eyebrow || title) && (
				<div className="panel-head">
					<div>{eyebrow && <div className="eyebrow">{eyebrow}</div>}{title && <h2 className="panel-title">{title}</h2>}</div>
					{note && <span className="note">{note}</span>}
				</div>
			)}
			{children}
		</section>
	);
}

export default function Show({ project, report, reports, platformNames, current, previous, series, tasks, content, weeks }) {
	const ALL_PLATFORMS = Object.keys(current);
	const [view, setView] = useState('overview');
	const [editing, setEditing] = useState(false);
	const [saving, setSaving] = useState(false);

	const initPf = () => Object.fromEntries(ALL_PLATFORMS.map((p) => [p, { ...emptyStat(), ...(current[p] || {}) }]));
	const [pf, setPf] = useState(initPf);
	const [tk, setTk] = useState(tasks || []);
	const [ct, setCt] = useState(content || []);
	const [summary, setSummary] = useState(report.summary || '');
	const [plan, setPlan] = useState(report.plan_next || '');
	const [community, setCommunity] = useState(normCommunity(report.community));
	const [biz, setBiz] = useState(report.business || {});
	// у включённой площадки без строк по неделям (YouTube в старых отчётах) создаём пустые недели,
	// иначе в редакторе нечего заполнять; на сервер они уйдут при сохранении
	const initWk = () => {
		const base = weeks || [];
		const missing = ALL_PLATFORMS.filter((p) => current[p]?.is_enabled && !base.some((w) => w.platform === p));
		return [...base, ...missing.flatMap((p) => [1, 2, 3, 4].map((i) => ({ platform: p, position: i, label: `Неделя ${i}`, ...emptyStat() })))];
	};
	const [wk, setWk] = useState(initWk);
	const [mn, setMn] = useState(report.metric_notes || {});

	useEffect(() => {

	const calculated = calcPlatformStatsFromWeeks(wk);

		setPf(prev => {
			const updated = {...prev};

			Object.keys(calculated).forEach(platform => {
				updated[platform] = {
					...updated[platform],
					...calculated[platform],
				};
			});

			return updated;
		});

	}, [wk]);

	const PLIST = ALL_PLATFORMS.filter(
		p => pf[p]?.is_enabled
	);

	const togglePlatform = (platform) => {
		// у площадки, которой раньше не было в отчёте, нет строк по неделям — создаём пустые
		if (!pf[platform]?.is_enabled && !wk.some((w) => w.platform === platform)) {
			setWk([...wk, ...[1, 2, 3, 4].map((i) => ({ platform, position: i, label: `Неделя ${i}`, ...emptyStat() }))]);
		}
		setPf(prev => ({
			...prev,
			[platform]: {
				...prev[platform],
				is_enabled: !prev[platform]?.is_enabled
			}
		}));
	};

	useEffect(() => {
		setPf(initPf()); setTk(tasks || []); setCt(content || []);
		setSummary(report.summary || ''); setPlan(report.plan_next || '');
		setCommunity(normCommunity(report.community)); setBiz(report.business || {}); setWk(initWk());
		setMn(report.metric_notes || {});
	}, [report.id]);

	const num = (e) => (e.target.value === '' ? 0 : Number(e.target.value));
	const stats = pf;

	const total = (obj) => {
		const sum = (k) => PLIST.reduce((a, p) => a + (Number(obj[p]?.[k]) || 0), 0);
		// обзор — суммы по всем площадкам; общий ER = взаимодействия / подписчики
		const subs = sum('subs'), inter = sum('inter');
		return { subs, views: sum('views'), reach: sum('reach'), inter, leads: sum('leads'), er: subs ? +((inter / subs) * 100).toFixed(1) : 0 };
	};
	const curTot = useMemo(
		() => total(stats),
		[stats, PLIST]
	);
	const prevTot = previous.stats ? total(previous.stats) : null;

	const liveSeries = useMemo(() => {
		const s = (series || []).map((r) => ({ ...r }));
		if (s.length) {
			const last = { ...s[s.length - 1] };
			PLIST.forEach((p) => { last[p] = { ...(last[p] || {}), ...stats[p], er: erOf(stats[p]) }; });
			s[s.length - 1] = last;
		}
		return s;
	}, [series, stats]);
	const hi = liveSeries.length - 1;

	const save = () => {
		setSaving(true);
		router.put(`/reports/${report.id}`, {
			tasks: tk,
			content: ct,
			business: biz,
			summary,
			plan_next: plan,
			community,
			weeks: wk,
			metric_notes: mn,
			platforms: Object.fromEntries(ALL_PLATFORMS.map((p) => [p, !!pf[p]?.is_enabled])),
		}, {
			preserveScroll: true,

			onSuccess: () => {
				console.log('SUCCESS');
				setEditing(false);
			},

			onError: (errors) => {
				console.log(errors);
			},

			onFinish: () => {
				setSaving(false);
			}
		});
	};
	const cancel = () => {
		setEditing(false); setPf(initPf()); setTk(tasks || []); setCt(content || []);
		setSummary(report.summary || ''); setPlan(report.plan_next || '');
		setCommunity(normCommunity(report.community)); setBiz(report.business || {}); setWk(initWk());
		setMn(report.metric_notes || {});
	};
	const removeReport = () => { if (confirm('Удалить отчёт?')) router.delete(`/reports/${report.id}`); };

	const [pulling, setPulling] = useState(false);
	const pullVk = () => {
		if (!confirm('Подтянуть данные из ВК? Понедельные цифры ВКонтакте (подписчики, просмотры, охваты, взаимодействия, посты) будут перезаписаны данными из API. Переходы на сайт и сторис останутся как есть.')) return;
		setPulling(true);
		router.post(`/reports/${report.id}/vk-sync`, {}, {
			preserveScroll: true,
			onFinish: () => setPulling(false),
		});
	};
	const [pullingIg, setPullingIg] = useState(false);
	const pullIg = () => {
		if (!confirm('Подтянуть данные из Instagram? Понедельные цифры Instagram (охваты, просмотры, взаимодействия, публикации, а при наличии данных — подписчики и сторис) будут перезаписаны данными из API.')) return;
		setPullingIg(true);
		router.post(`/reports/${report.id}/instagram-sync`, {}, {
			preserveScroll: true,
			onFinish: () => setPullingIg(false),
		});
	};
	const [pullingTg, setPullingTg] = useState(false);
	const pullTg = () => {
		if (!confirm('Подтянуть данные из Telegram? Понедельные цифры Telegram (просмотры, взаимодействия, посты, а при наличии данных — подписчики) будут перезаписаны данными из канала.')) return;
		setPullingTg(true);
		router.post(`/reports/${report.id}/telegram-sync`, {}, {
			preserveScroll: true,
			onFinish: () => setPullingTg(false),
		});
	};
	const [pullingYt, setPullingYt] = useState(false);
	const pullYt = () => {
		if (!confirm('Подтянуть данные из YouTube? Понедельные цифры YouTube (подписчики, просмотры, взаимодействия, видео) будут перезаписаны данными из API. Охваты останутся как есть.')) return;
		setPullingYt(true);
		router.post(`/reports/${report.id}/youtube-sync`, {}, {
			preserveScroll: true,
			onFinish: () => setPullingYt(false),
		});
	};

	return (
		<Layout crumbs={[{ label: project.name, href: `/projects/${project.id}` }, editing ? { label: report.period_label, href: `/reports/${report.id}` } : { label: report.period_label }, ...(editing ? [{ label: 'Редактирование' }] : [])]}>
			<div className="page-head">
				<div>
					<div className="eyebrow">Отчёт по SMM · {project.name}</div>
					<h1 className="page-title">{view === 'overview' ? 'Обзор' : platformNames[view]} · {report.period_label}</h1>
				</div>
				<div className="page-actions">
					{editing ? (
						<>
							<button className="btn btn-primary" onClick={save} disabled={saving}><Check size={16} /> {saving ? 'Сохранение…' : 'Сохранить'}</button>
							<button className="btn" onClick={cancel}><X size={16} /> Отмена</button>
						</>
					) : (
						<>
							<button className="btn btn-primary" onClick={() => setEditing(true)}><Pencil size={16} /> Редактировать</button>
							<button className="btn" onClick={pullVk} disabled={pulling}><RefreshCw size={16} className={pulling ? 'spin' : undefined} /> {pulling ? 'Загрузка…' : 'Подтянуть из ВК'}</button>
							{project.instagram_connected && (
								<button className="btn" onClick={pullIg} disabled={pullingIg}><RefreshCw size={16} className={pullingIg ? 'spin' : undefined} /> {pullingIg ? 'Загрузка…' : 'Подтянуть из Instagram'}</button>
							)}
							{project.youtube_channel && (
								<button className="btn" onClick={pullYt} disabled={pullingYt}><RefreshCw size={16} className={pullingYt ? 'spin' : undefined} /> {pullingYt ? 'Загрузка…' : 'Подтянуть из YouTube'}</button>
							)}
							{project.telegram_channel && (
								<button className="btn" onClick={pullTg} disabled={pullingTg}><RefreshCw size={16} className={pullingTg ? 'spin' : undefined} /> {pullingTg ? 'Загрузка…' : 'Подтянуть из Telegram'}</button>
							)}
							<a className="btn btn-accent" href={`/reports/${report.id}/pptx`}><FileDown size={16} /> Скачать PowerPoint</a>
							<button className="btn btn-danger" onClick={removeReport}><Trash2 size={16} /></button>
						</>
					)}
				</div>
			</div>

			<div className="tabs">
				<button className={'tab' + (view === 'overview' ? ' on' : '')} onClick={() => setView('overview')}><LayoutGrid size={15} /> Обзор</button>
				{PLIST.map((p) => <button key={p} className={'tab' + (view === p ? ' on' : '')} onClick={() => setView(p)}>{platformNames[p]}</button>)}

				<div className="tab-note">Динамика — к предыдущему месяцу{previous.label ? ` (${previous.label})` : ''}</div>
			</div>

			<div className="tabs-months">
				{reports.map((m) => {
					const active = m.id === report.id;

					return active ? (
						<span
							key={m.id}
							className="tab current"
						>
							{m.period_label}
						</span>
					) : (
						<Link
							key={m.id}
							href={`/reports/${m.id}`}
							className="tab"
						>
							{m.period_label}
						</Link>
					);
				})}
			</div>

			{view === 'overview'
				? <Overview {...{ platformNames, stats, curTot, prevTot, liveSeries, hi, tasks: tk, biz, summary, plan, community, setView, PLIST }} />
				: <PlatformView pid={view} {...{ platformNames, stats, previous, liveSeries, content: ct, PLIST, periodLabel: report.period_label }} />}

			{editing && <Editor {...{ platformNames, PLIST, togglePlatform, current, previous, pf, setPf, tk, setTk, ct, setCt, biz, setBiz, summary, setSummary, plan, setPlan, community, setCommunity, wk, setWk, mn, setMn, num }} />}

			<footer className="foot"><span>Истина в маркетинге · istinavm.ru</span><span>{project.name} · {report.period_label}</span></footer>
		</Layout>
	);
}

// карточка блока «Результаты»: акцентная (винная) для ключевых цифр и светлая для остальных
function BizCard({ icon: Ico, value, label, accent }) {
	return (
		<div className={'biz' + (accent ? ' biz-accent' : '')}>
			<span className="biz-ico"><Ico size={16} /></span>
			<div>
				<div className="biz-v">{value}</div>
				<div className="biz-l">{label}</div>
			</div>
		</div>
	);
}

function Overview({ platformNames, stats, curTot, prevTot, liveSeries, hi, tasks, biz, summary, plan, community, setView, PLIST }) {
	const kpis = [
		{ key: 'subs', label: 'База подписчиков', val: curTot.subs, prev: prevTot?.subs, icon: Users },
		{ key: 'views', label: 'Просмотры · все площадки', val: curTot.views, prev: prevTot?.views, icon: Eye },
		{ key: 'reach', label: 'Охват · все площадки', val: curTot.reach, prev: prevTot?.reach, icon: Radar },
		{ key: 'inter', label: 'Взаимодействия', val: curTot.inter, prev: prevTot?.inter, icon: Heart },
		{ key: 'er', label: 'ER · все площадки', val: curTot.er, prev: prevTot?.er, icon: Target, pp: true, pct: true },
		{ key: 'leads', label: 'Переходы на сайт', val: curTot.leads, prev: prevTot?.leads, icon: MousePointerClick },
	];

	console.log(curTot)

	const chartData = liveSeries.map((r) => ({ k: r.k, views: PLIST.reduce((a, p) => a + (r[p]?.views || 0), 0) }));
	return (
		<>
			<div className="kpi-grid">
				{kpis.map((k) => {
					const diff = k.prev === null || k.prev === undefined ? null : k.val - k.prev;
					const Ico = k.icon;

					if (k.val == 0) {
						return;
					} else {
						return (
							<div className="kpi" key={k.key}>
								<div className="kpi-top"><span className="kpi-ico"><Ico size={15} /></span><Delta diff={diff} pp={k.pp} /></div>
								<KpiNum value={k.val} isPct={k.pct} /><div className="kpi-lbl">{k.label}</div>
							</div>
						);
					}
				})}
			</div>

			<Panel eyebrow="Динамика" title="Просмотры по всем площадкам">
				<Bars data={chartData} dataKey="views" highlight={hi} fmt={fInt} />
			</Panel>

			<Panel eyebrow="Каналы" title="Показатели по площадкам">
				<div className="plat-grid">
					{PLIST.map((p) => {
						const d = { ...stats[p], er: erOf(stats[p]) };
						return (
							<button key={p} className="plat-card" onClick={() => setView(p)}>
								<div className="plat-name">{platformNames[p]} <ArrowRight size={15} /></div>
								<div className="plat-big">{fInt(d.subs)} <span>подписчиков</span></div>
								<div className="plat-rows">
									<div><span>Просмотры</span><b>{fInt(d.views)}</b></div>
									<div><span>Взаимодействия</span><b>{fInt(d.inter)}</b></div>
									<div><span>ER</span><b>{pct(d.er)}</b></div>
								</div>
							</button>
						);
					})}
				</div>
			</Panel>

			<div className="two">
				<Panel eyebrow="Работа" title="Задачи: план / факт" light>
					<div className="tasks">
						<div className="task-row task-h"><span>Задача</span><span>План</span><span>Факт</span><span>Статус</span></div>
						{(tasks || [])
							.filter(t => t.type !== 'check')
							.map((t, i) => (
								<div className="task-row" key={i}>
									<span className="task-l">{t.title}</span>
									<span className="task-c">{t.plan || '—'}</span>
									<span className="task-c task-b">{t.fact || '—'}</span>
									{t.plan <= t.fact ? (<span className="task-s">✓ Выполнено</span>) : (<span className="task-s warn">✕ Не выполнено</span>)}
								</div>
							))}
					</div>
				</Panel>
				<Panel eyebrow="Работа" title="Чек-лист" light>
					<div className="tasks">
						{(tasks || [])
							.filter(t => t.type === 'check')
							.map((t, i) => (
								<div className="task-row check-list-task-row" key={i}>
									<span className="task-l">
										{t.title}
									</span>
									{t.status === 'выполнено' ? (<span className="task-s">✓ Выполнено</span>) : (<span className="task-s warn">✕ Не выполнено</span>)}
								</div>
							))}
					</div>
				</Panel>
			</div>

			{PLIST.filter((p) => (community[p] || []).length > 0).map((p) => (
				<Panel key={p} eyebrow="Сообщество" title={`Работа с сообществом · ${platformNames[p]}`}>
					<div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 16 }}>
						{community[p].map((c, i) => (
							<figure key={i} style={{ margin: 0 }}>
								{c.image && <img src={`/storage/${c.image}`} alt={c.caption || ''} style={{ width: '100%', borderRadius: 10, display: 'block' }} />}
								{c.caption && <figcaption className="plain" style={{ marginTop: c.image ? 8 : 0 }}>{c.caption}</figcaption>}
							</figure>
						))}
					</div>
				</Panel>
			))}

				<Panel eyebrow="Бизнес" title="Результаты" light>
					<div className="biz-key">
						<BizCard accent icon={Globe} value={biz.ad_clicks ? fInt(biz.ad_clicks) : '—'} label="Переходы с рекламы" />
						<BizCard accent icon={UserRound} value={biz.ad_subs ? fInt(biz.ad_subs) : '—'} label="Подписчики с рекламы" />
					</div>
					<div className="biz-grid">
						<BizCard icon={Link2} value={fInt(curTot.leads)} label="Переходы на сайт" />
						<BizCard icon={Eye} value={biz.ad_views ? fInt(biz.ad_views) : '—'} label="Просмотры с рекламы" />
						<BizCard icon={Wallet} value={biz.ad_budget ? fInt(biz.ad_budget) + ' ₽' : '—'} label="Бюджет рекламы" />
						<BizCard icon={Tag} value={bizRateFmt(biz, 'ad_clicks')} label="Цена перехода" />
						<BizCard icon={Tag} value={bizRateFmt(biz, 'ad_subs')} label="Цена подписчика" />
						<BizCard icon={BarChart3} value={bizRateFmt(biz, 'ad_views', 1000)} label="Цена 1000 просмотров" />
					</div>
				</Panel>

			<div className="two">
				<Panel eyebrow="Итоги" title="Выводы месяца" light>
					{summary ? summary.split('\n').map((l, i) => <p className="plain" key={i} style={{ marginTop: i ? 8 : 0 }}>{l}</p>) : <p className="plain" style={{ color: 'var(--mut)' }}>Не заполнено.</p>}
				</Panel>
				<Panel eyebrow="Дальше" title="План на следующий месяц">
					{plan ? <ul className="plan">{plan.split('\n').filter(Boolean).map((l, i) => <li key={i}>{l}</li>)}</ul> : <p className="plain" style={{ color: 'var(--creamMut)' }}>Не заполнено.</p>}
				</Panel>
			</div>

		</>
	);
}

function PlatformView({ pid, platformNames, stats, previous, liveSeries, content, PLIST, periodLabel }) {
	const cur = { ...stats[pid], er: erOf(stats[pid]) };
	const prevRaw = previous.stats ? previous.stats[pid] : null;
	const prev = prevRaw ? { ...prevRaw, er: erOf(prevRaw) } : null;
	const keys = KEYS[pid];
	const chartS = liveSeries.map((r) => ({ k: r.k, ...(r[pid] || {}) }));
	const items = (content || []).filter((c) => c.platform === pid);

	return (
		<>
			<div className="kpi-grid">
				{keys.map((k) => <Kpi key={k} mkey={k} value={cur[k]} prev={prev ? prev[k] : null} />)}
			</div>

			<div className="two">
				<Panel eyebrow="Аудитория" title="Подписчики по месяцам" light><AreaOne data={chartS} dataKey="subs" /></Panel>
				<Panel eyebrow="Охват" title="Просмотры по месяцам" light><Bars data={chartS} dataKey="views" highlight={chartS.length - 1} fmt={fInt} /></Panel>
			</div>
			<div className="two">
				<Panel eyebrow="Активность" title="Взаимодействия по месяцам" light><Bars data={chartS} dataKey="inter" highlight={chartS.length - 1} fmt={fInt} /></Panel>
				<Panel eyebrow="Вовлечённость" title="ER % по месяцам" light><LineErr data={chartS} /></Panel>
			</div>

			<Panel eyebrow="Сравнение" title={`${platformNames[pid]}: месяц к месяцу`} light>
				<div className="mom">
					<div className="mom-row mom-h"><span>Показатель</span><span>{previous.label || 'Прошлый месяц'}</span><span>{periodLabel}</span><span>Динамика</span></div>
					{keys.map((k) => {
						const meta = METR[k];
						const diff = prev ? cur[k] - prev[k] : null;
						const up = diff !== null && diff > 0.001, down = diff !== null && diff < -0.001;
						return (
							<div className="mom-row" key={k}>
								<span className="mom-lbl">{meta.label}</span>
								<span className="mom-mut">{prev ? meta.fmt(prev[k]) : '—'}</span>
								<span className="mom-cur">{meta.fmt(cur[k])}</span>
								<span className={'mom-d ' + (up ? 'u' : down ? 'd' : 'f')}>
									{diff === null ? '—' : meta.pp ? (diff > 0 ? '+' : '') + Math.round(diff) + ' п.п.' : (diff > 0 ? '+' : '') + fInt(diff)}
								</span>
							</div>
						);
					})}
				</div>
			</Panel>

			{items.length > 0 && (
				<Panel eyebrow="Контент" title={`Топ-контент · ${platformNames[pid]}`}>
					{pid === 'ig' ? (
						<div className="reels">
							{items.map((c, i) => <div className="reel" key={i}>
								{c.image && (
									<img
										src={`/storage/${c.image}`}
										alt={c.title}
										className="content-image"
									/>
								)}

								<b>{fInt(c.views)}</b><span>просмотры</span><em>{c.title}</em></div>)}
						</div>
					) : (
						<div className="two">
							{items.map((c, i) => (
								<div className="post" key={i}>
									<div>
										<div className="post-title">{c.title}</div>
										{c.image && (
											<img
												src={`/storage/${c.image}`}
												alt={c.title}
												className="content-image"
											/>
										)}
									</div>
									<div>
										<div className="post-metrics">
											<div><b>{fInt(c.views)}</b><span>Просмотры</span></div>
											<div><b>{fInt(c.reactions)}</b><span>Реакции</span></div>
											{c.kind !== 'story' && <div><b>{fInt(c.comments)}</b><span>Комм.</span></div>}
											{c.kind !== 'story' && <div><b>{fInt(c.reposts)}</b><span>Репосты</span></div>}
										</div>
										{c.insight && <div className="post-ins"><b>Вывод:</b> {c.insight}</div>}
									</div>
								</div>
							))}
						</div>
					)}
				</Panel>
			)}
		</>
	);
}

function Editor({ platformNames, PLIST, togglePlatform, current, previous, pf, setPf, tk, setTk, ct, setCt, biz, setBiz, summary, setSummary, plan, setPlan, community, setCommunity, wk, setWk, mn, setMn, num }) {
	const setStat = (p, k, v) => setPf((s) => ({ ...s, [p]: { ...s[p], [k]: v } }));
	const statKeys = ['subs', 'views', 'reach', 'inter', 'leads', 'posts', 'stories'];
	// колонки таблицы топ-контента: площадка, тип, заголовок, 4 метрики, вывод, картинка, удалить;
	// последние две — фиксированные, а fr-колонки через minmax(…), чтобы шапка и строки
	// (отдельные гриды) совпадали по колонкам пиксель в пиксель;
	// просмотрам гарантированы 96px под шестизначные числа
	// метрики и служебные колонки фиксированные (минимум под подпись/число),
	// всё освободившееся пространство уходит заголовку и выводу
	const CT_GRID = '112px 86px minmax(0,2fr) 112px 64px 54px 68px minmax(0,1.2fr) 180px 30px';
	// поля с переносом: высота по содержимому (field-sizing), остальные поля строки
	// растягиваются гридом до самого высокого из них
	const wrapArea = { resize: 'none', overflow: 'hidden', whiteSpace: 'pre-wrap', fontFamily: 'inherit', width: '100%', minWidth: 0, fieldSizing: 'content' };
	const planTasks = tk.filter(t => t.type !== 'check');
	const checkTasks = tk.filter(t => t.type === 'check');

	const uploadImage = async (index, file) => {
		if (!file) return;

		const formData = new FormData();
		formData.append('image', file);

		try {
			const { data } = await axios.post('/upload', formData, {
				headers: {
					'Content-Type': 'multipart/form-data',
				},
			});

			setCt(ct =>
				ct.map((item, i) =>
					i === index
						? {
							...item,
							image: data.path,
						}
						: item
				)
			);
		} catch (e) {
			alert('Ошибка загрузки изображения');
			console.error(e);
		}
	};

	// список записей площадки и его замена (остальные площадки не трогаем)
	const communityOf = (p) => community[p] || [];
	const setCommunityFor = (p, list) => setCommunity({ ...community, [p]: list });

	const uploadCommunityImage = async (platform, index, file) => {
		if (!file) return;

		const formData = new FormData();
		formData.append('image', file);

		try {
			const { data } = await axios.post('/upload', formData, {
				headers: { 'Content-Type': 'multipart/form-data' },
			});

			setCommunity((prev) => ({
				...prev,
				[platform]: (prev[platform] || []).map((item, i) => (i === index ? { ...item, image: data.path } : item)),
			}));
		} catch (e) {
			alert('Ошибка загрузки изображения');
			console.error(e);
		}
	};

	// вкладка площадки в понедельной статистике; если площадку выключили — уходим на первую включённую
	const [wkTab, setWkTab] = useState(null);
	const wkPlat = PLIST.includes(wkTab) ? wkTab : PLIST[0];

	const weekStats = {};

	PLIST.forEach(platform => {
		weekStats[platform] = wk.filter(w => w.platform === platform);
	});

	console.log('weeks', wk);

	return (
		<Panel eyebrow="Редактирование" title="Данные отчёта">
			<div className="platform-edit">
				{Object.keys(current).map((key) => (
					<label key={key} className={'platform-toggle' + (pf[key]?.is_enabled ? ' on' : '')}>
						<span className="switch">
							<input type="checkbox" checked={!!pf[key]?.is_enabled} onChange={() => togglePlatform(key)} />
							<i />
						</span>
						<span>{platformNames[key]}</span>
					</label>
				))}
			</div>
			<div style={{ fontSize: 12, color: 'var(--creamMut)', margin: '-6px 0 16px' }}>
				Выключенные площадки не показываются в отчёте и презентации. Выбор сохраняется вместе с отчётом.
			</div>

			<div className='two'>
				<div>
					<div className="edit-label" style={{ display: 'flex', justifyContent: 'space-between' }}>
						<span>Задачи (план / факт)</span>
						<button className="btn btn-mini" onClick={() => setTk([...tk, { title: '', plan: '', fact: '', status: 'выполнено', type: 'plan_fact' }])}><Plus size={13} /></button>
					</div>
					<div className="edit-grid" style={{ marginTop: 8 }}>
						{planTasks.map((t) => {
							const i = tk.findIndex(x => x === t);

							return (
								<div key={i} style={{ display: 'grid', gridTemplateColumns: '2fr .7fr .7fr 1.2fr auto', gap: 6 }}>
									<input className="ei ei-text" value={t.title} placeholder="Задача" onChange={(e) => setTk(tk.map((x, j) => j === i ? { ...x, title: e.target.value } : x))} />
									<input className="ei" value={t.plan} onChange={(e) => setTk(tk.map((x, j) => j === i ? { ...x, plan: e.target.value } : x))} />
									<input className="ei" value={t.fact} onChange={(e) => setTk(tk.map((x, j) => j === i ? { ...x, fact: e.target.value } : x))} />
									<input className="ei ei-text" value={t.status} onChange={(e) => setTk(tk.map((x, j) => j === i ? { ...x, status: e.target.value } : x))} />
									<button className="ei-x" onClick={() => setTk(tk.filter((_, j) => j !== i))}><Trash2 size={13} /></button>
								</div>
							);
						})}
					</div>
				</div>

				<div style={{ marginTop: 24 }}>
					<div
						className="edit-label"
						style={{ display: 'flex', justifyContent: 'space-between' }}
					>
						<span>Чек-лист</span>

						<button
							className="btn btn-mini"
							onClick={() =>
								setTk([
									...tk,
									{
										title: '',
										status: 'не выполнено',
										type: 'check',
									}
								])
							}
						>
							<Plus size={13} />
						</button>
					</div>

					<div className="edit-grid" style={{ marginTop: 8 }}>
						{checkTasks.map((t) => {
							const i = tk.findIndex(x => x === t);

							return (
								<div
									key={i}
									style={{
										display: 'grid',
										gridTemplateColumns: '30px 1fr auto',
										gap: 8,
										alignItems: 'center'
									}}
								>
									<input
										type="checkbox"
										checked={t.status === 'выполнено'}
										onChange={(e) =>
											setTk(
												tk.map((x, j) =>
													j === i
														? {
															...x,
															status: e.target.checked
																? 'выполнено'
																: 'не выполнено',
														}
														: x
												)
											)
										}
									/>

									<input
										className="ei ei-text"
										value={t.title}
										placeholder="Пункт чек-листа"
										onChange={(e) =>
											setTk(
												tk.map((x, j) =>
													j === i
														? { ...x, title: e.target.value }
														: x
												)
											)
										}
									/>

									<button
										className="ei-x"
										onClick={() => setTk(tk.filter((_, j) => j !== i))}
									>
										<Trash2 size={13} />
									</button>
								</div>
							);
						})}
					</div>
				</div>
			</div>

			<div className="edit-label" style={{ marginTop: 18 }}>Понедельная статистика</div>
			<div style={{ fontSize: 12, color: 'var(--creamMut)', margin: '4px 0 10px' }}>
				Внесите цифры из статистики сообщества за каждую неделю. Пустые поля можно оставить нулями.
			</div>
			<div className="wk-tabs">
				{PLIST.map((p) => (
					<button key={p} type="button" className={'tab' + (wkPlat === p ? ' on' : '')} onClick={() => setWkTab(p)}>{platformNames[p]}</button>
				))}
			</div>
			{wkPlat && (() => {
				const rows = weekStats[wkPlat] || [];
				const sum = (k) => rows.reduce((a, w) => a + (Number(w[k]) || 0), 0);
				// подписчики — общее число на конец недели: итог месяца — последняя заполненная неделя
				const lastSubs = [...rows].filter((w) => Number(w.subs)).sort((a, b) => (a.position || 0) - (b.position || 0)).pop();
				const subsTotal = lastSubs ? Number(lastSubs.subs) : 0;
				const erFmt = (inter, subs) => (Number(subs) ? (+((Number(inter) || 0) / Number(subs) * 100).toFixed(1)).toLocaleString('ru-RU') + '%' : '—');
				const setField = (w, key, val) => setWk(wk.map((item) => (item === w ? { ...item, [key]: val } : item)));
				return (
					<div className="wtable wk-table">
						<table>
							<thead>
								<tr>
									<th>Неделя</th>
									<th>Подписчики</th>
									<th>Просмотры</th>
									<th>Охваты</th>
									<th>Взаимо&shy;действия</th>
									<th>Заявки</th>
									<th>Посты</th>
									<th>Сторис</th>
									<th>ER % <em>авто</em></th>
								</tr>
							</thead>
							<tbody>
								{rows.map((w, wi) => (
									<tr key={w.id ?? `new-${wi}`}>
										<td><input className="ei ei-text wk-label" value={w.label} onChange={(e) => setField(w, 'label', e.target.value)} /></td>
										{statKeys.map((key) => (
											<td key={key}><input className="ei" type="number" value={w[key] ?? 0} onChange={(e) => setField(w, key, num(e))} /></td>
										))}
										<td><span className="er-pill">{erFmt(w.inter, w.subs)}</span></td>
									</tr>
								))}
							</tbody>
							<tfoot>
								<tr className="wk-total">
									<td>Итого за месяц</td>
									<td>{fInt(subsTotal)}</td>
									<td>{fInt(sum('views'))}</td>
									<td>{fInt(sum('reach'))}</td>
									<td>{fInt(sum('inter'))}</td>
									<td>{fInt(sum('leads'))}</td>
									<td>{fInt(sum('posts'))}</td>
									<td>{fInt(sum('stories'))}</td>
									<td>{erFmt(sum('inter'), subsTotal)}</td>
								</tr>
							</tfoot>
						</table>
					</div>
				);
			})()}
			<div style={{ height: 18 }} />

			<div className="edit-label">
				Показатели по площадкам (рассчитано по неделям)
			</div>

			<div className="wtable" style={{ marginBottom: 18 }}>
				<table>
					<thead>
						<tr>
							<th>Площадка</th>
							{statKeys.map((k) => (
								<th key={k}>{METR[k].label}</th>
							))}
						</tr>
					</thead>

					<tbody>
						{PLIST.map((p) => (
							<tr key={p}>
								<td style={{ fontWeight: 700 }}>
									{platformNames[p]}
								</td>

								{statKeys.map((k) => (
									<td key={k}>
										{pf[p]?.[k] ?? 0}
									</td>
								))}
							</tr>
						))}
					</tbody>
				</table>
			</div>

			<div className="edit-label" style={{ marginTop: 14 }}>Выводы по площадкам (текст для слайдов презентации)</div>
			<div className='platrorm-stat-wrapper'>
				{PLIST.map(platform => (
					<details key={platform} style={{ marginTop: 10 }}>
						<summary className="edit-label" style={{ cursor: 'pointer' }}>
							Выводы · {platformNames[platform]}
						</summary>
						<div style={{ marginTop: 12, display: 'grid', gap: 14 }}>
							{[['subs', 'Подписчики'], ['views', 'Просмотры'], ['inter', 'Взаимодействия']].map(([mk, label]) => {
								const list = mn[platform]?.[mk] || [];
								const setList = (nl) => setMn({ ...mn, [platform]: { ...(mn[platform] || {}), [mk]: nl } });
								// динамика к прошлому месяцу — подсказка при написании вывода
								const prevVal = previous?.stats?.[platform]?.[mk];
								const diff = prevVal === null || prevVal === undefined ? null : (Number(pf[platform]?.[mk]) || 0) - Number(prevVal);
								return (
									<div key={mk}>
										<div className="edit-label" style={{ fontSize: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
											<span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>{label} <Delta diff={diff} /></span>
											<button className="btn btn-mini" onClick={() => setList([...list, ''])}><Plus size={13} /></button>
										</div>
										<div className="edit-grid" style={{ marginTop: 6 }}>
											{list.map((v, i) => (
												<div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: 6 }}>
													<input className="ei ei-text" value={v} placeholder="Текст вывода для презентации" onChange={(e) => setList(list.map((x, j) => j === i ? e.target.value : x))} />
													<button className="ei-x" onClick={() => setList(list.filter((_, j) => j !== i))}><Trash2 size={13} /></button>
												</div>
											))}
										</div>
									</div>
								);
							})}
						</div>
					</details>
				))}
			</div>

			<div className="edit-label" style={{ marginTop: 14 }}>Работа с сообществом (по площадкам)</div>
			<div className="platrorm-stat-wrapper">
				{PLIST.map((p) => (
					<details key={p} style={{ marginTop: 10 }} open={communityOf(p).length > 0}>
						<summary className="edit-label" style={{ cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
							<span>Работа с сообществом · {platformNames[p]}{communityOf(p).length ? ` (${communityOf(p).length})` : ''}</span>
							<button className="btn btn-mini" onClick={(e) => { e.preventDefault(); setCommunityFor(p, [...communityOf(p), { image: null, caption: '' }]); }}><Plus size={13} /></button>
						</summary>
						<div className="edit-grid" style={{ marginTop: 8 }}>
							{communityOf(p).map((c, i) => (
								<div key={i} style={{ display: 'grid', gridTemplateColumns: 'auto auto 1fr auto', gap: 6, alignItems: 'center' }}>
									<input
										id={`comm-file-${p}-${i}`}
										type="file"
										accept="image/*"
										onChange={(e) => uploadCommunityImage(p, i, e.target.files[0])}
										style={{ display: 'none' }}
									/>

									<label
										htmlFor={`comm-file-${p}-${i}`}
										style={{
											display: 'inline-flex',
											alignItems: 'center',
											gap: 8,
											padding: '10px 16px',
											background: '#f0a29b24',
											color: 'var(--redL)',
											borderRadius: 8,
											cursor: 'pointer',
											fontSize: 14,
											fontWeight: 500,
											transition: 'background .2s'
										}}
									>
										{c.image ? 'Заменить изображение' : 'Выбрать изображение'}
									</label>

									{c.image ? (
										<img
											src={`/storage/${c.image}`}
											style={{ width: 60, height: 60, objectFit: 'cover', borderRadius: 6 }}
										/>
									) : <span />}

									<input className="ei ei-text" value={c.caption || ''} placeholder="Подпись к изображению" onChange={(e) => setCommunityFor(p, communityOf(p).map((x, j) => j === i ? { ...x, caption: e.target.value } : x))} />

									<button className="ei-x" onClick={() => setCommunityFor(p, communityOf(p).filter((_, j) => j !== i))}><Trash2 size={13} /></button>
								</div>
							))}
							{communityOf(p).length === 0 && <div style={{ fontSize: 12, color: 'var(--mut)' }}>Записей нет — добавьте кнопкой «+»</div>}
						</div>
					</details>
				))}
			</div>

			<div className="two" style={{ marginTop: 18 }}>
				<div>
					<div className="edit-label">Результаты для бизнеса</div>
					<div className="edit-grid" style={{ marginTop: 8 }}>
						{[['ad_clicks', 'Переходов с рекламы'], ['ad_subs', 'Подписчики с рекламы'], ['ad_views', 'Просмотры с рекламы'], ['ad_budget', 'Бюджет размещения, ₽']].map(([k, lbl]) => (
							<div key={k} style={{ display: 'grid', gridTemplateColumns: '1fr .8fr', gap: 6, alignItems: 'center' }}>
								<span style={{ fontSize: 13 }}>{lbl}</span>
								<input className="ei" value={biz[k] ?? ''} onChange={(e) => setBiz({ ...biz, [k]: e.target.value })} />
							</div>
						))}
					</div>
				</div>
			</div>

			<div className="edit-label" style={{ marginTop: 18, display: 'flex', justifyContent: 'space-between' }}>
				<span>Топ-контент (посты / сторис)</span>
				<button className="btn btn-mini" onClick={() => setCt([...ct, { platform: 'vk', kind: 'post', title: '', views: 0, reactions: 0, comments: 0, reposts: 0, insight: '' }])}><Plus size={13} /></button>
			</div>
			<div className="edit-grid" style={{ marginTop: 8 }}>
				{ct.length > 0 && (
					<div style={{ display: 'grid', gridTemplateColumns: CT_GRID, gap: 6, fontSize: 11, fontWeight: 700, color: 'var(--mut)', textTransform: 'uppercase', letterSpacing: '.04em', textAlign: 'center' }}>
						<span>Площадка</span>
						<span>Тип</span>
						<span>Заголовок</span>
						<span>Просмотры</span>
						<span>Реакции</span>
						<span>Комм.</span>
						<span>Репосты</span>
						<span>Вывод</span>
						<span />
						<span />
					</div>
				)}
				{ct.map((c, i) => (
					<div key={i} style={{ display: 'grid', gridTemplateColumns: CT_GRID, gap: 6, alignItems: 'stretch' }}>
						<select className="ei ei-text" style={{ minWidth: 0, width: '100%' }} value={c.platform} onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, platform: e.target.value } : x))}>
							{PLIST.map((p) => <option key={p} value={p}>{platformNames[p]}</option>)}
						</select>
						<select className="ei ei-text" style={{ minWidth: 0, width: '100%' }} value={c.kind === 'story' ? 'story' : 'post'} onChange={(e) => setCt(ct.map((x, j) => j === i ? (e.target.value === 'story' ? { ...x, kind: 'story', comments: 0, reposts: 0 } : { ...x, kind: 'post' }) : x))}>
							<option value="post">Пост</option>
							<option value="story">Сторис</option>
						</select>
						<textarea
							className="ei ei-text"
							value={c.title}
							placeholder="Заголовок"
							rows={1}
							style={{ ...wrapArea }}
							onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, title: e.target.value } : x))}
						/>
						<input className="ei" style={{ minWidth: 0, width: '100%' }} type="number" value={c.views} onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, views: num(e) } : x))} />
						<input className="ei" style={{ minWidth: 0, width: '100%' }} type="number" value={c.reactions} onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, reactions: num(e) } : x))} />
						<input className="ei" style={{ minWidth: 0, width: '100%' }} type="number" value={c.kind === 'story' ? 0 : (c.comments ?? 0)} disabled={c.kind === 'story'} onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, comments: num(e) } : x))} />
						<input className="ei" style={{ minWidth: 0, width: '100%' }} type="number" value={c.kind === 'story' ? 0 : (c.reposts ?? 0)} disabled={c.kind === 'story'} onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, reposts: num(e) } : x))} />
						<textarea
							className="ei ei-text"
							value={c.insight || ''}
							placeholder="Вывод"
							rows={1}
							style={{ ...wrapArea }}
							onChange={(e) => setCt(ct.map((x, j) => j === i ? { ...x, insight: e.target.value } : x))}
						/>
						<div style={{
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							gap: 10,
							alignSelf: 'center',
							minWidth: 0
						}}>
							<input
								id={`file-${i}`}
								type="file"
								accept="image/*"
								onChange={(e) => uploadImage(i, e.target.files[0])}
								style={{ display: 'none' }}
							/>

							<label
								htmlFor={`file-${i}`}
								style={{
									display: 'inline-flex',
									alignItems: 'center',
									gap: 8,
									padding: '8px 14px',
									background: '#f0a29b24',
									color: 'var(--redL)',
									borderRadius: 8,
									cursor: 'pointer',
									fontSize: 13,
									fontWeight: 500,
									whiteSpace: 'nowrap',
									transition: 'background .2s'
								}}
							>
								{c.image ? 'Заменить' : 'Изображение'}
							</label>

							{c.image && (
								<img
									src={`/storage/${c.image}`}
									style={{
										width: 38,
										height: 38,
										objectFit: 'cover',
										borderRadius: 6,
										flexShrink: 0
									}}
								/>
							)}
						</div>
						<button className="ei-x" style={{ alignSelf: 'center' }} onClick={() => setCt(ct.filter((_, j) => j !== i))}><Trash2 size={13} /></button>
					</div>
				))}
			</div>

			<div className="two" style={{ marginTop: 18 }}>
				<div>
					<div className="edit-label">Выводы месяца</div>
					<textarea className="summary-inp" rows={4} value={summary} onChange={(e) => setSummary(e.target.value)} style={{ marginTop: 8 }} />
				</div>
				<div>
					<div className="edit-label">План на следующий месяц (каждый пункт с новой строки)</div>
					<textarea className="summary-inp" rows={4} value={plan} onChange={(e) => setPlan(e.target.value)} style={{ marginTop: 8 }} />
				</div>
			</div>
		</Panel>
	);
}
