import React, { useState } from 'react';
import { Link, useForm, router } from '@inertiajs/react';
import { Plus, CalendarDays, Trash2 } from 'lucide-react';
import Layout from '../../Layout';
import { fInt, fSigned, fPct } from '../../lib/ui';

const MONTHS = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
	'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

export default function Show({ project, reports }) {
	const now = new Date();
	const [open, setOpen] = useState(false);

	const [editing, setEditing] = useState(false);

	const edit = useForm({
		name: project.name,
		color: project.color,
		client_period: project.client_period ?? '',
		manager: project.manager ?? '',
		vk_group: project.vk_group ?? '',
		vk_token: '',
		vk_token_remove: false,
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
					<button className="btn btn-primary" onClick={() => setOpen((v) => !v)}>
						<Plus size={16} /> Новый отчёт (месяц)
					</button>
					<button className="btn btn-danger" onClick={removeProject}>
						<Trash2 size={16} /> Удалить проект
					</button>
				</div>
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
						<span className="card-title">Ответственный</span>

						{editing ? (
							<input
								className="inp"
								value={edit.data.manager}
								onChange={(e) =>
									edit.setData('manager', e.target.value)
								}
							/>
						) : (
							<span>{project.manager || '—'}</span>
						)}
					</div>

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

				</div>
			</div>

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
					<p>Создайте отчёт за месяц, чтобы вносить понедельную статистику.</p>
				</div>
			) : (
				<div className="grid-cards">
					{reports.map((r) => (
						<Link key={r.id} href={`/reports/${r.id}`} className="card">
							<div className="card-accent" style={{ background: project.color }} />
							<div className="card-title">{r.period_label}</div>
							<div className="card-meta">Понедельный отчёт</div>
							<div className="card-stats">
								<div className="card-stat">
									<b>{fSigned(r.totals.subscribers)}</b>
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
