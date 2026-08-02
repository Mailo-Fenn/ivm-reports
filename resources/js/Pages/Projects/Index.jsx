import React, { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Plus, FolderOpen } from 'lucide-react';
import Layout from '../../Layout';
import { fInt, fPct } from '../../lib/ui';

export default function Index({ projects }) {
	const [open, setOpen] = useState(false);
	const [tab, setTab] = useState('active');
	const { data, setData, post, processing, errors, reset } = useForm({
		name: '',
		color: '#6C4CF0',
	});

	const submit = () => {
		post('/projects', {
			onSuccess: () => {
				reset();
				setOpen(false);
			},
		});
	};

	const filteredProjects = projects.filter((p) =>
	tab === 'active'
		? p.is_active
		: !p.is_active
	);

	return (
		<Layout>
			<div className="page-head">
				<div>
					<div className="eyebrow">Клиенты агентства</div>
					<h1 className="page-title">Проекты</h1>
				</div>
				<div className="page-actions">
					<button className="btn btn-primary" onClick={() => setOpen((v) => !v)}>
						<Plus size={16} /> Новый проект
					</button>
				</div>
			</div>

			{open && (
				<div className="newform">
					<h3>Новый проект</h3>
					<div className="form-row">
						<input
							className="inp"
							style={{ flex: 1, minWidth: 220 }}
							placeholder="Название клиента"
							value={data.name}
							autoFocus
							onChange={(e) => setData('name', e.target.value)}
							onKeyDown={(e) => e.key === 'Enter' && data.name && submit()}
						/>
						<input
							className="color"
							type="color"
							value={data.color}
							onChange={(e) => setData('color', e.target.value)}
							title="Фирменный цвет"
						/>
						<button className="btn btn-primary" onClick={submit} disabled={processing || !data.name}>
							Создать
						</button>
						<button className="btn" onClick={() => setOpen(false)}>Отмена</button>
					</div>
					{errors.name && <div className="field-err">{errors.name}</div>}
				</div>
			)}

			<div className="tabs project-tabs">
				<button
					className={'tab' + (tab === 'active' ? ' on' : '')}
					onClick={() => setTab('active')}
				>
					Активные
				</button>

				<button
					className={'tab' + (tab === 'archive' ? ' on' : '')}
					onClick={() => setTab('archive')}
				>
					Архив
				</button>
			</div>

			{filteredProjects.length === 0 ? (
				<div className="empty">
					<FolderOpen size={40} strokeWidth={1.5} style={{ opacity: .4, marginBottom: 12 }} />
					<h3>Пока нет проектов</h3>
					<p>Создайте первый проект — и добавляйте в него месячные отчёты.</p>
				</div>
			) : (
				<div className="grid-cards">
					{filteredProjects.map((p) => (
						<Link key={p.id} href={`/projects/${p.id}`} className="card">
							<div className="card-accent" style={{ background: p.color }} />
							<div className="card-title">{p.name}</div>
							<div className="card-meta">
								{p.reports_count} {plural(p.reports_count, 'отчёт', 'отчёта', 'отчётов')}
								{p.last_period ? ` · последний: ${p.last_period}` : ''}
							</div>
							{p.last_totals && (
								<div className="card-stats">
									<div className="card-stat">
										<b>{fInt(p.last_totals.reach)}</b>
										<span>Охваты</span>
									</div>
									<div className="card-stat">
										<b>{fInt(p.last_totals.leads)}</b>
										<span>Заявки</span>
									</div>
									<div className="card-stat">
										<b>{fPct(p.last_totals.er)}</b>
										<span>ER</span>
									</div>
								</div>
							)}
						</Link>
					))}
				</div>
			)}
		</Layout>
	);
}

function plural(n, one, few, many) {
	const m10 = n % 10;
	const m100 = n % 100;
	if (m10 === 1 && m100 !== 11) return one;
	if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
	return many;
}
