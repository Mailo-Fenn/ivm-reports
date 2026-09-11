import axios from 'axios';
import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Check, X, Camera, Users } from 'lucide-react';
import Layout from '../../Layout';

const initials = (name) => (name || '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('');

// загрузка фото через общий /upload — возвращает путь в storage/app/public
const uploadPhoto = async (file) => {
	const fd = new FormData();
	fd.append('image', file);
	const { data } = await axios.post('/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
	return data.path;
};

function Photo({ photo, name }) {
	return photo
		? <img className="emp-photo" src={`/storage/${photo}`} alt={name} />
		: <span className="emp-photo emp-initials">{initials(name) || '?'}</span>;
}

// форма сотрудника: общая для добавления и редактирования
function EmployeeForm({ initial, onSubmit, onCancel, busy }) {
	const [form, setForm] = useState({ name: initial?.name || '', position: initial?.position || '', photo: initial?.photo || null });
	const [uploading, setUploading] = useState(false);
	const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
	const pick = async (file) => {
		if (!file) return;
		setUploading(true);
		try { set('photo', await uploadPhoto(file)); } catch { alert('Не удалось загрузить фото'); } finally { setUploading(false); }
	};
	const id = `emp-photo-${initial?.id ?? 'new'}`;

	return (
		<div className="emp-form">
			<div className="emp-form-photo">
				<Photo photo={form.photo} name={form.name} />
				<input id={id} type="file" accept="image/*" style={{ display: 'none' }} onChange={(e) => pick(e.target.files[0])} />
				<div className="form-row" style={{ justifyContent: 'center' }}>
					<label htmlFor={id} className="btn btn-mini"><Camera size={13} /> {uploading ? 'Загрузка…' : form.photo ? 'Заменить фото' : 'Загрузить фото'}</label>
					{form.photo && <button className="btn btn-mini btn-danger" onClick={() => set('photo', null)}>Убрать</button>}
				</div>
			</div>
			<div className="emp-form-fields">
				<input className="inp" placeholder="Имя и фамилия" value={form.name} autoFocus onChange={(e) => set('name', e.target.value)} onKeyDown={(e) => e.key === 'Enter' && form.name.trim() && onSubmit(form)} />
				<input className="inp" placeholder="Должность" value={form.position} onChange={(e) => set('position', e.target.value)} onKeyDown={(e) => e.key === 'Enter' && form.name.trim() && onSubmit(form)} />
				<div className="form-row">
					<button className="btn btn-primary" disabled={busy || uploading || !form.name.trim()} onClick={() => onSubmit(form)}><Check size={15} /> Сохранить</button>
					<button className="btn" onClick={onCancel}><X size={15} /> Отмена</button>
				</div>
			</div>
		</div>
	);
}

export default function Index({ employees }) {
	const [adding, setAdding] = useState(false);
	const [editingId, setEditingId] = useState(null);
	const [busy, setBusy] = useState(false);

	const opts = (after) => ({ preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: after });
	const create = (form) => router.post('/employees', form, opts(() => setAdding(false)));
	const save = (id, form) => router.put(`/employees/${id}`, form, opts(() => setEditingId(null)));
	const remove = (e) => { if (confirm(`Удалить сотрудника «${e.name}»? Проекты останутся, изменится только карточка.`)) router.delete(`/employees/${e.id}`, { preserveScroll: true }); };

	return (
		<Layout>
			<div className="page-head">
				<div>
					<div className="eyebrow">Команда агентства</div>
					<h1 className="page-title">Сотрудники</h1>
				</div>
				<div className="page-actions">
					<button className="btn btn-primary" onClick={() => setAdding((v) => !v)}>
						<Plus size={16} /> Добавить сотрудника
					</button>
				</div>
			</div>

			{adding && (
				<div className="newform">
					<h3>Новый сотрудник</h3>
					<EmployeeForm onSubmit={create} onCancel={() => setAdding(false)} busy={busy} />
				</div>
			)}

			{employees.length === 0 && !adding ? (
				<div className="empty">
					<Users size={40} strokeWidth={1.5} style={{ opacity: .4, marginBottom: 12 }} />
					<h3>Пока нет сотрудников</h3>
					<p>Добавьте сотрудника — проекты подтянутся по полю «Ответственный» в карточке проекта.</p>
				</div>
			) : (
				<div className="emp-grid">
					{employees.map((e) => (
						<div key={e.id} className="card emp-card">
							{editingId === e.id ? (
								<EmployeeForm initial={e} onSubmit={(f) => save(e.id, f)} onCancel={() => setEditingId(null)} busy={busy} />
							) : (
								<>
									<div className="emp-photo-wrap">
										<Photo photo={e.photo} name={e.name} />
										<div className="emp-actions">
											<button className="btn btn-mini" title="Редактировать" onClick={() => setEditingId(e.id)}><Pencil size={13} /></button>
											<button className="btn btn-mini btn-danger" title="Удалить" onClick={() => remove(e)}><Trash2 size={13} /></button>
										</div>
									</div>
									<div className="emp-name">{e.name}</div>
									<div className="emp-position">{e.position || 'Должность не указана'}</div>
									<div className="emp-projects-label">Проекты</div>
									{e.projects.length ? (
										<div className="emp-projects">
											{e.projects.map((p) => (
												<Link key={p.id} href={`/projects/${p.id}`} className={'chip emp-chip' + (p.is_active ? '' : ' off')}>
													<span className="emp-dot" style={{ background: p.color }} />{p.name}
												</Link>
											))}
										</div>
									) : (
										<div style={{ fontSize: 12.5, color: 'var(--mut)' }}>Нет проектов — укажите это имя в поле «Ответственный» проекта</div>
									)}
								</>
							)}
						</div>
					))}
				</div>
			)}
		</Layout>
	);
}
