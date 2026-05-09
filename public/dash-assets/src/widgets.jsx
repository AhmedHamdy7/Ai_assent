// Reusable widgets — chips, headers, buttons, tiny charts, command palette
const { useState, useEffect, useRef, useMemo } = React;

function Pill({ children, tone = "default", glow }) {
  const tones = {
    default: "border-white/10 text-white/65 bg-white/[0.03]",
    cyan:    "border-cyan-glow/40 text-cyan-glow bg-cyan-glow/[0.06]",
    azure:   "border-[#5C7BFF]/40 text-[#A8B7FF] bg-[#5C7BFF]/[0.07]",
    pos:     "border-[#5EE7A8]/30 text-[#5EE7A8] bg-[#5EE7A8]/[0.06]",
    warn:    "border-[#FFC56B]/30 text-[#FFC56B] bg-[#FFC56B]/[0.07]",
    danger:  "border-[#FF7A8A]/30 text-[#FF7A8A] bg-[#FF7A8A]/[0.07]",
  };
  return (
    <span className={`inline-flex items-center gap-1.5 px-2 py-[3px] rounded-md border text-[10.5px] font-mono uppercase tracking-[0.08em] ${tones[tone]}`} style={glow ? { boxShadow: "0 0 18px -6px currentColor" } : null}>
      {children}
    </span>
  );
}

function LiveDot({ tone = "pos" }) {
  const c = { pos: "#5EE7A8", cyan: "#5BD4FF", warn: "#FFC56B", danger: "#FF7A8A" }[tone];
  return (
    <span className="relative inline-flex items-center justify-center" style={{ width: 8, height: 8 }}>
      <span className="absolute inset-0 rounded-full pulse-dot" style={{ background: c, animationName: tone === "cyan" ? "pulseRingCyan" : "pulseRing" }} />
      <span className="relative w-1.5 h-1.5 rounded-full" style={{ background: c, boxShadow: `0 0 8px ${c}` }} />
    </span>
  );
}

function SectionHeader({ eyebrow, title, sub, right }) {
  return (
    <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-5">
      <div className="min-w-0">
        {eyebrow && <div className="font-mono text-[10.5px] tracking-[0.18em] uppercase text-white/40 mb-2">{eyebrow}</div>}
        <h1 className="text-[26px] sm:text-[30px] lg:text-[34px] leading-[1.1] tracking-[-0.02em] font-medium">{title}</h1>
        {sub && <div className="text-white/55 mt-2 max-w-2xl text-[13.5px] sm:text-[14px]">{sub}</div>}
      </div>
      {right && <div className="flex flex-wrap items-center gap-2">{right}</div>}
    </div>
  );
}

function Btn({ children, tone = "ghost", icon, onClick, className = "" }) {
  const tones = {
    ghost: "border-white/10 hover:bg-white/[0.05] text-white/85",
    primary: "border-cyan-glow/40 text-[#06080D] bg-cyan-glow/95 hover:bg-cyan-glow shadow-glow-cyan",
    secondary: "border-white/15 text-white/90 bg-white/[0.05] hover:bg-white/[0.08]",
  };
  return (
    <button onClick={onClick} className={`magnet inline-flex items-center gap-2 px-3.5 h-9 rounded-[10px] border text-[13px] font-medium ${tones[tone]} ${className}`}>
      {icon}{children}
    </button>
  );
}

function StatTile({ label, value, sub, spark, tone = "default" }) {
  const accent = { default: "text-white", cyan: "text-cyan-glow", azure: "text-[#A8B7FF]", pos: "text-[#5EE7A8]", warn: "text-[#FFC56B]" }[tone] || "text-white";
  const stroke = { default: "#5BD4FF", cyan: "#5BD4FF", azure: "#5C7BFF", pos: "#5EE7A8", warn: "#FFC56B" }[tone];
  return (
    <div className="glass p-4 flex items-end justify-between gap-3">
      <div className="min-w-0">
        <div className="text-[11px] font-mono uppercase tracking-[0.14em] text-white/45">{label}</div>
        <div className={`mt-2 text-[28px] font-medium tracking-[-0.02em] ${accent}`}>{value}</div>
        {sub && <div className="text-[12px] text-white/45 mt-1">{sub}</div>}
      </div>
      {spark && (
        <svg width="92" height="40" viewBox="0 0 92 40" className="shrink-0 opacity-90">
          <defs>
            <linearGradient id={`g-${label}`} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={stroke} stopOpacity="0.35"/>
              <stop offset="100%" stopColor={stroke} stopOpacity="0"/>
            </linearGradient>
          </defs>
          <path d={`${smoothPath(spark, 92, 40, 2)} L90 38 L2 38 Z`} fill={`url(#g-${label})`} />
          <path d={smoothPath(spark, 92, 40, 2)} fill="none" stroke={stroke} strokeWidth="1.5" strokeLinecap="round"/>
        </svg>
      )}
    </div>
  );
}

// Bar chart row (used in queue/cost)
function BarRow({ label, value, max, tone = "cyan", right }) {
  const colors = { cyan: "#5BD4FF", azure: "#5C7BFF", aurora: "#9D7BFF", pos: "#5EE7A8", warn: "#FFC56B", danger: "#FF7A8A" };
  const c = colors[tone];
  return (
    <div className="flex items-center gap-3 py-2">
      <div className="w-32 truncate text-[12.5px] text-white/70">{label}</div>
      <div className="flex-1 h-1.5 rounded-full bg-white/[0.05] overflow-hidden">
        <div className="h-full rounded-full" style={{ width: `${(value / max) * 100}%`, background: `linear-gradient(90deg, ${c}33, ${c})`, boxShadow: `0 0 12px -2px ${c}` }} />
      </div>
      <div className="w-20 text-right text-[12px] font-mono text-white/85">{right}</div>
    </div>
  );
}

// Glass section card with optional title/right
function Card({ title, eyebrow, right, children, className = "", ring }) {
  return (
    <div className={`glass p-5 ${ring ? "ring-aurora" : ""} ${className}`}>
      {(title || eyebrow || right) && (
        <div className="flex items-center justify-between mb-4">
          <div>
            {eyebrow && <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/40">{eyebrow}</div>}
            {title && <div className="text-[14px] font-medium text-white/90 mt-0.5">{title}</div>}
          </div>
          {right}
        </div>
      )}
      {children}
    </div>
  );
}

// Live waveform of bars
function Waveform({ bars = 26, tone = "cyan", height = 22 }) {
  const c = { cyan: "#5BD4FF", azure: "#5C7BFF", pos: "#5EE7A8" }[tone];
  return (
    <div className="flex items-end gap-[3px]" style={{ height }}>
      {Array.from({ length: bars }).map((_, i) => (
        <div key={i} className="w-[2px] rounded-full" style={{
          height: `${30 + Math.abs(Math.sin(i * 1.7)) * 70}%`,
          background: c, opacity: 0.5 + (i % 3) * 0.15,
          animation: `waveBar ${600 + (i * 47) % 700}ms ease-in-out ${(i * 30) % 800}ms infinite`,
          transformOrigin: 'bottom',
          boxShadow: `0 0 6px ${c}`,
        }}/>
      ))}
    </div>
  );
}

// Donut
function Donut({ value, max = 100, size = 100, stroke = 8, color = "#5BD4FF", track = "rgba(255,255,255,0.06)", label }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const off = c * (1 - value / max);
  return (
    <div className="relative" style={{ width: size, height: size }}>
      <svg width={size} height={size}>
        <circle cx={size/2} cy={size/2} r={r} fill="none" stroke={track} strokeWidth={stroke}/>
        <circle cx={size/2} cy={size/2} r={r} fill="none" stroke={color} strokeWidth={stroke} strokeLinecap="round" strokeDasharray={c} strokeDashoffset={off} transform={`rotate(-90 ${size/2} ${size/2})`} style={{ filter: `drop-shadow(0 0 6px ${color})`, transition: "stroke-dashoffset 600ms cubic-bezier(.2,.7,.2,1)" }}/>
      </svg>
      {label && <div className="absolute inset-0 flex items-center justify-center text-center"><div>{label}</div></div>}
    </div>
  );
}

// Fancy area chart
function AreaChart({ data, w = 560, h = 180, color = "#5BD4FF", color2 = "#5C7BFF", grid = true, label }) {
  const min = Math.min(...data), max = Math.max(...data), span = max - min || 1;
  const path = smoothPath(data, w, h, 12);
  const fill = `${path} L${w-12} ${h-12} L12 ${h-12} Z`;
  const id = useMemo(() => `ac-${Math.random().toString(36).slice(2,7)}`, []);
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full">
      <defs>
        <linearGradient id={id} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.45"/>
          <stop offset="100%" stopColor={color2} stopOpacity="0"/>
        </linearGradient>
      </defs>
      {grid && Array.from({length: 4}).map((_, i) => (
        <line key={i} x1="12" x2={w-12} y1={12 + (h-24) * (i/3)} y2={12 + (h-24) * (i/3)} stroke="rgba(255,255,255,0.05)" strokeDasharray="2 4"/>
      ))}
      <path d={fill} fill={`url(#${id})`} />
      <path d={path} fill="none" stroke={color} strokeWidth="1.6" strokeLinecap="round"/>
    </svg>
  );
}

window.Pill = Pill; window.LiveDot = LiveDot; window.SectionHeader = SectionHeader; window.Btn = Btn; window.StatTile = StatTile; window.BarRow = BarRow; window.Card = Card; window.Waveform = Waveform; window.Donut = Donut; window.AreaChart = AreaChart;
