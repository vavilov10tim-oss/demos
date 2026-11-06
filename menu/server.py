#!/usr/bin/env python3
"""Бэкенд меню «Утро в Сердце»: заказ и жалобы в Telegram, ИИ-чат через GigaChat.

Только стандартная библиотека. Запуск: python3 server.py [порт]
Переменные (.env рядом с файлом или окружение):
  BOT_TOKEN      — токен Telegram-бота (заказы и жалобы приходят владельцу)
  OWNER_CHAT_ID  — chat_id владельца/группы зала
  GIGACHAT_KEY   — ключ авторизации GigaChat API
  GIGACHAT_MODEL — необязательно, по умолчанию GigaChat-2-Max
"""
import json
import os
import re
import ssl
import sys
import time
import uuid
import urllib.request
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).parent
CA_BUNDLE = Path(__file__).parent.parent / "ai-admin" / "certs" / "gigachat_bundle.pem"

env_file = ROOT / ".env"
if env_file.exists():
    for line in env_file.read_text().splitlines():
        m = re.match(r"^\s*([A-Z_]+)\s*=\s*(.*)\s*$", line)
        if m and m.group(1) not in os.environ:
            os.environ[m.group(1)] = m.group(2).strip().strip('"').strip("'")

BOT_TOKEN = os.environ.get("BOT_TOKEN", "")
OWNER_CHAT_ID = os.environ.get("OWNER_CHAT_ID", "")
GIGACHAT_KEY = os.environ.get("GIGACHAT_KEY", "")
GIGACHAT_MODEL = os.environ.get("GIGACHAT_MODEL", "GigaChat-2-Max")
GIGACHAT_SCOPE = os.environ.get("GIGACHAT_SCOPE", "GIGACHAT_API_PERS")


def load_kb() -> str:
    """База знаний для чата: меню из menu-data.js."""
    lines = []
    for line in (ROOT / "menu-data.js").read_text().splitlines():
        m = re.match(r"const (ITEMS|EXTRA|ADDONS) = (\[.*\]);$", line)
        if not m:
            continue
        for it in json.loads(m.group(2)):
            title, price, cat = it.get("title", ""), it.get("price", 0), it.get("cat", "Добавки")
            tag = it.get("tag", "")
            variants = it.get("variants") or []
            vtxt = ("; варианты: " + ", ".join(f"{v['title']} — {v['price']} ₽" for v in variants)) if variants else ""
            lines.append(f"- {title} — {price} ₽ ({cat}){'. ' + tag if tag else ''}{vtxt}")
    return "\n".join(lines)


SYSTEM_PROMPT = """Ты — помощник ресторана «Утро в Сердце» (Москва, Шелепихинская наб., 34к3, 8:00–23:00).
Отвечаешь гостю в чате меню: советуешь блюда, называешь цены и состав, помогаешь выбрать.
Правила:
- Отвечай только по меню ниже. Ничего не выдумывай: если блюда нет в списке, скажи, что его нет, и предложи похожее.
- Коротко и по-человечески, 1–4 предложения. Цены называй в рублях.
- Гость сидит в зале{table}. Заказ он делает сам через кнопку «+» в меню; ты только советуешь.
- Про бронирование столов и банкеты: предложи позвонить +7 (903) 592-50-75.
- Алкоголя в меню нет — не предлагай.

МЕНЮ:
{kb}"""

# GigaChat
_gc_token = None
_gc_expires = 0.0


def _ssl_ctx():
    if CA_BUNDLE.exists():
        return ssl.create_default_context(cafile=str(CA_BUNDLE))
    return ssl.create_default_context()


def _post(url, data, headers, ctx=None):
    req = urllib.request.Request(url, data=data, headers=headers, method="POST")
    with urllib.request.urlopen(req, context=ctx, timeout=60) as r:
        return json.loads(r.read())


def gigachat_reply(messages):
    global _gc_token, _gc_expires
    if not _gc_token or time.time() > _gc_expires - 60:
        auth = _post(
            "https://ngw.devices.sberbank.ru:9443/api/v2/oauth",
            data=f"scope={GIGACHAT_SCOPE}".encode(),
            headers={
                "Authorization": f"Basic {GIGACHAT_KEY}",
                "RqUID": str(uuid.uuid4()),
                "Content-Type": "application/x-www-form-urlencoded",
                "Accept": "application/json",
            },
            ctx=_ssl_ctx(),
        )
        _gc_token = auth["access_token"]
        _gc_expires = auth["expires_at"] / 1000
    out = _post(
        "https://gigachat.devices.sberbank.ru/api/v1/chat/completions",
        data=json.dumps({"model": GIGACHAT_MODEL, "messages": messages, "temperature": 0.2, "max_tokens": 500}).encode(),
        headers={"Authorization": f"Bearer {_gc_token}", "Content-Type": "application/json", "Accept": "application/json"},
        ctx=_ssl_ctx(),
    )
    return out["choices"][0]["message"]["content"]


def tg_send(text):
    if not (BOT_TOKEN and OWNER_CHAT_ID):
        print("[demo] сообщение владельцу:\n" + text)
        return
    _post(
        f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage",
        data=json.dumps({"chat_id": OWNER_CHAT_ID, "text": text}).encode(),
        headers={"Content-Type": "application/json"},
    )


KB = load_kb()


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=str(ROOT), **kw)

    def log_message(self, fmt, *args):
        if "/api/" in (args[0] if args else ""):
            super().log_message(fmt, *args)

    def _json_body(self):
        n = int(self.headers.get("Content-Length") or 0)
        return json.loads(self.rfile.read(n) or b"{}")

    def _reply(self, obj, code=200):
        body = json.dumps(obj, ensure_ascii=False).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        try:
            body = self._json_body()
        except Exception:
            return self._reply({"ok": False, "error": "bad json"}, 400)

        if self.path == "/api/order":
            items = "\n".join(f"  {l['q']} × {l['title']} — {l['price'] * l['q']} ₽" for l in body.get("items", []))
            table = f"стол № {body['table']}" if body.get("table") else "стол не указан"
            text = (f"🔔 Заказ из меню ({table})\n{items}\nИтого: {body.get('total', 0)} ₽"
                    + (f"\nКомментарий: {body['comment']}" if body.get("comment") else ""))
            tg_send(text)
            return self._reply({"ok": True})

        if self.path == "/api/review":
            table = f", стол № {body['table']}" if body.get("table") else ""
            tg_send(f"⚠️ Жалоба из меню ({body.get('rating')}★{table}):\n{body.get('text', '')}")
            return self._reply({"ok": True})

        if self.path == "/api/chat":
            if not GIGACHAT_KEY:
                return self._reply({"ok": True, "reply": "Чат пока настраивается. Спросите официанта или позвоните: +7 (903) 592-50-75"})
            table = f" (стол № {body['table']})" if body.get("table") else ""
            system = SYSTEM_PROMPT.format(kb=KB, table=table)
            msgs = [{"role": "system", "content": system}] + [
                {"role": m["role"], "content": m["content"]}
                for m in body.get("messages", [])[-12:]
                if m.get("role") in ("user", "assistant") and m.get("content")
            ]
            try:
                return self._reply({"ok": True, "reply": gigachat_reply(msgs)})
            except Exception as e:
                print("[gigachat]", repr(e))
                return self._reply({"ok": True, "reply": "Что-то пошло не так. Спросите официанта — он подскажет."})

        return self._reply({"ok": False, "error": "unknown endpoint"}, 404)


if __name__ == "__main__":
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
    print(f"Меню «Утро в Сердце»: http://localhost:{port}/menu.html")
    print(f"  Telegram: {'подключён' if BOT_TOKEN else 'ДЕМО-режим (нет BOT_TOKEN)'}")
    print(f"  GigaChat: {'подключён' if GIGACHAT_KEY else 'выкл (нет GIGACHAT_KEY)'}")
    ThreadingHTTPServer(("0.0.0.0", port), Handler).serve_forever()
