#!/usr/bin/env python3
"""
Статистика каналов Telegram через клиентский API (Telethon).

Вызывается из Laravel (App\\Services\\TelegramStats). Все ответы — одна JSON-строка в stdout,
ошибки — {"error": "...", "kind": "..."} и код выхода 1.

Параметры подключения передаются через переменные окружения:
  TG_API_ID, TG_API_HASH — из https://my.telegram.org
  TG_SESSION            — путь к файлу сессии (без расширения .session)

Команды:
  me                                  — кто подключён
  login_start <phone>                 — отправить код входа, вернуть phone_code_hash
  login_code <phone> <hash> <code>    — войти по коду; при облачном пароле вернёт need_password
  login_password <password>           — завершить вход облачным паролем
  logout                              — выйти и удалить сессию
  channel <ref>                       — данные канала (@имя, ссылка t.me или числовой id)
  count <ref>                         — текущее число подписчиков
  stats <ref> <since_ts> <until_ts>   — посты за интервал и прирост подписчиков по дням
"""
import asyncio
import json
import os
import re
import sys
from datetime import datetime, timezone

try:
    from telethon import TelegramClient, errors
    from telethon.tl.functions.channels import GetFullChannelRequest
    from telethon.tl.functions.stats import GetBroadcastStatsRequest, LoadAsyncGraphRequest
    from telethon.tl.types import MessageService, StatsGraph, StatsGraphAsync
except ImportError:
    print(json.dumps({"error": "Библиотека telethon не установлена (pip install telethon)", "kind": "config"}, ensure_ascii=False))
    sys.exit(1)


def out(data, code=0):
    # Laravel читает stdout как UTF-8 независимо от локали сервера
    sys.stdout.reconfigure(encoding="utf-8")
    print(json.dumps(data, ensure_ascii=False))
    sys.exit(code)


def fail(message, kind="error"):
    out({"error": message, "kind": kind}, 1)


def make_client():
    api_id = os.environ.get("TG_API_ID")
    api_hash = os.environ.get("TG_API_HASH")
    session = os.environ.get("TG_SESSION")
    if not api_id or not api_hash or not session:
        fail("Не заданы TG_API_ID, TG_API_HASH или TG_SESSION", "config")
    os.makedirs(os.path.dirname(session) or ".", exist_ok=True)
    return TelegramClient(
        session, int(api_id), api_hash,
        device_model="IVM SMM Portal", system_version="Linux", app_version="1.0", lang_code="ru",
        # короткие флуд-паузы пережидаем сами, длинные отдаём наверх ошибкой
        flood_sleep_threshold=30,
    )


def user_info(user):
    return {
        "id": user.id,
        "username": user.username,
        "name": " ".join(filter(None, [user.first_name, user.last_name])) or None,
        "phone": user.phone,
    }


def parse_ref(ref):
    """@имя, t.me/имя, https://t.me/имя/123, -100123 или 123 → username | int"""
    ref = ref.strip()
    ref = re.sub(r"^https?://(www\.)?(t\.me|telegram\.me)/(s/)?", "", ref, flags=re.I)
    ref = ref.split("?")[0].split("#")[0].strip("/")
    if ref.startswith("+") or ref.startswith("joinchat/"):
        fail("Ссылка-приглашение не подходит — укажите публичное имя канала или его числовой id", "ref")
    ref = ref.split("/")[0].lstrip("@")
    if re.fullmatch(r"-?\d+", ref):
        n = int(ref)
        # id канала в ссылках вида t.me/c/123 и в ботах приходит без префикса -100
        if n > 0:
            n = int("-100" + str(n))
        return n
    if not re.fullmatch(r"[A-Za-z][A-Za-z0-9_]{3,}", ref):
        fail(f"Не удалось разобрать адрес канала «{ref}»", "ref")
    return ref


async def get_channel(client, ref):
    target = parse_ref(ref)
    try:
        if isinstance(target, int):
            # приватные каналы находятся только через список диалогов аккаунта
            await client.get_dialogs(limit=200)
        entity = await client.get_entity(target)
    except (ValueError, errors.UsernameNotOccupiedError, errors.UsernameInvalidError, errors.ChannelPrivateError):
        fail(f"Канал «{ref}» не найден или аккаунт не состоит в нём", "ref")
    if not getattr(entity, "broadcast", False):
        fail(f"«{ref}» — не канал (группы и пользователи не поддерживаются)", "ref")
    full = await client(GetFullChannelRequest(entity))
    return entity, {
        "id": entity.id,
        "title": entity.title,
        "username": entity.username,
        "subscribers": full.full_chat.participants_count or 0,
    }


async def require_auth(client):
    if not await client.is_user_authorized():
        fail("Аккаунт Telegram не подключён — войдите на странице «Настройки»", "unauthorized")


async def graph_data(client, graph):
    if isinstance(graph, StatsGraphAsync):
        graph = await client(LoadAsyncGraphRequest(token=graph.token))
    if not isinstance(graph, StatsGraph):
        return None
    return json.loads(graph.json.data)


async def followers_by_day(client, entity):
    """Прирост подписчиков по дням из встроенной статистики канала (нужно ≥500 подписчиков и права админа)."""
    try:
        stats = await client(GetBroadcastStatsRequest(channel=entity))
        data = await graph_data(client, stats.followers_graph)
    except errors.RPCError as e:
        return None, e.__class__.__name__
    if not data:
        return None, "no_graph"
    cols = {c[0]: c[1:] for c in data.get("columns", [])}
    xs = cols.get("x") or []
    names = data.get("names", {})
    joined = left = None
    for key, name in names.items():
        if str(name).lower().startswith(("join", "подпис")):
            joined = cols.get(key)
        elif str(name).lower().startswith(("left", "отпис")):
            left = cols.get(key)
    if joined is None:
        ys = [cols[k] for k in cols if k != "x"]
        joined = ys[0] if ys else []
        left = ys[1] if len(ys) > 1 else [0] * len(joined)
    result = {}
    for i, x in enumerate(xs):
        day = datetime.fromtimestamp(x / 1000, tz=timezone.utc).strftime("%Y-%m-%d")
        j = int(joined[i]) if i < len(joined) else 0
        l = abs(int(left[i])) if left and i < len(left) else 0
        result[day] = j - l
    return result, None


async def posts_between(client, entity, since, until):
    """Посты за интервал; альбом из нескольких медиа считается одним постом."""
    posts = []
    seen_groups = set()
    async for m in client.iter_messages(entity, offset_date=datetime.fromtimestamp(until + 1, tz=timezone.utc)):
        ts = int(m.date.timestamp())
        if ts < since:
            break
        if ts > until or isinstance(m, MessageService):
            continue
        if m.grouped_id:
            if m.grouped_id in seen_groups:
                continue
            seen_groups.add(m.grouped_id)
        reactions = 0
        if m.reactions and m.reactions.results:
            reactions = sum(r.count for r in m.reactions.results)
        posts.append({
            "id": m.id,
            "date": ts,
            "views": m.views or 0,
            "forwards": m.forwards or 0,
            "reactions": reactions,
            "replies": (m.replies.replies if m.replies else 0) or 0,
        })
    return posts


async def main(argv):
    if len(argv) < 2:
        fail("Не указана команда", "usage")
    cmd, args = argv[1], argv[2:]
    client = make_client()
    await client.connect()
    try:
        if cmd == "me":
            if not await client.is_user_authorized():
                out({"authorized": False})
            out({"authorized": True, "user": user_info(await client.get_me())})

        if cmd == "login_start":
            phone = args[0]
            sent = await client.send_code_request(phone)
            out({"phone_code_hash": sent.phone_code_hash})

        if cmd == "login_code":
            phone, code_hash, code = args[0], args[1], args[2]
            try:
                user = await client.sign_in(phone=phone, code=code, phone_code_hash=code_hash)
            except errors.SessionPasswordNeededError:
                out({"need_password": True})
            out({"user": user_info(user)})

        if cmd == "login_password":
            user = await client.sign_in(password=args[0])
            out({"user": user_info(user)})

        if cmd == "logout":
            if await client.is_user_authorized():
                await client.log_out()
            out({"ok": True})

        await require_auth(client)

        if cmd == "channel":
            _, info = await get_channel(client, args[0])
            out(info)

        if cmd == "count":
            _, info = await get_channel(client, args[0])
            out({"subscribers": info["subscribers"]})

        if cmd == "stats":
            ref, since, until = args[0], int(args[1]), int(args[2])
            entity, info = await get_channel(client, ref)
            posts = await posts_between(client, entity, since, until)
            followers, stats_error = await followers_by_day(client, entity)
            out({"channel": info, "posts": posts, "followers_by_day": followers, "stats_error": stats_error})

        fail(f"Неизвестная команда {cmd}", "usage")
    except errors.PhoneNumberInvalidError:
        fail("Неверный номер телефона", "login")
    except errors.PhoneCodeInvalidError:
        fail("Неверный код", "login")
    except errors.PhoneCodeExpiredError:
        fail("Код устарел — запросите новый", "login")
    except errors.PasswordHashInvalidError:
        fail("Неверный облачный пароль", "login")
    except errors.FloodWaitError as e:
        fail(f"Telegram просит подождать {e.seconds} сек. перед следующей попыткой", "flood")
    except errors.AuthKeyUnregisteredError:
        fail("Сессия Telegram отозвана — войдите заново на странице «Настройки»", "unauthorized")
    except errors.RPCError as e:
        fail(f"Ошибка Telegram: {e.__class__.__name__}: {e}", "rpc")
    finally:
        await client.disconnect()


if __name__ == "__main__":
    asyncio.run(main(sys.argv))
