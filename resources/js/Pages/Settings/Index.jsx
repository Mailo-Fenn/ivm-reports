import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { KeyRound, Link2, Save, Trash2 } from 'lucide-react';
import Layout from '../../Layout';

export default function Index({ vk, vkid }) {
	const [token, setToken] = useState('');
	const [saving, setSaving] = useState(false);
	const [clientId, setClientId] = useState(vkid.client_id || '');
	const [savingId, setSavingId] = useState(false);

	const save = () => {
		if (!token.trim()) return;
		setSaving(true);
		router.post('/settings', { vk_token: token }, {
			onFinish: () => setSaving(false),
			onSuccess: () => setToken(''),
		});
	};

	const remove = () => {
		if (confirm('Удалить сохранённый токен ВК? Подтягивание данных из ВК перестанет работать.')) {
			router.post('/settings', { vk_token: '' });
		}
	};

	const saveClientId = () => {
		if (!clientId.trim()) return;
		setSavingId(true);
		router.post('/settings/vkid', { client_id: clientId }, {
			onFinish: () => setSavingId(false),
		});
	};

	const disconnect = () => {
		if (confirm('Отключить VK? Сохранённые токены VK ID будут удалены, подтягивание статистики перестанет работать.')) {
			router.post('/settings/vkid', { disconnect: true });
		}
	};

	return (
		<Layout crumbs={[{ label: 'Настройки' }]}>
			<div className="page-head">
				<div>
					<div className="eyebrow">Портал</div>
					<h1 className="page-title">Настройки</h1>
				</div>
			</div>

			<div className="card" style={{ maxWidth: 640 }}>
				<div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
					<Link2 size={16} /> VK ID — подключение администратора
				</div>

				<p style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px' }}>
					Официальная авторизация ВКонтакте. Токен получается от имени аккаунта-администратора
					сообществ клиентов и обновляется автоматически — именно он используется для
					подтягивания статистики в отчёты.
				</p>

				<ol style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px', paddingLeft: 18, lineHeight: 1.7 }}>
					<li>Создайте приложение в кабинете <a href="https://id.vk.ru/about/business/go" target="_blank" rel="noreferrer">VK ID для бизнеса</a> (тип — Web).</li>
					<li>В настройках приложения укажите доверенный Redirect URL: <code style={{ userSelect: 'all' }}>{vkid.redirect_uri}</code></li>
					<li>Вставьте ID приложения ниже, сохраните и нажмите «Подключить VK».</li>
				</ol>

				<p style={{ fontSize: 12, color: 'var(--mut)', margin: '6px 0 4px' }}>
					Посты и данные сообществ доступны сразу. Для статистики (просмотры, охваты,
					подписчики) VK должен выдать приложению право «stats» — оно включается только
					по заявке в поддержку кабинета VK ID; до одобрения цифры можно вносить вручную
					или использовать резервный токен ниже.
				</p>

				<div style={{ margin: '14px 0 6px', fontSize: 13, fontWeight: 700 }}>
					{vkid.connected
						? <span className="status on">● Подключено{vkid.user_id ? ` (VK ID ${vkid.user_id})` : ''}</span>
						: <span className="status off">● Не подключено</span>}
				</div>

				<div className="form-row" style={{ marginTop: 10 }}>
					<input
						className="inp"
						style={{ flex: 1, minWidth: 200 }}
						placeholder="ID приложения VK ID"
						value={clientId}
						onChange={(e) => setClientId(e.target.value)}
					/>
					<button className="btn" onClick={saveClientId} disabled={savingId || !clientId.trim()}>
						<Save size={15} /> Сохранить
					</button>
					{vkid.client_id && (
						<a className="btn btn-primary" href="/vk/connect">
							<Link2 size={15} /> {vkid.connected ? 'Переподключить' : 'Подключить VK'}
						</a>
					)}
					{vkid.connected && (
						<button className="btn btn-danger" onClick={disconnect}><Trash2 size={15} /></button>
					)}
				</div>
			</div>

			<div className="card" style={{ maxWidth: 640, marginTop: 18 }}>
				<div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
					<KeyRound size={16} /> Резервный токен ВК (вручную)
				</div>

				<p style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px' }}>
					Запасной вариант, если VK ID не подключён: сюда можно вставить готовый токен
					аккаунта-администратора. При подключённом VK ID это поле не используется.
					Хранится в базе в зашифрованном виде и никогда не показывается целиком.
				</p>

				<div style={{ margin: '14px 0 6px', fontSize: 13, fontWeight: 700 }}>
					{vk.has_token
						? <span className="status on">● Токен установлен (····{vk.token_tail})</span>
						: <span className="status off">● Токен не задан</span>}
				</div>

				<div className="form-row" style={{ marginTop: 10 }}>
					<input
						className="inp"
						type="password"
						style={{ flex: 1, minWidth: 260 }}
						placeholder={vk.has_token ? 'Вставьте новый токен, чтобы заменить' : 'Вставьте токен доступа ВК'}
						value={token}
						onChange={(e) => setToken(e.target.value)}
					/>
					<button className="btn btn-primary" onClick={save} disabled={saving || !token.trim()}>
						<Save size={15} /> {saving ? 'Проверка…' : 'Сохранить'}
					</button>
					{vk.has_token && (
						<button className="btn btn-danger" onClick={remove}><Trash2 size={15} /></button>
					)}
				</div>

				<p style={{ fontSize: 12, color: 'var(--mut)', marginTop: 12 }}>
					При сохранении токен проверяется запросом к VK API. Сообщество каждого клиента
					указывается в настройках его проекта — поле «Сообщество ВК».
				</p>
			</div>
		</Layout>
	);
}
