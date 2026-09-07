import React, { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ crumbs = [], children }) {
  const { flash } = usePage().props;
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
          <Link href="/projects" className="logo">
            <span className="logo-bar" />
            <span>
              <span className="logo-name">ИСТИНА</span>
              <span className="logo-sub">В МАРКЕТИНГЕ</span>
            </span>
          </Link>
          <div className="topbar-spacer" />
          <nav className="crumbs">
            <Link href="/projects">Проекты</Link>
            {crumbs.map((c, i) => (
              <React.Fragment key={i}>
                <span className="sep">/</span>
                {c.href ? <Link href={c.href}>{c.label}</Link> : <span className="cur">{c.label}</span>}
              </React.Fragment>
            ))}
          </nav>
          <nav className="crumbs" style={{ marginLeft: 18 }}>
            <Link href="/settings">Настройки</Link>
          </nav>
        </div>
      </header>
      <main className="page">{children}</main>
      {toast && <div className={'flash' + (toast.err ? ' err' : '')}>{toast.text}</div>}
    </div>
  );
}
