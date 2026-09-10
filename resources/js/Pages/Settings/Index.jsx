import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Instagram, KeyRound, Link2, Save, Trash2, Youtube } from 'lucide-react';
import Layout from '../../Layout';

export default function Index({ vk, vkid, google, instagram }) {
	const [token, setToken] = useState('');
	const [saving, setSaving] = useState(false);
	const [clientId, setClientId] = useState(vkid.client_id || '');
	const [savingId, setSavingId] = useState(false);
	const [gId, setGId] = useState(google?.client_id || '');
	const [gSecret, setGSecret] = useState('');
	const [savingG, setSavingG] = useState(false);

	const [igId, setIgId] = useState(instagram?.app_id || '');
	const [igSecret, setIgSecret] = useState('');
	const [savingIg, setSavingIg] = useState(false);

	const saveInstagram = () => {
		if (!igId.trim()) return;
		setSavingIg(true);
		router.post('/settings/instagram', { app_id: igId, app_secret: igSecret }, {
			onFinish: () => setSavingIg(false),
			onSuccess: () => setIgSecret(''),
		});
	};

	const saveGoogle = () => {
		if (!gId.trim()) return;
		setSavingG(true);
		router.post('/settings/google', { client_id: gId, client_secret: gSecret }, {
			onFinish: () => setSavingG(false),
			onSuccess: () => setGSecret(''),
		});
	};

	const disconnectGoogle = () => {
		if (confirm('Отключить Google? Сохранённые токены будут удалены, подтягивание статистики YouTube перестанет работать.')) {
			router.post('/settings/google', { disconnect: true });
		}
	};

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

			<div className="card" style={{ maxWidth: 640, marginTop: 18 }}>
				<div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
					<Youtube size={16} /> Google — статистика YouTube
				</div>

				<p style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px' }}>
					Авторизация аккаунта агентства в Google. Статистика канала (просмотры, подписчики,
					лайки, комментарии) доступна, только если этот аккаунт — владелец или менеджер
					канала клиента; количество видео подтягивается для любого открытого канала.
				</p>

				<ol style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px', paddingLeft: 18, lineHeight: 1.7 }}>
					<li>В <a href="https://console.cloud.google.com/" target="_blank" rel="noreferrer">Google Cloud Console</a> создайте проект и включите API: <b>YouTube Data API v3</b> и <b>YouTube Analytics API</b>.</li>
					<li>«APIs &amp; Services → OAuth consent screen»: тип External, добавьте себя в Test users, затем нажмите <b>Publish app</b> — иначе токен будет протухать каждые 7 дней.</li>
					<li>«Credentials → Create credentials → OAuth client ID», тип Web application. Authorized redirect URI: <code style={{ userSelect: 'all' }}>{google?.redirect_uri}</code></li>
					<li>Вставьте Client ID и Client Secret ниже, сохраните и нажмите «Подключить Google».</li>
					<li>Попросите клиентов добавить этот аккаунт менеджером канала (YouTube Studio → Настройки → Разрешения) и укажите канал в настройках проекта.</li>
				</ol>

				<div style={{ margin: '14px 0 6px', fontSize: 13, fontWeight: 700 }}>
					{google?.connected
						? <span className="status on">● Подключено{google.account ? ` (${google.account})` : ''}</span>
						: <span className="status off">● Не подключено</span>}
				</div>

				<div className="form-row" style={{ marginTop: 10, flexWrap: 'wrap' }}>
					<input
						className="inp"
						style={{ flex: 1, minWidth: 220 }}
						placeholder="Client ID (…apps.googleusercontent.com)"
						value={gId}
						onChange={(e) => setGId(e.target.value)}
					/>
					<input
						className="inp"
						type="password"
						style={{ flex: 1, minWidth: 180 }}
						placeholder={google?.has_secret ? 'Client Secret установлен — вставьте новый для замены' : 'Client Secret'}
						value={gSecret}
						onChange={(e) => setGSecret(e.target.value)}
					/>
					<button className="btn" onClick={saveGoogle} disabled={savingG || !gId.trim() || (!gSecret.trim() && !google?.has_secret)}>
						<Save size={15} /> Сохранить
					</button>
					{google?.client_id && google?.has_secret && (
						<a className="btn btn-primary" href="/google/connect">
							<Link2 size={15} /> {google.connected ? 'Переподключить' : 'Подключить Google'}
						</a>
					)}
					{google?.connected && (
						<button className="btn btn-danger" onClick={disconnectGoogle}><Trash2 size={15} /></button>
					)}
				</div>

				<p style={{ fontSize: 12, color: 'var(--mut)', marginTop: 12 }}>
					Client Secret хранится в базе в зашифрованном виде и не показывается. Канал каждого
					клиента указывается в настройках его проекта — поле «Канал YouTube».
				</p>
			</div>

			<div className="card" style={{ maxWidth: 640, marginTop: 18 }}>
				<div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
					<Instagram size={16} /> Meta — статистика Instagram
				</div>

				<p style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px' }}>
					Instagram выдаёт токен конкретному аккаунту, поэтому подключение делается отдельно
					для каждого проекта: кнопка «Подключить Instagram» в настройках проекта открывает вход
					в Instagram, куда нужно войти под аккаунтом клиента. Аккаунт должен быть
					бизнес- или авторским (Профиль → Настройки → Тип аккаунта). Здесь указываются
					только данные приложения Meta.
				</p>

				<ol style={{ fontSize: 13, color: 'var(--mut)', margin: '10px 0 4px', paddingLeft: 18, lineHeight: 1.7 }}>
					<li>В <a href="https://developers.facebook.com/apps/" target="_blank" rel="noreferrer">Meta for Developers</a> создайте приложение: вариант <b>Other → Business</b>.</li>
					<li>В панели приложения добавьте продукт <b>Instagram</b> и откройте <b>API setup with Instagram login</b>.</li>
					<li>В разделе <b>Business login settings</b> добавьте OAuth redirect URI: <code style={{ userSelect: 'all' }}>{instagram?.redirect_uri}</code></li>
					<li>Там же скопируйте <b>Instagram app ID</b> и <b>Instagram app secret</b> (не путать с общими App ID и Secret приложения) и вставьте ниже.</li>
					<li>Пока приложение не прошло проверку Meta, подключать можно только аккаунты-тестировщики: <b>App roles → Roles → Add people → Instagram Tester</b>, укажите аккаунт клиента. Клиент принимает приглашение в Instagram: Настройки → Сайт и приложения → Приглашения тестировщиков.</li>
				</ol>

				<p style={{ fontSize: 12, color: 'var(--mut)', margin: '6px 0 4px' }}>
					Сторис в API доступны только 24 часа, поэтому портал собирает их по расписанию.
					На сервере должен быть настроен cron с командой <code>php artisan schedule:run</code>.
				</p>

				<div style={{ margin: '14px 0 6px', fontSize: 13, fontWeight: 700 }}>
					{instagram?.app_id && instagram?.has_secret
						? <span className="status on">● Приложение настроено</span>
						: <span className="status off">● Приложение не настроено</span>}
				</div>

				<div className="form-row" style={{ marginTop: 10, flexWrap: 'wrap' }}>
					<input
						className="inp"
						style={{ flex: 1, minWidth: 200 }}
						placeholder="Instagram app ID"
						value={igId}
						onChange={(e) => setIgId(e.target.value)}
					/>
					<input
						className="inp"
						type="password"
						style={{ flex: 1, minWidth: 200 }}
						placeholder={instagram?.has_secret ? 'App secret установлен — вставьте новый для замены' : 'Instagram app secret'}
						value={igSecret}
						onChange={(e) => setIgSecret(e.target.value)}
					/>
					<button className="btn btn-primary" onClick={saveInstagram} disabled={savingIg || !igId.trim() || (!igSecret.trim() && !instagram?.has_secret)}>
						<Save size={15} /> Сохранить
					</button>
				</div>
			</div>
		</Layout>
	);
}
