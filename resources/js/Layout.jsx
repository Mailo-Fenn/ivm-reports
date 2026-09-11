import React, { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ crumbs = [], children }) {
  // share — страница открыта по ссылке для клиента: без «Проекты», настроек и выхода
  const { flash, share } = usePage().props;
  const { url } = usePage();
  const section = url.startsWith('/employees') ? 'employees' : 'projects';
  const [toast, setToast] = useState(null);

  useEffect(() => {
    if (flash?.success || flash?.error) {
      const err = !flash.success && !!flash.error;
      setToast({ text: flash.success || flash.error, err });
      const t = setTimeout(() => setToast(null), err ? 6000 : 2600);
      return () => clearTimeout(t);
    }
  }, [flash]);

  return (
    <div className="shell">
      <header className="topbar">
        <div className="topbar-in">
          <Link href={share ? `/share/${share.token}` : '/projects'} className="logo">
            <span className="logo-bar" />
            <span>
              <span className="logo-name">ИСТИНА</span>
              <span className="logo-sub">В МАРКЕТИНГЕ</span>
            </span>
          </Link>
          <nav className="crumbs">
            {!share && (
              <>
                <Link href="/projects" className={section === 'projects' && crumbs.length === 0 ? 'cur' : ''}>Проекты</Link>
                <span className="sep">·</span>
                <Link href="/employees" className={section === 'employees' ? 'cur' : ''}>Сотрудники</Link>
              </>
            )}
            {crumbs.map((c, i) => (
              <React.Fragment key={i}>
                {(!share || i > 0) && <span className="sep">›</span>}
                {c.href ? <Link href={c.href}>{c.label}</Link> : <span className="cur">{c.label}</span>}
              </React.Fragment>
            ))}
          </nav>
          <div className="topbar-spacer" />
          {share ? (
            <span className="crumbs" style={{ opacity: .8 }}>Отчёт для клиента</span>
          ) : (
            <nav className="crumbs">
              <Link href="/settings">Настройки</Link>
              <span className="sep">·</span>
              <Link href="/logout" method="post" as="button" type="button">Выйти</Link>
            </nav>
          )}
        </div>
      </header>
      <main className="page">{children}</main>
      {toast && <div className={'flash' + (toast.err ? ' err' : '')}>{toast.text}</div>}
    </div>
  );
}
