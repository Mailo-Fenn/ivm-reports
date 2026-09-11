import React, { useState } from 'react';
import { Link, useForm, router } from '@inertiajs/react';
import { Plus, CalendarDays, Trash2, Send, Copy, ExternalLink, X, CalendarClock } from 'lucide-react';
import Layout from '../../Layout';
import { fInt, fSigned, fPct } from '../../lib/ui';

const MONTHS = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
	'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

export default function Show({ project, reports, tariffs = {}, share, employees = [] }) {
	// страница открыта по ссылке для клиента: без правки, интеграций и кнопок
	const ro = !!share;
	const reportHref = (id) => (ro ? `/share/${share.token}/reports/${id}` : `/reports/${id}`);
	const [copied, setCopied] = useState(false);
	const [shareOpen, setShareOpen] = useState(false);
	// «Отправить клиенту»: если ссылки ещё нет — выпускаем и сразу открываем окно с ней
	const openShare = () => {
		if (project.share_url) { setShareOpen(true); return; }
		router.post(`/projects/${project.id}/share`, {}, { preserveScroll: true, onSuccess: () => setShareOpen(true) });
	};
	const copyShare = async () => {
		try { await navigator.clipboard.writeText(project.share_url); setCopied(true); setTimeout(() => setCopied(false), 1800); } catch { /* браузер без clipboard — поле можно выделить и скопировать вручную */ }
	};
	const now = new Date();
	const [open, setOpen] = useState(false);

	const [editing, setEditing] = useState(false);

	const edit = useForm({
		name: project.name,
		color: project.color,
		client_period: project.client_period ?? '',
		manager: project.manager ?? '',
		tariff: project.tariff ?? 'start',
		vk_group: project.vk_group ?? '',
		vk_token: '',
		vk_token_remove: false,
		youtube_channel: project.youtube_channel ?? '',
		telegram_channel: project.telegram_channel ?? '',
		is_active: project.is_active,
	});

	const { data, setData, post, processing } = useForm({
		month: now.getMonth() + 1,
		year: now.getFullYear(),
	});

	const submit = () => {
		post(`/projects/${project.id}/reports`, { onSuccess: () => setOpen(false) });
	};

	const saveProject = () => {
		edit.put(`/projects/${project.id}`, {
			onSuccess: () => setEditing(false),
		});
	};

	const removeProject = () => {
		if (confirm(`Удалить проект «${project.name}» со всеми отчётами?`)) {
			router.delete(`/projects/${project.id}`);
		}
	};

	return (
		<Layout crumbs={[{ label: project.name }]}>
			<div className="page-head">
				<div>
					<div className="eyebrow" style={{ color: edit.data.color }}>
						Проект
					</div>

					{editing ? (
						<input
							className="inp"
							value={edit.data.name}
							onChange={(e) => edit.setData('name', e.target.value)}
						/>
					) : (
						<h1 className="page-title">{project.name}</h1>
					)}
				</div>
				{!ro && (
				<div className="page-actions">
					{editing ? (
						<>
							<button className="btn btn-primary" onClick={saveProject}>
								Сохранить
							</button>

							<button
								className="btn"
								onClick={() => setEditing(false)}
							>
								Отмена
							</button>
						</>
					) : (
						<button
							className="btn"
							onClick={() => setEditing(true)}
						>
							Редактировать
						</button>
					)}
					<Link className="btn" href={`/projects/${project.id}/posts`}>
						<CalendarClock size={16} /> Контент-план
					</Link>
					<button className="btn btn-accent" onClick={openShare}>
						<Send size={16} /> Отправить клиенту
					</button>
					<button className="btn btn-primary" onClick={() => setOpen((v) => !v)}>
						<Plus size={16} /> Новый отчёт (месяц)
					</button>
					<button className="btn btn-danger" onClick={removeProject}>
						<Trash2 size={16} /> Удалить проект
					</button>
				</div>
				)}
			</div>

			<div className="project-info card">
				<div className="project-info-grid">

					<div className="info-item">
						<span className="card-title">Период ведения</span>

						{editing ? (
							<input
								className="inp"
								value={edit.data.client_period}
								onChange={(e) =>
									edit.setData('client_period', e.target.value)
								}
							/>
						) : (
							<span>{project.client_period || '—'}</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Тариф</span>

						{editing ? (
							<select
								className="inp"
								value={edit.data.tariff}
								onChange={(e) => edit.setData('tariff', e.target.value)}
							>
								{Object.entries(tariffs).map(([key, name]) => <option key={key} value={key}>{name}</option>)}
							</select>
						) : (
							<span>{tariffs[project.tariff] || '—'}</span>
						)}
						{editing && (
							<span style={{ fontSize: 12, color: 'var(--mut)' }}>задаёт план постов, сторис и рекламы и чек-лист в новых отчётах</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Ответственный</span>

						{editing ? (
							<>
								<input
									className="inp"
									list="employee-names"
									placeholder="Имя сотрудника"
									value={edit.data.manager}
									onChange={(e) =>
										edit.setData('manager', e.target.value)
									}
								/>
								<datalist id="employee-names">
									{employees.map((n) => <option key={n} value={n} />)}
								</datalist>
								<span style={{ fontSize: 12, color: 'var(--mut)' }}>совпадение с именем в разделе «Сотрудники» добавит проект в его карточку</span>
							</>
						) : (
							<span>{project.manager || '—'}</span>
						)}
					</div>

					{!ro && (<>
					<div className="info-item">
						<span className="card-title">Сообщество ВК</span>

						{editing ? (
							<input
								className="inp"
								placeholder="ID, короткое имя или ссылка"
								value={edit.data.vk_group}
								onChange={(e) =>
									edit.setData('vk_group', e.target.value)
								}
							/>
						) : (
							<span>{project.vk_group || '—'}</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Ключ доступа сообщества ВК</span>

						{editing ? (
							<>
								<input
									className="inp"
									type="password"
									placeholder={project.has_vk_token ? 'Установлен — вставьте новый для замены' : 'Вставьте ключ (опционально)'}
									value={edit.data.vk_token}
									onChange={(e) =>
										edit.setData('vk_token', e.target.value)
									}
								/>
								{project.has_vk_token && (
									<label className="switch-row" style={{ marginTop: 6, fontSize: 12 }}>
										<input
											type="checkbox"
											checked={edit.data.vk_token_remove}
											onChange={(e) =>
												edit.setData('vk_token_remove', e.target.checked)
											}
										/>
										Удалить сохранённый ключ
									</label>
								)}
							</>
						) : (
							<span className={project.has_vk_token ? 'status on' : ''}>
								{project.has_vk_token ? '● Установлен' : '—'}
							</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Канал YouTube</span>

						{editing ? (
							<input
								className="inp"
								placeholder="@handle, ID канала или ссылка"
								value={edit.data.youtube_channel}
								onChange={(e) =>
									edit.setData('youtube_channel', e.target.value)
								}
							/>
						) : (
							<span>{project.youtube_channel || '—'}</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Канал Telegram</span>

						{editing ? (
							<input
								className="inp"
								placeholder="@имя канала или ссылка t.me"
								value={edit.data.telegram_channel}
								onChange={(e) =>
									edit.setData('telegram_channel', e.target.value)
								}
							/>
						) : (
							<span>{project.telegram_channel || '—'}</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Instagram</span>

						{project.instagram_connected ? (
							<>
								<span className="status on">● @{project.instagram_username}</span>
								<span style={{ fontSize: 12, color: 'var(--mut)' }}>токен до {project.instagram_expires_at}, продлевается автоматически</span>
								<div className="form-row" style={{ marginTop: 6, gap: 6 }}>
									<a className="btn btn-mini" href={`/projects/${project.id}/instagram/connect`}>Переподключить</a>
									<button
										className="btn btn-mini btn-danger"
										onClick={() => { if (confirm('Отключить Instagram от проекта?')) router.post(`/projects/${project.id}/instagram/disconnect`); }}
									>
										Отключить
									</button>
								</div>
							</>
						) : project.instagram_configured ? (
							<>
								<span>—</span>
								<a className="btn btn-mini" style={{ marginTop: 6, alignSelf: 'flex-start' }} href={`/projects/${project.id}/instagram/connect`}>
									Подключить Instagram
								</a>
								<span style={{ fontSize: 12, color: 'var(--mut)' }}>откроется вход в Instagram — войдите в аккаунт клиента</span>
							</>
						) : (
							<span style={{ fontSize: 12, color: 'var(--mut)' }}>сначала укажите App ID и App Secret приложения Meta в Настройках</span>
						)}
					</div>

					<div className="info-item">
						<span className="card-title">Статус</span>

						{editing ? (
							<label className="switch-row">
								<input
									type="checkbox"
									checked={edit.data.is_active}
									onChange={(e) =>
										edit.setData('is_active', e.target.checked)
									}
								/>
								Активный
							</label>
						) : (
							<span className={project.is_active ? 'status on' : 'status off'}>
								{project.is_active ? '● Активный' : '● Архивный'}
							</span>
						)}
					</div>

					</>)}
				</div>
			</div>

			{!ro && shareOpen && project.share_url && (
				<div className="modal-bg" onClick={() => setShareOpen(false)}>
					<div className="modal" onClick={(e) => e.stopPropagation()}>
						<div className="modal-head">
							<div>
								<div className="eyebrow">Клиенту</div>
								<h2 className="modal-title">Ссылка на отчёты</h2>
							</div>
							<button className="ei-x" onClick={() => setShareOpen(false)}><X size={15} /></button>
						</div>
						<p style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 12px' }}>
							Клиент открывает проект и все отчёты без входа, только для просмотра: без редактирования,
							выгрузки презентации и подтягивания соцсетей. Ссылка действует, пока вы её не отключите.
						</p>
						<div className="form-row">
							<input className="inp" readOnly value={project.share_url} style={{ flex: 1, minWidth: 240 }} onFocus={(e) => e.target.select()} />
							<button className="btn btn-primary" onClick={copyShare}><Copy size={15} /> {copied ? 'Скопировано' : 'Скопировать'}</button>
							<a className="btn" href={project.share_url} target="_blank" rel="noreferrer"><ExternalLink size={15} /> Открыть</a>
						</div>
						<div className="form-row" style={{ marginTop: 14, justifyContent: 'space-between' }}>
							<span style={{ fontSize: 12, color: 'var(--mut)' }}>Перевыпуск делает старую ссылку недействительной.</span>
							<span style={{ display: 'flex', gap: 6 }}>
								<button className="btn btn-mini" onClick={() => { if (confirm('Перевыпустить ссылку? Старая перестанет работать.')) router.post(`/projects/${project.id}/share`, {}, { preserveScroll: true }); }}>Перевыпустить</button>
								<button className="btn btn-mini btn-danger" onClick={() => { if (confirm('Отключить ссылку для клиента?')) router.delete(`/projects/${project.id}/share`, { preserveScroll: true, onSuccess: () => setShareOpen(false) }); }}>Отключить</button>
							</span>
						</div>
					</div>
				</div>
			)}

			{open && (
				<div className="newform">
					<h3>Новый месячный отчёт</h3>
					<div className="form-row">
						<select className="inp" value={data.month} onChange={(e) => setData('month', +e.target.value)}>
							{MONTHS.slice(1).map((m, i) => (
								<option key={i} value={i + 1}>{m}</option>
							))}
						</select>
						<input
							className="inp"
							type="number"
							style={{ width: 110 }}
							value={data.year}
							onChange={(e) => setData('year', +e.target.value)}
						/>
						<button className="btn btn-primary" onClick={submit} disabled={processing}>Создать</button>
						<button className="btn" onClick={() => setOpen(false)}>Отмена</button>
					</div>
					<p className="field-err" style={{ color: 'var(--muted)' }}>
						В новый отчёт добавятся 4 пустые недели — заполните статистику внутри.
					</p>
				</div>
			)}

			{reports.length === 0 ? (
				<div className="empty">
					<CalendarDays size={40} strokeWidth={1.5} style={{ opacity: .4, marginBottom: 12 }} />
					<h3>Ещё нет отчётов</h3>
					<p>{ro ? 'Отчёты появятся здесь, как только будут готовы.' : 'Создайте отчёт за месяц, чтобы вносить понедельную статистику.'}</p>
				</div>
			) : (
				<div className="grid-cards">
					{reports.map((r) => (
						<Link key={r.id} href={reportHref(r.id)} className="card">
							<div className="card-accent" style={{ background: project.color }} />
							<div className="card-title">{r.period_label}</div>
							<div className="card-meta">Понедельный отчёт</div>
							<div className="card-stats">
								<div className="card-stat">
									<b>{fInt(r.totals.subs)}</b>
									<span>Подписчики</span>
								</div>
								<div className="card-stat">
									<b>{fInt(r.totals.reach)}</b>
									<span>Охваты</span>
								</div>
								<div className="card-stat">
									<b>{fInt(r.totals.leads)}</b>
									<span>Заявки</span>
								</div>
								<div className="card-stat">
									<b>{fPct(r.totals.er)}</b>
									<span>ER</span>
								</div>
							</div>
						</Link>
					))}
				</div>
			)}
		</Layout>
	);
}
