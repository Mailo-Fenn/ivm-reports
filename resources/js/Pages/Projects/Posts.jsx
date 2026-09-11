import axios from 'axios';
import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Send, CalendarClock, Save, X, Upload, RefreshCw, ExternalLink, Image as ImageIcon, Film } from 'lucide-react';
import Layout from '../../Layout';

const STATUS = {
	draft: ['Черновик', 'st-draft'],
	scheduled: ['Запланировано', 'st-sched'],
	publishing: ['Публикуется…', 'st-sched'],
	published: ['Опубликовано', 'st-ok'],
	partial: ['Частично', 'st-warn'],
	failed: ['Ошибка', 'st-err'],
};

// локальное время «сейчас + час», округлённое до 5 минут — удобное значение по умолчанию
const defaultTime = () => {
	const d = new Date(Date.now() + 60 * 60 * 1000);
	d.setMinutes(Math.ceil(d.getMinutes() / 5) * 5, 0, 0);
	const p = (n) => String(n).padStart(2, '0');
	return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};

const uploadMedia = async (file) => {
	const fd = new FormData();
	fd.append('file', file);
	const { data } = await axios.post('/upload-media', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
	return data;
};

function MediaThumb({ m, size = 96, onRemove }) {
	return (
		<div className="post-thumb" style={{ width: size, height: size }}>
			{m.type === 'video'
				? <video src={m.url} muted playsInline preload="metadata" />
				: <img src={m.url} alt={m.name} />}
			<span className="post-thumb-kind">{m.type === 'video' ? <Film size={12} /> : <ImageIcon size={12} />}</span>
			{onRemove && <button className="post-thumb-x" onClick={onRemove} title="Убрать"><X size={12} /></button>}
		</div>
	);
}

function PostForm({ initial, platformNames, availability, onClose, busy, setBusy, projectId }) {
	const [text, setText] = useState(initial?.text || '');
	const [media, setMedia] = useState(initial?.media || []);
	const [platforms, setPlatforms] = useState(initial?.platforms || Object.keys(availability).filter((k) => !availability[k]));
	const [when, setWhen] = useState(initial?.scheduled_at || defaultTime());
	const [uploading, setUploading] = useState(0);

	const toggle = (p) => setPlatforms((list) => (list.includes(p) ? list.filter((x) => x !== p) : [...list, p]));
	const pick = async (files) => {
		const list = [...files].slice(0, 10 - media.length);
		if (!list.length) return;
		setUploading((n) => n + list.length);
		for (const f of list) {
			try {
				const m = await uploadMedia(f);
				setMedia((cur) => [...cur, m]);
			} catch (e) {
				alert(`Не удалось загрузить «${f.name}»: ` + (e.response?.data?.message || 'слишком большой файл или неподдерживаемый формат'));
			} finally {
				setUploading((n) => n - 1);
			}
		}
	};

	const submit = (action) => {
		const payload = { text, media: media.map(({ path, type, name }) => ({ path, type, name })), platforms, scheduled_at: action === 'draft' ? (when || null) : when, action };
		const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onClose };
		initial?.id ? router.put(`/posts/${initial.id}`, payload, opts) : router.post(`/projects/${projectId}/posts`, payload, opts);
	};

	const canSend = (text.trim() || media.length) && !uploading;

	return (
		<div className="newform post-form">
			<h3>{initial?.id ? 'Публикация' : 'Новая публикация'}</h3>
			<textarea className="inp post-text" rows={5} placeholder="Текст публикации" value={text} onChange={(e) => setText(e.target.value)} />

			<div className="post-media">
				{media.map((m, i) => <MediaThumb key={m.path} m={m} onRemove={() => setMedia(media.filter((_, j) => j !== i))} />)}
				<label className="post-add">
					<input type="file" accept="image/*,video/mp4,video/quicktime,video/x-m4v" multiple style={{ display: 'none' }} onChange={(e) => { pick(e.target.files); e.target.value = ''; }} />
					<Upload size={18} />
					<span>{uploading ? `Загрузка… (${uploading})` : 'Фото или видео'}</span>
				</label>
			</div>
			<div className="post-hint">До 10 файлов: JPG, PNG, WebP, GIF, MP4, MOV. Видео для Telegram до 50 МБ.</div>

			<div className="post-platforms">
				{Object.entries(platformNames).map(([key, name]) => {
					const why = availability[key];
					return (
						<label key={key} className={'platform-pill' + (platforms.includes(key) ? ' on' : '') + (why ? ' off' : '')} title={why || ''}>
							<input type="checkbox" disabled={!!why} checked={platforms.includes(key)} onChange={() => toggle(key)} />
							{name}{why ? ' · недоступно' : ''}
						</label>
					);
				})}
			</div>

			<div className="form-row" style={{ marginTop: 12 }}>
				<label className="post-when"><CalendarClock size={15} /> <input className="inp" type="datetime-local" value={when} onChange={(e) => setWhen(e.target.value)} /></label>
				<button className="btn btn-primary" disabled={busy || !canSend || !platforms.length || !when} onClick={() => submit('schedule')}><CalendarClock size={15} /> Запланировать</button>
				<button className="btn btn-accent" disabled={busy || !canSend || !platforms.length} onClick={() => { if (confirm('Опубликовать прямо сейчас во все выбранные площадки?')) submit('now'); }}><Send size={15} /> Опубликовать сейчас</button>
				<button className="btn" disabled={busy || !canSend} onClick={() => submit('draft')}><Save size={15} /> Черновик</button>
				<button className="btn" onClick={onClose}><X size={15} /> Отмена</button>
			</div>
		</div>
	);
}

export default function Posts({ project, posts, platformNames, availability }) {
	const [adding, setAdding] = useState(false);
	const [editingId, setEditingId] = useState(null);
	const [busy, setBusy] = useState(false);

	const publishNow = (p) => { if (confirm('Опубликовать сейчас во все выбранные площадки?')) router.post(`/posts/${p.id}/publish`, {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) }); };
	const remove = (p) => { if (confirm('Удалить публикацию? Уже вышедшие посты в соцсетях останутся.')) router.delete(`/posts/${p.id}`, { preserveScroll: true }); };

	const unavailable = Object.entries(availability).filter(([, why]) => why);

	return (
		<Layout crumbs={[{ label: project.name, href: `/projects/${project.id}` }, { label: 'Контент-план' }]}>
			<div className="page-head">
				<div>
					<div className="eyebrow">Проект · {project.name}</div>
					<h1 className="page-title">Контент-план</h1>
				</div>
				<div className="page-actions">
					<button className="btn btn-primary" onClick={() => { setEditingId(null); setAdding((v) => !v); }}><Plus size={16} /> Новая публикация</button>
				</div>
			</div>

			{unavailable.length > 0 && (
				<div className="post-notice">
					{unavailable.map(([k, why]) => <div key={k}><b>{platformNames[k]}</b>: {why}</div>)}
				</div>
			)}

			{adding && <PostForm platformNames={platformNames} availability={availability} projectId={project.id} onClose={() => setAdding(false)} busy={busy} setBusy={setBusy} />}

			{posts.length === 0 && !adding ? (
				<div className="empty">
					<CalendarClock size={40} strokeWidth={1.5} style={{ opacity: .4, marginBottom: 12 }} />
					<h3>Публикаций пока нет</h3>
					<p>Добавьте текст и фото или видео, выберите площадки и время — портал опубликует сам.</p>
				</div>
			) : (
				<div className="post-list">
					{posts.map((p) => editingId === p.id ? (
						<PostForm key={p.id} initial={p} platformNames={platformNames} availability={availability} projectId={project.id} onClose={() => setEditingId(null)} busy={busy} setBusy={setBusy} />
					) : (
						<div key={p.id} className="card post-card">
							<div className="post-card-head">
								<span className={'post-status ' + STATUS[p.status]?.[1]}>{STATUS[p.status]?.[0] || p.status}</span>
								<span className="post-date">{p.scheduled_label || 'без даты'}</span>
								<span className="post-actions">
									{p.status !== 'published' && <button className="btn btn-mini" title="Редактировать" onClick={() => { setAdding(false); setEditingId(p.id); }}><Pencil size={13} /></button>}
									{p.status !== 'published' && p.platforms.length > 0 && (
										<button className="btn btn-mini" title={['failed', 'partial'].includes(p.status) ? 'Повторить неудавшиеся' : 'Опубликовать сейчас'} disabled={busy} onClick={() => publishNow(p)}>
											{['failed', 'partial'].includes(p.status) ? <RefreshCw size={13} /> : <Send size={13} />}
										</button>
									)}
									<button className="btn btn-mini btn-danger" title="Удалить" onClick={() => remove(p)}><Trash2 size={13} /></button>
								</span>
							</div>
							<div className="post-body">
								{p.media.length > 0 && <div className="post-media">{p.media.map((m) => <MediaThumb key={m.path} m={m} size={84} />)}</div>}
								{p.text ? <p className="post-text-view">{p.text}</p> : <p className="post-text-view" style={{ color: 'var(--mut)' }}>Без текста</p>}
							</div>
							<div className="post-pubs">
								{p.platforms.map((k) => {
									const pub = p.publications.find((x) => x.platform === k);
									const st = pub?.status || 'pending';
									return (
										<span key={k} className={'post-pub ' + st} title={pub?.error || (pub?.published_at ? `Опубликовано ${pub.published_at}` : 'Ожидает публикации')}>
											{platformNames[k]}
											{st === 'published' && pub.url && <a href={pub.url} target="_blank" rel="noreferrer" title="Открыть пост"><ExternalLink size={12} /></a>}
											{st === 'failed' && <em> · {pub.error}</em>}
										</span>
									);
								})}
							</div>
						</div>
					))}
				</div>
			)}
		</Layout>
	);
}
