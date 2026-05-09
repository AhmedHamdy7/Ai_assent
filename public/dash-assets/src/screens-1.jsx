// Screens 1-3: Command Center, Agent (Tools) Monitoring, Memory Graph — wired to live project data
const { useState, useEffect, useRef, useMemo } = React;

const __D = () => (window.__DASH_DATA || {});

/* =========================================================
   SCREEN 1 — COMMAND CENTER
   ========================================================= */
function CommandCenter() {
  const D = __D();
  const meta     = D.meta || {};
  const overview = D.overview || {};
  const cost     = D.cost || {};
  const tasks    = D.tasks || {};
  const agents   = D.agents || {};

  const messagesToday = overview.messages_today || 0;
  const series = (overview.messages_series || []).map(r => r.count || 0);
  const heroSpark = useMemo(() => series.length ? series.concat(series).concat(series) : genWalk(40, 0.5, 6), [series.join(',')]);

  const ctxFill = useTicker(63, { min: 50, max: 78, step: 1, period: 1100 });
  const reply = useTyper(
    `${meta.app_name || 'hamdix'} is online · provider ${meta.provider || '—'} · model ${meta.model || '—'}. Today: ${messagesToday} messages, ${tasks.in_progress || 0} task${tasks.in_progress === 1 ? '' : 's'} in progress, $${(cost.today || 0).toFixed(2)} spent. ${overview.reminders_active || 0} active reminders. Standing by.`,
    { speed: 14 }
  );

  const intents = [
    { k: "Create a new reminder via Telegram", t: "/remind" },
    { k: "Summarize today's messages", t: "/summary" },
    { k: "Add a learning track", t: "/learn" },
    { k: "Log an expense", t: "/expense" },
  ];

  const recents = (overview.recent_activity || []).slice(0, 6);

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="00 · COMMAND"
        title={<>Welcome back. The <span className="font-serif italic text-white/85">assistant</span> is listening.</>}
        sub={`Your Telegram-native AI workforce — ${agents.tool_count || 0} tools, live messaging, memory of every session.`}
        right={
          <div className="flex items-center gap-2">
            <Pill tone="pos"><LiveDot/> {meta.env?.toUpperCase() || 'LOCAL'}</Pill>
            <Pill tone="cyan">laravel {meta.laravel || ''}</Pill>
          </div>
        }
      />

      <Stagger delay={80}>
        <div className="grid grid-cols-12 gap-5">
          <div className="col-span-12 lg:col-span-8 glass ring-aurora p-7 underglow-cyan relative overflow-hidden">
            <div className="absolute inset-0 pointer-events-none" style={{ background: "radial-gradient(600px 240px at 20% 0%, rgba(91,212,255,0.10), transparent 60%)" }}/>
            <div className="flex items-start justify-between relative">
              <div>
                <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-cyan-glow/80 flex items-center gap-2"><LiveDot tone="cyan"/> conductor · live stream</div>
                <h2 className="text-[28px] tracking-[-0.02em] mt-2 font-medium leading-tight">A unified surface for the entire <span className="font-serif italic text-cyan-glow">operating layer</span>.</h2>
              </div>
              <div className="flex items-center gap-3">
                <div className="flex items-center gap-2 text-[11px] font-mono text-white/55">
                  <span>msg/min</span>
                  <span className="text-cyan-glow tabular text-[15px]">{(D.telegram?.messages_per_min || 0).toFixed(1)}</span>
                </div>
                <Waveform />
              </div>
            </div>

            <div className="mt-6 glass-strong rounded-[14px] p-4 border-white/[0.08] flex items-start gap-3 ring-aurora">
              <div className="w-9 h-9 rounded-[10px] grid place-items-center text-[#06080D] bg-cyan-glow shrink-0" style={{ boxShadow: "0 0 24px -4px #5BD4FF" }}><Icon.Spark/></div>
              <div className="flex-1">
                <div className="font-mono text-[10.5px] tracking-[0.16em] uppercase text-white/40 mb-1.5">prompt · {meta.model || 'ollama'} · routed via orchestrator</div>
                <div className="text-[14.5px] text-white/85">Triage Telegram messages, summarize sessions, plan one focus block, and prepare a 5-bullet brief.</div>
                <div className="mt-3 flex items-center gap-2">
                  <Pill tone="cyan">/daily-kickoff</Pill>
                  <Pill>tools: {agents.tool_count || 0}</Pill>
                  <Pill>sessions: {overview.sessions_active || 0}</Pill>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button className="h-8 w-8 grid place-items-center rounded-md hover:bg-white/[0.06]"><Icon.Pause/></button>
                <Btn tone="primary" icon={<Icon.Send/>}>Dispatch</Btn>
              </div>
            </div>

            <div className="mt-5 flex gap-3">
              <div className="w-1 rounded-full" style={{ background: "linear-gradient(#5BD4FF, transparent)" }}/>
              <div className="flex-1">
                <div className="font-mono text-[10.5px] tracking-[0.16em] uppercase text-white/40 mb-2 flex items-center gap-2">
                  <span>response · streaming</span>
                  <Pill tone="cyan">ACTIVE</Pill>
                </div>
                <div className="text-[14.5px] leading-[1.65] text-white/82 max-w-3xl">
                  {reply}<span className="caret"/>
                </div>
              </div>
            </div>

            <div className="mt-7 grid grid-cols-2 gap-2">
              {intents.map((i, k) => (
                <Magnet key={k}>
                  <div className="group flex items-center justify-between glass-strong rounded-[12px] px-3.5 py-3 border border-white/[0.08] hover:border-cyan-glow/40 cursor-pointer transition">
                    <div className="flex items-center gap-3">
                      <div className="w-1.5 h-1.5 rounded-full bg-cyan-glow" style={{ boxShadow: "0 0 8px #5BD4FF" }}/>
                      <div className="text-[13px] text-white/85">{i.k}</div>
                    </div>
                    <div className="font-mono text-[10px] text-white/45 group-hover:text-cyan-glow transition">{i.t}</div>
                  </div>
                </Magnet>
              ))}
            </div>
          </div>

          <div className="col-span-12 lg:col-span-4 space-y-5">
            <div className="glass p-5 ring-aurora">
              <div className="flex items-center justify-between">
                <div>
                  <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/40">today · messages</div>
                  <div className="text-[34px] tracking-[-0.02em] font-medium mt-1">
                    <AnimatedNumber value={messagesToday} format={(v) => fmt.short(v)}/>
                    <span className="text-[16px] text-white/40 ml-1">msgs</span>
                  </div>
                </div>
                <Donut value={ctxFill} size={72} stroke={6} color="#5BD4FF" label={<div className="font-mono text-[12px] tabular">{ctxFill.toFixed(0)}<span className="text-[10px] text-white/45">%</span></div>}/>
              </div>
              <AreaChart data={heroSpark} h={88} />
              <div className="grid grid-cols-3 gap-3 mt-3 font-mono text-[11px]">
                <div><div className="text-white/45">sessions</div><div className="text-cyan-glow tabular">{overview.sessions_active || 0}</div></div>
                <div><div className="text-white/45">open tasks</div><div className="text-white tabular">{overview.tasks_open || 0}</div></div>
                <div><div className="text-white/45">spend wk</div><div className="text-white tabular">${(overview.expense_week || 0).toFixed(2)}</div></div>
              </div>
            </div>

            <div className="glass p-5">
              <div className="flex items-center justify-between mb-3">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/40">activity · messages</div>
                <Pill tone="cyan"><LiveDot tone="cyan"/> LIVE</Pill>
              </div>
              <div className="space-y-2.5">
                {recents.length === 0 && <div className="text-[12.5px] text-white/45 italic">No messages yet — send something to your bot.</div>}
                {recents.map((r, i) => (
                  <div key={i} className="flex items-center gap-3">
                    <LiveDot tone={r.tone}/>
                    <div className="flex-1 min-w-0">
                      <div className="text-[13px] text-white/85"><span className="font-mono text-cyan-glow/90">{r.who}</span> <span className="text-white/55">· {r.what}</span></div>
                    </div>
                    <div className="font-mono text-[10.5px] text-white/40">{r.t}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-12 gap-5">
          <Card className="col-span-12 lg:col-span-7" eyebrow="ARRAY" title={`Tool fleet · ${agents.tool_count || 0} registered`} right={<div className="flex gap-2"><Pill tone="pos">{agents.total_24h || 0} calls 24h</Pill></div>}>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
              {(agents.tools || []).slice(0, 9).map((a, i) => (
                <div key={i} className="glass-strong p-3 rounded-[12px]">
                  <div className="flex items-center justify-between">
                    <div className="text-[13px] truncate" title={a.name}>{a.name}</div>
                    <LiveDot tone={a.tone}/>
                  </div>
                  <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/45 mt-0.5">{a.role}</div>
                  <div className="mt-3 h-1 rounded-full bg-white/[0.05] overflow-hidden">
                    <div className="h-full" style={{
                      width: `${Math.max(4, a.load)}%`,
                      background: a.tone === "warn" ? "linear-gradient(90deg, #FFC56B33, #FFC56B)" :
                                  a.tone === "azure" ? "linear-gradient(90deg, #5C7BFF33, #5C7BFF)" :
                                  a.tone === "pos" ? "linear-gradient(90deg, #5EE7A833, #5EE7A8)" :
                                  a.tone === "aurora" ? "linear-gradient(90deg, #9D7BFF33, #9D7BFF)" :
                                  "linear-gradient(90deg, #5BD4FF33, #5BD4FF)"
                    }}/>
                  </div>
                  <div className="font-mono text-[10px] text-white/40 mt-1.5">{a.calls_24h || 0} calls · 24h</div>
                </div>
              ))}
            </div>
          </Card>

          <Card className="col-span-12 lg:col-span-5" eyebrow="SIGNAL" title="Inbound signals" right={<Pill tone="cyan"><LiveDot tone="cyan"/> {(D.telegram?.messages_per_min || 0).toFixed(1)}/min</Pill>}>
            <div className="space-y-3 max-h-[260px] overflow-hidden relative">
              {(overview.recent_activity || []).slice(0, 7).map((s, i) => (
                <div key={i} className="flex items-center gap-3 py-1.5 border-b border-white/[0.04] last:border-0">
                  <div className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/45 w-20 truncate">{s.who}</div>
                  <div className="flex-1 text-[13px] text-white/82 truncate">{s.what}</div>
                  <LiveDot tone={s.tone}/>
                </div>
              ))}
              <div className="absolute bottom-0 left-0 right-0 h-12 pointer-events-none" style={{ background: "linear-gradient(transparent, #0A0D14)" }}/>
            </div>
          </Card>
        </div>
      </Stagger>
    </div>
  );
}

/* =========================================================
   SCREEN 2 — TOOL / AGENT MONITORING
   ========================================================= */
function AgentMonitoring() {
  const D = __D();
  const tools = (D.agents?.tools) || [];
  const [sel, setSel] = useState(0);
  const a = tools[sel] || { name: '—', role: '—', description: '—', event: '—', tone: 'cyan', load: 0, calls_24h: 0, status: 'idle' };

  const trace = (D.overview?.recent_activity || []).slice(0, 7).map((r, i) => ({
    t: `+${(i * 0.12).toFixed(3)}s`,
    k: r.role || 'msg',
    v: `${r.who} · ${r.what}`,
  }));

  const cpu = useTicker(54, { step: 3, period: 700 });
  const mem = useTicker(67, { step: 2, period: 900 });
  const traffic = useMemo(() => genWalk(60, 0.5, 8), []);

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="01 · ORCHESTRATION"
        title="Tool monitoring"
        sub={`${tools.length} tools registered. Each call billed and traced through the orchestrator.`}
        right={
          <div className="flex items-center gap-2">
            <Pill tone="pos"><LiveDot/> {tools.length}/{tools.length} ONLINE</Pill>
            <Pill tone="cyan">{D.agents?.total_24h || 0} calls 24h</Pill>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-4" eyebrow="FLEET" title="Registered tools">
          <div className="space-y-1.5 max-h-[600px] overflow-y-auto pr-1">
            {tools.map((g, i) => (
              <button key={i} onClick={() => setSel(i)}
                className={`w-full text-left rounded-[12px] px-3 py-2.5 flex items-center gap-3 border transition reveal ${i === sel ? 'bg-white/[0.06] border-cyan-glow/40 shadow-glow-cyan' : 'border-white/[0.05] hover:bg-white/[0.04]'}`}
                style={{ animationDelay: `${i * 50}ms` }}>
                <div className="relative">
                  <div className="w-9 h-9 rounded-[10px] glass-strong grid place-items-center"><Icon.Bot/></div>
                  <span className="absolute -bottom-0.5 -right-0.5"><LiveDot tone={g.tone}/></span>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-[13.5px] text-white/90 truncate">{g.name}</div>
                  <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/45">{g.role}</div>
                </div>
                <div className="text-right">
                  <div className="font-mono text-[11px] text-white/85 tabular">{g.calls_24h || 0}<span className="text-white/40"> /24h</span></div>
                  <div className="font-mono text-[10px] text-white/45">{g.load}%</div>
                </div>
              </button>
            ))}
            {tools.length === 0 && <div className="px-3 py-6 text-[12.5px] text-white/45 italic text-center">No tools registered.</div>}
          </div>
        </Card>

        <div className="col-span-12 lg:col-span-8 space-y-5">
          <div className="glass p-6 ring-aurora">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 rounded-2xl glass-strong grid place-items-center" style={{ boxShadow: "0 0 30px -6px #5BD4FF" }}>
                  <Icon.Bot/>
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <div className="text-[24px] tracking-[-0.02em] font-medium">{a.name}</div>
                    <Pill tone={a.tone}><LiveDot tone={a.tone}/> {(a.status || 'idle').toUpperCase()}</Pill>
                  </div>
                  <div className="font-mono text-[11px] uppercase tracking-[0.16em] text-white/45 mt-0.5">{a.role}</div>
                  <div className="text-[13.5px] text-white/70 mt-2 italic max-w-2xl">"{a.description}"</div>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Btn tone="secondary" icon={<Icon.Code/>}>Inspect</Btn>
              </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
              <div>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">cpu</div>
                <div className="text-[22px] mt-1 font-medium tabular">{cpu.toFixed(0)}%</div>
                <div className="h-1 rounded-full bg-white/[0.05] mt-2"><div className="h-full rounded-full" style={{ width: `${cpu}%`, background: "linear-gradient(90deg, #5BD4FF33, #5BD4FF)" }}/></div>
              </div>
              <div>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">memory</div>
                <div className="text-[22px] mt-1 font-medium tabular">{mem.toFixed(0)}%</div>
                <div className="h-1 rounded-full bg-white/[0.05] mt-2"><div className="h-full rounded-full" style={{ width: `${mem}%`, background: "linear-gradient(90deg, #5C7BFF33, #5C7BFF)" }}/></div>
              </div>
              <div>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">calls · 24h</div>
                <div className="text-[22px] mt-1 font-medium tabular">{a.calls_24h || 0}</div>
                <div className="font-mono text-[10.5px] text-white/45 mt-1">event: {a.event}</div>
              </div>
              <div>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">load share</div>
                <div className="text-[22px] mt-1 font-medium tabular">{a.load || 0}%</div>
                <div className="font-mono text-[10.5px] text-white/45 mt-1">of 24h volume</div>
              </div>
            </div>

            <div className="mt-5">
              <div className="flex items-center justify-between mb-2">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">throughput · last 60s · synthetic</div>
              </div>
              <AreaChart data={traffic} h={120}/>
            </div>
          </div>

          <Card eyebrow="TRACE" title="Recent message stream" right={<Pill tone="cyan"><LiveDot tone="cyan"/> recording</Pill>}>
            <div className="space-y-2 font-mono text-[12px]">
              {trace.length === 0 && <div className="text-white/45 italic">No recent activity.</div>}
              {trace.map((row, i) => (
                <div key={i} className="grid grid-cols-[80px_120px_1fr] gap-3 items-center reveal" style={{ animationDelay: `${i * 80}ms` }}>
                  <div className="text-white/40 tabular">{row.t}</div>
                  <Pill tone={row.k === 'tool' ? 'warn' : row.k === 'assistant' ? 'azure' : row.k === 'user' ? 'cyan' : 'default'}>{row.k}</Pill>
                  <div className="text-white/85 truncate">{row.v}</div>
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 3 — MEMORY GRAPH (project entities)
   ========================================================= */
function MemoryGraph() {
  const D = __D();
  const mem = D.memory || { width: 760, height: 460, nodes: [], edges: [] };
  const W = mem.width, H = mem.height;
  const nodes = mem.nodes || [];
  const edges = mem.edges || [];

  const [hover, setHover] = useState(null);
  const [pulse, setPulse] = useState(0);
  useEffect(() => { const id = setInterval(() => setPulse(p => (p + 1) % 1000), 90); return () => clearInterval(id); }, []);

  const colorOf = (tone) => ({ cyan: "#5BD4FF", azure: "#5C7BFF", warn: "#FFC56B", pos: "#5EE7A8", default: "rgba(255,255,255,0.7)" })[tone];
  const node = (id) => nodes.find(n => n.id === id);

  const sessionsTotal = D.sessions?.total || 0;
  const tasksOpen = D.overview?.tasks_open || 0;
  const remindersActive = D.overview?.reminders_active || 0;
  const learningTotal = D.learning?.total_tracks || 0;

  const stats = [
    { k: "sessions",   v: String(sessionsTotal) },
    { k: "tasks open", v: String(tasksOpen) },
    { k: "reminders",  v: String(remindersActive) },
    { k: "tracks",     v: String(learningTotal) },
  ];

  const journal = [
    ['+', `${D.overview?.messages_today || 0} messages today`, 'cyan'],
    ['~', `${D.tasks?.in_progress || 0} task(s) in progress`, 'azure'],
    ['+', `${D.reminders?.daily || 0} daily reminders armed`, 'pos'],
    ['+', `${D.learning?.lessons_done || 0}/${D.learning?.lessons_total || 0} lessons completed`, 'cyan'],
    ['~', `$${(D.cost?.month || 0).toFixed(2)} spent this month`, 'warn'],
  ];

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="02 · MEMORY"
        title="Memory graph"
        sub="A live map of your assistant's world — sessions, tasks, reminders, learning tracks, and expenses."
        right={
          <div className="flex items-center gap-2">
            <div className="glass-strong rounded-[10px] flex items-center gap-2 px-3 h-9">
              <Icon.Search/>
              <input className="bg-transparent text-[13px] placeholder:text-white/35 w-56" placeholder={`search · ${nodes.length} nodes`} />
            </div>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-8 overflow-hidden" eyebrow="GRAPH" title="root subgraph · live" right={<Pill tone="cyan"><LiveDot tone="cyan"/> {nodes.length} NODES</Pill>}>
          <div className="relative h-[480px] rounded-[14px] overflow-hidden" style={{
            background: "radial-gradient(620px 360px at 40% 50%, rgba(91,212,255,0.06), transparent 70%), linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0))",
            border: "1px solid rgba(255,255,255,0.05)",
          }}>
            <svg viewBox={`0 0 ${W} ${H}`} className="absolute inset-0 w-full h-full">
              <defs>
                <radialGradient id="halo" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stopColor="#5BD4FF" stopOpacity="0.22"/>
                  <stop offset="100%" stopColor="#5BD4FF" stopOpacity="0"/>
                </radialGradient>
              </defs>
              <circle cx={W/2} cy={H/2} r="160" fill="url(#halo)"/>
              {edges.map(([a, b], i) => {
                const A = node(a), B = node(b);
                if (!A || !B) return null;
                const dash = (i + pulse) % 28;
                return <line key={i} x1={A.x} y1={A.y} x2={B.x} y2={B.y} stroke="rgba(91,212,255,0.18)" strokeWidth="1" strokeDasharray="2 6" strokeDashoffset={-dash}/>;
              })}
              {nodes.map(n => (
                <g key={n.id} onMouseEnter={() => setHover(n.id)} onMouseLeave={() => setHover(null)} style={{ cursor: 'pointer' }}>
                  <circle cx={n.x} cy={n.y} r={n.r + 6} fill={colorOf(n.tone)} fillOpacity="0.10"/>
                  <circle cx={n.x} cy={n.y} r={n.r} fill="#0F131C" stroke={colorOf(n.tone)} strokeWidth="1.5" style={{ filter: `drop-shadow(0 0 8px ${colorOf(n.tone)})` }}/>
                  {n.id === 'self' && <circle cx={n.x} cy={n.y} r={n.r-6} fill={colorOf(n.tone)} fillOpacity="0.4"/>}
                  <text x={n.x} y={n.y + n.r + 14} fontFamily="Geist Mono" fontSize="10" fill={hover === n.id ? "#fff" : "rgba(255,255,255,0.55)"} textAnchor="middle" style={{ letterSpacing: 1 }}>{n.label}</text>
                </g>
              ))}
            </svg>

            <div className="absolute bottom-3 left-3 flex gap-3 font-mono text-[10.5px] uppercase tracking-[0.14em]">
              <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-cyan-glow" style={{ boxShadow: "0 0 6px #5BD4FF" }}/>self</span>
              <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full" style={{ background: "#5C7BFF", boxShadow: "0 0 6px #5C7BFF" }}/>context</span>
              <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full" style={{ background: "#FFC56B", boxShadow: "0 0 6px #FFC56B" }}/>task</span>
              <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full" style={{ background: "#5EE7A8", boxShadow: "0 0 6px #5EE7A8" }}/>reminder</span>
            </div>

            {hover && node(hover) && (
              <div className="absolute top-3 right-3 glass p-3 w-60 ring-aurora">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">node · {node(hover).t}</div>
                <div className="text-[15px] mt-0.5">{node(hover).label}</div>
                <div className="text-[12px] text-white/55 mt-1">connected to {edges.filter(e => e[0] === hover || e[1] === hover).length} entities</div>
              </div>
            )}
          </div>
        </Card>

        <div className="col-span-12 lg:col-span-4 space-y-5">
          <Card eyebrow="TELEMETRY" title="Subgraph health">
            <div className="grid grid-cols-2 gap-3">
              {stats.map((s, i) => (
                <div key={i} className="glass-strong rounded-[12px] p-3">
                  <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/45">{s.k}</div>
                  <div className="text-[20px] mt-1 tabular">{s.v}</div>
                </div>
              ))}
            </div>
            <div className="mt-4">
              <div className="flex items-center justify-between mb-1.5">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/45">learning · completion</div>
                <div className="font-mono text-[11px] text-cyan-glow tabular">{D.learning?.lessons_pct || 0}%</div>
              </div>
              <div className="h-1.5 rounded-full bg-white/[0.05]"><div className="h-full rounded-full" style={{ width: `${D.learning?.lessons_pct || 0}%`, background: "linear-gradient(90deg, #5BD4FF33, #5BD4FF)" }}/></div>
            </div>
          </Card>

          <Card eyebrow="JOURNAL" title="Today's signals">
            <div className="space-y-2.5">
              {journal.map((r, i) => (
                <div key={i} className="flex items-center gap-3 text-[13px]">
                  <span className={`font-mono w-4 text-center ${r[2] === 'cyan' ? 'text-cyan-glow' : r[2] === 'azure' ? 'text-[#A8B7FF]' : r[2] === 'warn' ? 'text-[#FFC56B]' : r[2] === 'pos' ? 'text-[#5EE7A8]' : 'text-white/55'}`}>{r[0]}</span>
                  <div className="flex-1 text-white/82 truncate">{r[1]}</div>
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

window.CommandCenter = CommandCenter;
window.AgentMonitoring = AgentMonitoring;
window.MemoryGraph = MemoryGraph;
