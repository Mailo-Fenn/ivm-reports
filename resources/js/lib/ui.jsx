import React, { useState, useRef, useEffect } from 'react';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';

/* ---------- palette ---------- */
export const C = {
  violet: '#6C4CF0',
  teal: '#12B981',
  amber: '#F5A524',
  coral: '#F0556B',
  sky: '#2AABEE',
  ink: '#14152B',
  muted: '#71748C',
  line: '#E7E9F2',
};

export const AX = { fill: C.muted, fontSize: 12, fontFamily: 'Manrope, sans-serif' };

/* ---------- formatters ---------- */
export const fInt = (n) => Math.round(Number(n) || 0).toLocaleString('ru-RU');
export const fSigned = (n) => (n > 0 ? '+' : '') + Math.round(Number(n) || 0).toLocaleString('ru-RU');
export const fPct = (n) =>
  (Number(n) || 0).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
export const kAxis = (v) =>
  Math.abs(v) >= 1000
    ? (v / 1000).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + 'к'
    : String(v);

/* ---------- count-up ---------- */
export function useCountUp(target, animate = true) {
  const [val, setVal] = useState(target);
  const ref = useRef(target);
  useEffect(() => {
    const from = ref.current;
    const to = Number(target) || 0;
    const reduce =
      typeof window !== 'undefined' &&
      window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!animate || reduce || from === to) {
      setVal(to);
      ref.current = to;
      return;
    }
    let raf;
    const start = performance.now();
    const tick = (now) => {
      const t = Math.min(1, (now - start) / 800);
      const e = 1 - Math.pow(1 - t, 3);
      setVal(from + (to - from) * e);
      if (t < 1) raf = requestAnimationFrame(tick);
      else ref.current = to;
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [target, animate]);
  return val;
}

/* ---------- shared components ---------- */
export function Trend({ cur, prev, pp = false }) {
  if (prev === undefined || prev === null) return null;
  const diff = pp ? cur - prev : prev === 0 ? null : ((cur - prev) / prev) * 100;
  if (diff === null) return <span className="trend flat"><Minus size={13} /> —</span>;
  const up = diff > 0.05;
  const down = diff < -0.05;
  const Ico = up ? TrendingUp : down ? TrendingDown : Minus;
  const txt = pp
    ? (diff > 0 ? '+' : '') + diff.toFixed(1) + ' п.п.'
    : (diff > 0 ? '+' : '') + diff.toFixed(0) + '%';
  return (
    <span className={'trend ' + (up ? 'up' : down ? 'down' : 'flat')}>
      <Ico size={13} /> {txt}
    </span>
  );
}

export function KpiValue({ value, fmt, animate }) {
  const v = useCountUp(value, animate);
  return <div className="kpi-value">{fmt(v)}</div>;
}

export function PanelHead({ eyebrow, title, note }) {
  return (
    <div className="panel-head">
      <div>
        {eyebrow && <div className="eyebrow">{eyebrow}</div>}
        <h2 className="panel-title">{title}</h2>
      </div>
      {note && <span className="panel-note">{note}</span>}
    </div>
  );
}

export function ChartTip({ active, payload, label, fmt = fInt }) {
  if (!active || !payload || !payload.length) return null;
  return (
    <div className="tip">
      {label !== undefined && <div className="tip-label">{label}</div>}
      {payload.map((p, i) => (
        <div className="tip-row" key={i}>
          <span className="tip-dot" style={{ background: p.color || p.payload?.color }} />
          <span className="tip-name">{p.name}</span>
          <b>{fmt(p.value)}</b>
        </div>
      ))}
    </div>
  );
}

export function Legend({ items }) {
  return (
    <div className="chart-legend">
      {items.map((it, i) => (
        <span key={i} className="lg">
          <span className="dot" style={{ background: it.c, borderRadius: 99 }} />
          {it.t}
        </span>
      ))}
    </div>
  );
}
