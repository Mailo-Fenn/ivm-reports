import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';

export default function Login({ configured }) {
	const form = useForm({ login: '', password: '' });

	const submit = (e) => {
		e.preventDefault();
		form.post('/login', { onFinish: () => form.reset('password') });
	};

	return (
		<div className="login-wrap">
			<Head title="Вход" />
			<form className="card login-card" onSubmit={submit}>
				<div className="logo" style={{ marginBottom: 18 }}>
					<span className="logo-bar" />
					<span>
						<span className="logo-name">ИСТИНА</span>
						<span className="logo-sub">В МАРКЕТИНГЕ</span>
					</span>
				</div>

				<div className="eyebrow">Портал SMM-отчётности</div>
				<h1 className="page-title" style={{ fontSize: 24, marginBottom: 18 }}>Вход</h1>

				{!configured && (
					<div className="login-err">
						Вход не настроен: задайте PORTAL_LOGIN и PORTAL_PASSWORD в файле .env на сервере.
					</div>
				)}

				<label className="login-field">
					<span>Логин</span>
					<input
						className="inp"
						autoComplete="username"
						autoFocus
						value={form.data.login}
						onChange={(e) => form.setData('login', e.target.value)}
					/>
				</label>

				<label className="login-field">
					<span>Пароль</span>
					<input
						className="inp"
						type="password"
						autoComplete="current-password"
						value={form.data.password}
						onChange={(e) => form.setData('password', e.target.value)}
					/>
				</label>

				{(form.errors.password || form.errors.login) && (
					<div className="login-err">{form.errors.password || form.errors.login}</div>
				)}

				<button className="btn btn-primary" type="submit" disabled={form.processing || !configured} style={{ width: '100%', justifyContent: 'center', marginTop: 6 }}>
					<LogIn size={16} /> {form.processing ? 'Проверка…' : 'Войти'}
				</button>
			</form>
		</div>
	);
}
