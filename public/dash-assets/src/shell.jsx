// Main shell: sidebar, topbar, command palette, design-system view
const { useState, useEffect, useRef, useMemo } = React;

const NAV = [
  { id: "command",    label: "Command Center",   k: "00", icon: <Icon.Spark/>     },
  { id: "agents",     label: "Tool Monitoring",  k: "01", icon: <Icon.Bot/>      },
  { id: "memory",     label: "Memory Graph",     k: "02", icon: <Icon.Memory/>   },
  { id: "telegram",   label: "Chat",             k: "03", icon: <Icon.Telegram/> },
  { id: "queue",      label: "Queue & Jobs",     k: "04", icon: <Icon.Queue/>    },
  { id: "cost",       label: "Expense Ledger",   k: "05", icon: <Icon.Coin/>     },
  { id: "api",        label: "API Playground",   k: "06", icon: <Icon.Code/>     },
  { id: "workflow",   label: "Orchestrator Flow",k: "07", icon: <Icon.Flow/>     },
  { id: "deploy",     label: "System Center",    k: "08", icon: <Icon.Rocket/>   },
  { id: "life",       label: "Productivity Hub", k: "09", icon: <Icon.Sun/>      },
];
const NAV_MAP = Object.fromEntries(NAV.map(n => [n.id, n]));

function Sidebar({ active, onChange, onOpenPalette, mobileOpen, onMobileClose }) {
  const appName = window.__DASH_DATA?.meta?.app_name || 'Hamdix';
  const appNameLower = appName.toLowerCase();
  const laravel = window.__DASH_DATA?.meta?.laravel || '';

  const handleChange = (id) => {
    onChange(id);
    if (onMobileClose) onMobileClose();
  };

  return (
    <>
      {/* Mobile overlay */}
      {mobileOpen && (
        <div className="lg:hidden fixed inset-0 z-40 bg-black/60 backdrop-blur-sm" onClick={onMobileClose}/>
      )}
      <aside className={`w-[260px] shrink-0 h-screen flex flex-col p-4 gap-3 border-r border-white/[0.05] z-50 transition-transform duration-300 ${mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'} fixed lg:sticky top-0 left-0`}
        style={{
          background: "linear-gradient(180deg, rgba(10,13,20,0.95), rgba(6,8,13,0.95))",
          backdropFilter: "blur(20px) saturate(140%)",
        }}>
        {/* Brand + close button on mobile */}
        <div className="flex items-center gap-2.5 px-2 pt-1 pb-3">
          <div className="w-8 h-8 rounded-[10px] grid place-items-center relative overflow-hidden" style={{
            background: "radial-gradient(120% 120% at 30% 0%, #5BD4FF 0%, #5C7BFF 40%, #1c2237 80%)",
            boxShadow: "inset 0 1px 0 rgba(255,255,255,0.4), 0 0 22px -4px rgba(91,212,255,0.6)",
          }}>
            <span className="absolute inset-0 grid place-items-center font-serif italic text-[#06080D] text-[18px] font-bold">{appNameLower.charAt(0)}</span>
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-[15px] tracking-[-0.01em] font-medium leading-none">{appName}</div>
            <div className="font-mono text-[9.5px] uppercase tracking-[0.18em] text-white/45 mt-1 truncate">operating layer{laravel ? ` · laravel ${laravel}` : ''}</div>
          </div>
          <button onClick={onMobileClose} className="lg:hidden text-white/45 hover:text-white p-1" title="Close menu"><Icon.X/></button>
        </div>

        {/* Search */}
        <button onClick={onOpenPalette} className="glass-strong rounded-[10px] flex items-center gap-2.5 px-3 h-9 text-left text-[12.5px] text-white/45 hover:text-white/70 transition">
          <Icon.Search/> Search anything
          <span className="ml-auto font-mono text-[10px] text-white/40">⌘K</span>
        </button>

        {/* Nav */}
        <nav className="space-y-0.5 mt-2 flex-1 overflow-y-auto pr-1">
          {NAV.map(n => (
            <button key={n.id} onClick={() => handleChange(n.id)}
              className={`w-full text-left px-2.5 h-9 rounded-[10px] flex items-center gap-3 group transition relative ${active === n.id ? 'bg-white/[0.06] text-white' : 'text-white/65 hover:bg-white/[0.03] hover:text-white'}`}>
              {active === n.id && <span className="absolute left-0 top-1.5 bottom-1.5 w-[2px] rounded-full bg-cyan-glow" style={{ boxShadow: "0 0 8px #5BD4FF" }}/>}
              <span className={`${active === n.id ? 'text-cyan-glow' : 'text-white/55 group-hover:text-white/85'}`}>{n.icon}</span>
              <span className="text-[13px] flex-1">{n.label}</span>
              <span className="font-mono text-[10px] text-white/35">{n.k}</span>
            </button>
          ))}
        </nav>

        {/* Footer */}
        <div className="space-y-2 pt-2 border-t border-white/[0.05]">
          <button onClick={() => handleChange("system")} className={`w-full text-left px-2.5 h-9 rounded-[10px] flex items-center gap-3 transition ${active === 'system' ? 'bg-white/[0.06] text-white' : 'text-white/65 hover:bg-white/[0.03]'}`}>
            <Icon.Stack/> <span className="text-[13px]">Design system</span>
          </button>
          <div className="glass-strong rounded-[12px] p-3 flex items-center gap-3">
            <div className="relative">
              <div className="w-9 h-9 rounded-full grid place-items-center font-serif italic text-[18px]" style={{ background: "linear-gradient(135deg, #5BD4FF, #5C7BFF)", color: "#06080D", boxShadow: "0 0 14px -2px #5BD4FF" }}>{appNameLower.charAt(0)}</div>
              <span className="absolute -bottom-0.5 -right-0.5"><LiveDot/></span>
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-[12.5px] truncate">{appName}</div>
              <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-white/45">{(window.__DASH_DATA?.meta?.env || 'local').toUpperCase()} · self</div>
            </div>
            <button className="text-white/45 hover:text-white"><Icon.Bell/></button>
          </div>
        </div>
      </aside>
    </>
  );
}

function TopBar({ activeId, onOpenPalette, onOpenSidebar, onRefresh, refreshing }) {
  const t = useClock();
  const time = t.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false });
  const date = t.toLocaleDateString([], { weekday: "short", month: "short", day: "numeric" });
  const nav = NAV_MAP[activeId];
  const appName = window.__DASH_DATA?.meta?.app_name || 'Hamdix';
  return (
    <div className="sticky top-0 z-30 flex items-center gap-2 sm:gap-3 px-3 sm:px-5 lg:px-7 h-14 border-b border-white/[0.05]" style={{
      background: "linear-gradient(180deg, rgba(8,10,16,0.85), rgba(8,10,16,0.55))",
      backdropFilter: "blur(20px)",
    }}>
      {/* Mobile hamburger */}
      <button onClick={onOpenSidebar} className="lg:hidden h-9 w-9 grid place-items-center rounded-[10px] glass-strong text-white/85 hover:text-cyan-glow transition" title="Open menu">
        <Icon.Stack/>
      </button>

      <div className="hidden sm:flex items-center gap-2 font-mono text-[10.5px] uppercase tracking-[0.18em] text-white/45 min-w-0">
        <span className="truncate">{appName.toLowerCase()}</span><span>/</span>
        <span className="text-white/82 truncate">{nav ? nav.label : "Design system"}</span>
        <span className="ml-2 px-1.5 py-0.5 rounded bg-white/[0.04] border border-white/[0.06] text-white/55 shrink-0">{nav ? nav.k : "ds"}</span>
      </div>

      {/* Mobile-only screen label */}
      <div className="sm:hidden flex-1 min-w-0 font-mono text-[11px] uppercase tracking-[0.14em] text-white/82 truncate">
        {nav ? nav.label : "Design system"}
      </div>

      <div className="hidden sm:block flex-1"/>

      <button onClick={onOpenPalette} className="hidden md:flex glass-strong rounded-[10px] items-center gap-2.5 px-3 h-9 text-[12.5px] text-white/55 hover:text-white/85">
        <Icon.Search/> <span className="hidden xl:inline">Search · jump · run</span><span className="xl:hidden">Search</span>
        <span className="font-mono text-[10px] text-white/40 ml-3">⌘K</span>
      </button>

      {/* Refresh button — works on every viewport */}
      <button onClick={onRefresh} disabled={refreshing}
        className={`h-9 w-9 sm:w-auto sm:px-3 grid place-items-center sm:flex sm:items-center sm:gap-2 rounded-[10px] glass-strong transition ${refreshing ? 'opacity-60' : 'hover:border-cyan-glow/30'}`}
        title="Refresh data">
        <span className={refreshing ? 'animate-spin inline-block' : 'inline-block'} style={{ transformOrigin: 'center' }}><Icon.Spark2/></span>
        <span className="hidden sm:inline text-[12.5px]">{refreshing ? 'Refreshing…' : 'Refresh'}</span>
      </button>

      <Magnet className="hidden sm:inline-block">
        <button onClick={onOpenPalette} className="h-9 px-3 rounded-[10px] glass-strong flex items-center gap-2 hover:border-cyan-glow/30 transition">
          <Icon.Spark/>
          <span className="text-[12.5px]">Dispatch</span>
          <span className="hidden lg:inline font-mono text-[10px] text-white/40">⌘ ↵</span>
        </button>
      </Magnet>

      <div className="hidden md:flex items-center gap-3 pl-3 border-l border-white/[0.06]">
        <div className="text-right">
          <div className="font-mono text-[12px] tabular text-white/85">{time}</div>
          <div className="font-mono text-[9.5px] uppercase tracking-[0.16em] text-white/45">{date}</div>
        </div>
      </div>
    </div>
  );
}

function CommandPalette({ open, onClose, onJump }) {
  const [q, setQ] = useState("");
  useEffect(() => {
    if (!open) setQ("");
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);
  if (!open) return null;
  const items = [
    ...NAV.map(n => ({ k: "jump", label: `Jump · ${n.label}`, hint: n.k, action: () => onJump(n.id) })),
    { k: "run", label: "Run /daily-kickoff", hint: "ritual", action: () => onJump("command") },
    { k: "run", label: "Spawn agent · ledger.reconcile", hint: "agent", action: () => onJump("agents") },
    { k: "search", label: "Memory · 'q3-plan'", hint: "memory", action: () => onJump("memory") },
    { k: "deploy", label: "Deploy main → prod", hint: "shipyard", action: () => onJump("deploy") },
    { k: "ask", label: `Ask ${(window.__DASH_DATA?.meta?.app_name || 'Hamdix')}: "${q || "summarize my day"}"`, hint: "concierge", action: () => onJump("command") },
  ].filter(it => it.label.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="fixed inset-0 z-50" onClick={onClose} style={{ background: "rgba(4,6,10,0.55)", backdropFilter: "blur(8px)" }}>
      <div className="absolute left-1/2 top-[18%] -translate-x-1/2 w-[640px] max-w-[92vw]" onClick={(e) => e.stopPropagation()}>
        <div className="glass ring-aurora p-2 rounded-[18px] shadow-[0_40px_120px_-40px_rgba(0,0,0,0.9)]">
          <div className="flex items-center gap-3 px-4 h-14 border-b border-white/[0.06]">
            <span className="text-cyan-glow"><Icon.Spark/></span>
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Run a command, jump to a screen, ask the assistant…" className="flex-1 text-[15px] placeholder:text-white/35"/>
            <Pill>esc</Pill>
          </div>
          <div className="max-h-[400px] overflow-y-auto py-2">
            {items.slice(0, 8).map((it, i) => (
              <button key={i} onClick={() => { it.action(); onClose(); }} className="w-full text-left px-4 py-3 rounded-[10px] hover:bg-white/[0.05] flex items-center gap-3 group">
                <Pill tone={it.k === 'run' ? 'cyan' : it.k === 'deploy' ? 'azure' : it.k === 'ask' ? 'pos' : 'default'}>{it.k}</Pill>
                <div className="text-[14px] text-white/85 flex-1">{it.label}</div>
                <div className="font-mono text-[10.5px] text-white/40 group-hover:text-cyan-glow transition">{it.hint}</div>
                <Icon.Arrow/>
              </button>
            ))}
            {items.length === 0 && (
              <div className="px-4 py-10 text-center text-white/45 text-[13px]">No matches. Press ↵ to ask the assistant.</div>
            )}
          </div>
          <div className="border-t border-white/[0.06] px-4 py-2 flex items-center gap-3 font-mono text-[10.5px] text-white/40 uppercase tracking-[0.14em]">
            <span><span className="text-cyan-glow">↵</span> open</span>
            <span><span className="text-cyan-glow">⌘ ↵</span> ask {(window.__DASH_DATA?.meta?.app_name || 'Hamdix').toLowerCase()}</span>
            <span><span className="text-cyan-glow">↑↓</span> nav</span>
            <span className="ml-auto">⌘K to close</span>
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================================================
   DESIGN SYSTEM SCREEN
   ========================================================= */
function DesignSystem() {
  const swatch = (label, val, c) => (
    <div className="glass-strong rounded-[12px] p-3">
      <div className="h-14 rounded-[8px] mb-2" style={{ background: c, boxShadow: c.includes("#5BD4FF") || c.includes("#5C7BFF") ? `inset 0 0 18px ${c}` : "inset 0 1px 0 rgba(255,255,255,0.06)" }}/>
      <div className="text-[12.5px] text-white/85">{label}</div>
      <div className="font-mono text-[10.5px] text-white/45 mt-0.5">{val}</div>
    </div>
  );
  return (
    <div className="space-y-7">
      <SectionHeader
        eyebrow="DESIGN SYSTEM"
        title={<>Cinematic Cyber Minimalism — <span className="font-serif italic">a system</span></>}
        sub="Tokens, type, motion, and component grammar for the hamdix operating layer. Tailwind + Livewire-compatible, dark-first, glass-native."
      />

      {/* Tokens */}
      <Card eyebrow="01 · COLOR" title="Surfaces & accents">
        <div className="grid grid-cols-6 gap-3">
          {swatch("ink/0",   "#06080D", "#06080D")}
          {swatch("ink/1",   "#0A0D14", "#0A0D14")}
          {swatch("ink/2",   "#0F131C", "#0F131C")}
          {swatch("ink/3",   "#161B26", "#161B26")}
          {swatch("line/06", "rgba(255,255,255,0.06)", "rgba(255,255,255,0.06)")}
          {swatch("line/12", "rgba(255,255,255,0.12)", "rgba(255,255,255,0.12)")}
          {swatch("cyan",    "#5BD4FF · oklch(.82 .13 220)", "#5BD4FF")}
          {swatch("azure",   "#5C7BFF · oklch(.66 .17 252)", "#5C7BFF")}
          {swatch("aurora",  "#9D7BFF · oklch(.66 .17 290)", "#9D7BFF")}
          {swatch("positive","#5EE7A8", "#5EE7A8")}
          {swatch("warn",    "#FFC56B", "#FFC56B")}
          {swatch("danger",  "#FF7A8A", "#FF7A8A")}
        </div>
      </Card>

      {/* Type */}
      <Card eyebrow="02 · TYPE" title="Geist · Geist Mono · Instrument Serif">
        <div className="grid grid-cols-12 gap-5">
          <div className="col-span-12 lg:col-span-7 space-y-4">
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-1">display · 56 / -0.03em / 500</div>
              <div className="text-[56px] tracking-[-0.03em] leading-[1.02] font-medium">A nervous system <span className="font-serif italic text-cyan-glow">for one person.</span></div>
            </div>
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-1">title · 34 / -0.02em / 500</div>
              <div className="text-[34px] tracking-[-0.02em] leading-[1.05] font-medium">Memory graph · q3-plan</div>
            </div>
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-1">body · 14.5 / 1.6 / 400</div>
              <div className="text-[14.5px] leading-[1.65] text-white/82 max-w-2xl">Indexed 142 messages from six chats. Three flagged as decisions, one drafted into a reply, two parked into the q3 subgraph for the Friday review.</div>
            </div>
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-1">caption · 11 mono / 0.16em uc / 500</div>
              <div className="font-mono text-[11px] uppercase tracking-[0.16em] text-white/55">REQUEST · ROUTED VIA CONCIERGE · 412MS</div>
            </div>
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-1">data · mono / tabular</div>
              <div className="font-mono text-[24px] tabular text-cyan-glow">$ 1.84 / hr · 142 tok/s · p99 412ms</div>
            </div>
          </div>

          <div className="col-span-12 lg:col-span-5 glass-strong rounded-[12px] p-5 space-y-4">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">scale</div>
            {[
              ["display",  56, -0.03, 500],
              ["title",    34, -0.02, 500],
              ["heading",  22, -0.02, 500],
              ["body lg",  16, -0.01, 400],
              ["body",     14.5, -0.01, 400],
              ["body sm",  13, -0.01, 400],
              ["caption",  11, 0.16,  500],
              ["data",     12, 0,     500],
            ].map((row, i) => (
              <div key={i} className="grid grid-cols-[120px_1fr_120px] items-baseline gap-3 text-white/85 border-b border-white/[0.04] pb-2 last:border-0">
                <div className="font-mono text-[11px] uppercase tracking-[0.14em] text-white/55">{row[0]}</div>
                <div style={{ fontSize: row[1], letterSpacing: `${row[2]}em`, fontWeight: row[3], fontFamily: row[0] === 'caption' || row[0] === 'data' ? 'Geist Mono' : 'Geist' }}>Aa · 01</div>
                <div className="font-mono text-[10.5px] text-white/40 text-right">{row[1]}px</div>
              </div>
            ))}
          </div>
        </div>
      </Card>

      {/* Spacing & radius */}
      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-7" eyebrow="03 · SPACING" title="Token scale">
          <div className="flex items-end gap-4">
            {[4,8,12,16,24,32,48,64,96].map(n => (
              <div key={n} className="flex flex-col items-center gap-2">
                <div className="bg-cyan-glow/20 border border-cyan-glow/40" style={{ width: n, height: n, borderRadius: 4 }}/>
                <div className="font-mono text-[10.5px] text-white/45">{n}</div>
              </div>
            ))}
          </div>
          <div className="mt-6 flex items-center gap-4">
            {[8,12,16,24,9999].map(r => (
              <div key={r} className="flex flex-col items-center gap-2">
                <div className="w-16 h-16 glass-strong border border-white/[0.10]" style={{ borderRadius: r === 9999 ? 99 : r }}/>
                <div className="font-mono text-[10.5px] text-white/45">{r === 9999 ? "full" : `${r}px`}</div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-5" eyebrow="04 · ELEVATION" title="Glass + glow">
          <div className="space-y-3">
            <div className="glass p-3 text-[12.5px]"><span className="text-white/55">surface · </span>glass · 22px blur · 4% white</div>
            <div className="glass-strong p-3 text-[12.5px]"><span className="text-white/55">surface · </span>glass-strong · 5.5% white</div>
            <div className="glass p-3 ring-aurora text-[12.5px]"><span className="text-white/55">accent · </span>ring-aurora · animated conic edge</div>
            <div className="glass underglow-cyan p-3 text-[12.5px]"><span className="text-white/55">accent · </span>underglow-cyan</div>
            <div className="glass underglow-azure p-3 text-[12.5px]"><span className="text-white/55">accent · </span>underglow-azure</div>
          </div>
        </Card>
      </div>

      {/* Components */}
      <Card eyebrow="05 · COMPONENTS" title="Core grammar">
        <div className="grid grid-cols-12 gap-5">
          <div className="col-span-12 lg:col-span-4 space-y-3">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">buttons</div>
            <div className="flex flex-wrap gap-2">
              <Btn tone="primary" icon={<Icon.Send/>}>Dispatch</Btn>
              <Btn tone="secondary">Secondary</Btn>
              <Btn>Ghost</Btn>
              <Btn icon={<Icon.Plus/>}>Add</Btn>
            </div>
          </div>
          <div className="col-span-12 lg:col-span-4 space-y-3">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">pills</div>
            <div className="flex flex-wrap gap-2">
              <Pill>default</Pill>
              <Pill tone="cyan"><LiveDot tone="cyan"/> live</Pill>
              <Pill tone="azure">routed</Pill>
              <Pill tone="pos"><LiveDot/> healthy</Pill>
              <Pill tone="warn">retrying</Pill>
              <Pill tone="danger">failed</Pill>
            </div>
          </div>
          <div className="col-span-12 lg:col-span-4 space-y-3">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">indicators</div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2"><LiveDot/> healthy</div>
              <div className="flex items-center gap-2"><LiveDot tone="cyan"/> active</div>
              <div className="flex items-center gap-2"><LiveDot tone="warn"/> warning</div>
              <Donut value={72} size={36} stroke={4} color="#5BD4FF"/>
              <Waveform bars={14}/>
            </div>
          </div>

          <div className="col-span-12 lg:col-span-6">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-2">stat tile</div>
            <StatTile label="QUEUED · NOW" value="142" sub="across 6 queues" tone="cyan" spark={genWalk(28)}/>
          </div>

          <div className="col-span-12 lg:col-span-6">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-2">prompt input</div>
            <div className="glass-strong rounded-[14px] p-4 border-white/[0.08] flex items-start gap-3 ring-aurora">
              <div className="w-9 h-9 rounded-[10px] grid place-items-center text-[#06080D] bg-cyan-glow shrink-0"><Icon.Spark/></div>
              <div className="flex-1">
                <div className="text-[14px] text-white/85">A unified composer for every command…</div>
                <div className="mt-2 flex gap-2"><Pill tone="cyan">/intent</Pill><Pill>memory</Pill></div>
              </div>
              <Btn tone="primary" icon={<Icon.Send/>}>Run</Btn>
            </div>
          </div>
        </div>
      </Card>

      {/* Motion rules */}
      <Card eyebrow="06 · MOTION" title="Rules of the room">
        <div className="grid grid-cols-3 gap-5 text-[13.5px] leading-[1.65]">
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Spring, not ease.</div>
            <div className="text-white/65">Layout transitions use a 12% stiffness, 78% damping spring. Nothing snaps; everything settles.</div>
          </div>
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Stagger reveals.</div>
            <div className="text-white/65">Cards enter on a 60–90ms cascade with a 6px fade-up + blur 6 → 0. The room composes itself.</div>
          </div>
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Pulse the live.</div>
            <div className="text-white/65">Anything streaming gets a 1.8s pulse ring + caret. Static is for finished states.</div>
          </div>
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Magnetic primaries.</div>
            <div className="text-white/65">Hero CTAs lean toward the cursor up to 8px and gain a cyan halo. Reserved for one action per surface.</div>
          </div>
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Cursor lighting.</div>
            <div className="text-white/65">A 360px cyan radial follows the pointer over the scene. Subtle — barely above the noise floor.</div>
          </div>
          <div>
            <div className="font-serif italic text-cyan-glow text-[18px] mb-1">Animate data, not chrome.</div>
            <div className="text-white/65">Numbers tween, sparklines walk, particles drift. Containers stay still so the eye trusts them.</div>
          </div>
        </div>
      </Card>

      {/* Architecture */}
      <Card eyebrow="07 · ARCHITECTURE" title="Tailwind + Livewire compose">
        <div className="grid grid-cols-12 gap-5 text-[13px]">
          <div className="col-span-12 lg:col-span-7 glass-strong rounded-[12px] p-5 font-mono text-[12px] leading-[1.7] whitespace-pre">
<span className="text-white/40">{`// resources/views/components/glass.blade.php`}</span>{`\n`}
<span>{`@props(['ring' => false, 'glow' => null])`}</span>{`\n\n`}
<span className="text-cyan-glow">{`<div`}</span>{` `}<span className="text-[#FFC56B]">{`{{ $attributes->merge([`}</span>{`\n`}
<span>{`  'class' => 'glass p-5 '`}</span>{`\n`}
<span>{`    .($ring ? 'ring-aurora ' : '')`}</span>{`\n`}
<span>{`    .($glow ? 'underglow-'.$glow : '')`}</span>{`\n`}
<span>{`]) }}`}</span><span className="text-cyan-glow">{`>`}</span>{`\n`}
<span>{`  {{ $slot }}`}</span>{`\n`}
<span className="text-cyan-glow">{`</div>`}</span>
          </div>
          <div className="col-span-12 lg:col-span-5 space-y-3">
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">component hierarchy</div>
            {[
              ["<x-shell>",         "topbar + sidebar + scene-bg"],
              ["<x-glass>",         "card · ring · glow"],
              ["<x-pill>",          "tone · live"],
              ["<x-stat-tile>",     "label · value · spark"],
              ["<x-live-dot>",      "tone-aware pulse"],
              ["<x-prompt>",        "intent input · streaming"],
              ["<x-trace>",         "wired waterfall row"],
              ["<x-graph-canvas>",  "memory · workflow"],
              ["livewire://stream", "server-sent updates"],
            ].map((r, i) => (
              <div key={i} className="flex items-center justify-between border-b border-white/[0.04] pb-1.5">
                <div className="font-mono text-[12px] text-cyan-glow">{r[0]}</div>
                <div className="text-[12px] text-white/55">{r[1]}</div>
              </div>
            ))}
          </div>
        </div>
      </Card>
    </div>
  );
}

window.Sidebar = Sidebar;
window.TopBar = TopBar;
window.CommandPalette = CommandPalette;
window.DesignSystem = DesignSystem;
window.NAV = NAV;
window.NAV_MAP = NAV_MAP;
