// Screens 7-10: API Playground, Workflow Builder, Deployment Center, Productivity Hub
const { useState, useEffect, useRef, useMemo } = React;

const __D = () => (window.__DASH_DATA || {});

/* =========================================================
   SCREEN 7 — API PLAYGROUND (Telegram webhook + tools)
   ========================================================= */
function ApiPlayground() {
  const D = __D();
  const meta = D.meta || {};
  const tools = D.agents?.tools || [];
  const [tab, setTab] = useState("body");
  const [streaming, setStreaming] = useState(false);

  const sample = JSON.stringify({
    update_id: 123456789,
    message: {
      message_id: 42,
      chat: { id: 12345, type: "private" },
      from: { id: 12345, first_name: "User" },
      date: Math.floor(Date.now() / 1000),
      text: "/remind me to drink water in 30 minutes",
    },
  }, null, 2);

  const responseText = useTyper(
    streaming
      ? `{\n  "ok": true,\n  "provider": "${meta.provider}",\n  "model": "${meta.model}",\n  "session_id": ${D.sessions?.list?.[0]?.id || 1},\n  "tools_available": ${tools.length},\n  "queued_jobs": ${D.queue?.total_depth || 0}\n}`
      : "",
    { speed: 8 }
  );

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="06 · DEVTOOLS"
        title="API playground"
        sub="Compose against the Telegram webhook or any registered tool."
        right={
          <div className="flex items-center gap-2">
            <Pill tone="cyan">workspace · {meta.env}</Pill>
            <Btn icon={<Icon.Code/>}>cURL</Btn>
            <Btn icon={<Icon.Plus/>}>Save preset</Btn>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-7" eyebrow="REQUEST" title={`POST /${meta.webhook || 'telegram/webhook'}`} right={<Pill tone="pos">200 · webhook</Pill>}>
          <div className="glass-strong rounded-[12px] p-1 flex items-center gap-1">
            <span className="px-3 py-1.5 rounded-md bg-cyan-glow/15 text-cyan-glow font-mono text-[12px] tracking-wider">POST</span>
            <input
              defaultValue={`/${meta.webhook || 'telegram/webhook'}`}
              className="flex-1 px-2 font-mono text-[13px]"/>
            <Btn tone="primary" icon={<Icon.Send/>} onClick={() => setStreaming(true)}>{streaming ? "Streaming…" : "Send"}</Btn>
          </div>

          <div className="mt-4 border-b border-white/[0.06] flex gap-5">
            {["body","headers","auth","tools","traces"].map(t => (
              <button key={t} onClick={() => setTab(t)} className={`relative pb-2 font-mono text-[11.5px] uppercase tracking-[0.14em] ${tab === t ? 'text-cyan-glow' : 'text-white/45 hover:text-white/85'}`}>
                {t}
                {tab === t && <span className="absolute -bottom-px left-0 right-0 h-px bg-cyan-glow" style={{ boxShadow: "0 0 8px #5BD4FF" }}/>}
              </button>
            ))}
          </div>

          {tab === "body" && (
            <div className="mt-4 glass-strong rounded-[12px] p-4 font-mono text-[12.5px] leading-[1.7] whitespace-pre-wrap text-white/85">
              {sample}
            </div>
          )}
          {tab === "tools" && (
            <div className="mt-4 grid grid-cols-2 gap-2">
              {tools.map((t, i) => (
                <div key={i} className="glass-strong rounded-[10px] p-3">
                  <div className="font-mono text-[12px] text-cyan-glow truncate">{t.name}</div>
                  <div className="text-[12px] text-white/65 mt-1 line-clamp-2">{t.description}</div>
                  <div className="font-mono text-[10.5px] text-white/40 mt-1.5">event: {t.event}</div>
                </div>
              ))}
            </div>
          )}
          {tab === "headers" && (
            <div className="mt-4 glass-strong rounded-[12px] p-4 font-mono text-[12.5px] leading-[1.7] whitespace-pre">
              {`Content-Type: application/json\nX-Telegram-Bot-Api-Secret-Token: ********`}
            </div>
          )}
          {tab === "auth" && (
            <div className="mt-4 glass-strong rounded-[12px] p-4 font-mono text-[12.5px] text-white/75">Webhook secret token validation in TelegramWebhookController.</div>
          )}
          {tab === "traces" && (
            <div className="mt-4 glass-strong rounded-[12px] p-4 font-mono text-[12.5px] text-white/75">Job: ProcessTelegramUpdate · Orchestrator → tool → AiMessage</div>
          )}

          <div className="flex items-center gap-2 mt-4">
            <Pill tone="cyan">⌘ ↵ to send</Pill>
            <Pill>Laravel {meta.laravel}</Pill>
            <Pill>PHP {meta.php}</Pill>
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-5" eyebrow="RESPONSE" title="streaming · synthetic" right={<div className="flex gap-2"><Pill tone={streaming ? "cyan" : "default"}>{streaming ? <><LiveDot tone="cyan"/> live</> : "idle"}</Pill></div>}>
          <div className="glass-strong rounded-[12px] p-4 font-mono text-[12px] leading-[1.65] min-h-[260px] whitespace-pre-wrap">
            {streaming ? <>{responseText}<span className="caret"/></> : <span className="text-white/40">{`// click "Send" to stream a synthetic response`}</span>}
          </div>
          <div className="grid grid-cols-3 gap-3 mt-4 font-mono text-[11px]">
            <div><div className="text-white/45">tools</div><div className="text-cyan-glow tabular">{tools.length}</div></div>
            <div><div className="text-white/45">sessions</div><div className="text-white tabular">{D.sessions?.total || 0}</div></div>
            <div><div className="text-white/45">queue</div><div className="text-white tabular">{D.queue?.total_depth || 0}</div></div>
          </div>
        </Card>

        <Card className="col-span-12" eyebrow="TRACE" title="Webhook waterfall · synthetic" right={<Pill tone="cyan">Orchestrator pipeline</Pill>}>
          <div className="space-y-2.5">
            {[
              { label: `POST /${meta.webhook}`, color: "#5BD4FF", start: 0, dur: 12 },
              { label: "validate secret token",   color: "#5C7BFF", start: 4, dur: 8 },
              { label: "ProcessTelegramUpdate (job)", color: "#5BD4FF", start: 14, dur: 24 },
              { label: "AiSession resolve / create", color: "#9D7BFF", start: 38, dur: 80 },
              { label: "Orchestrator → tool dispatch", color: "#FFC56B", start: 118, dur: 180 },
              { label: `${meta.provider}.${meta.model} · stream`, color: "#5EE7A8", start: 312, dur: 100 },
            ].map((r, i) => (
              <div key={i} className="grid grid-cols-[260px_1fr_80px] items-center gap-3 reveal" style={{ animationDelay: `${i * 60}ms` }}>
                <div className="font-mono text-[11.5px] text-white/75 truncate">{r.label}</div>
                <div className="relative h-5 bg-white/[0.04] rounded-full overflow-hidden">
                  <div className="absolute top-0 bottom-0 rounded-full" style={{
                    left: `${(r.start / 412) * 100}%`,
                    width: `${(r.dur / 412) * 100}%`,
                    background: `linear-gradient(90deg, ${r.color}66, ${r.color})`,
                    boxShadow: `0 0 12px -2px ${r.color}`,
                  }}/>
                </div>
                <div className="font-mono text-[11px] text-right text-white/70 tabular">{r.dur}ms</div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 8 — WORKFLOW BUILDER (Orchestrator visualization)
   ========================================================= */
function WorkflowBuilder() {
  const D = __D();
  const W = 880, H = 420;

  const tools = D.agents?.tools || [];

  const nodes = [
    { id: "trigger", x: 60,  y: 60,  w: 200, h: 78, kind: "trigger", title: "Telegram message", sub: "from any watched chat", tone: "cyan" },
    { id: "queue",   x: 320, y: 60,  w: 200, h: 78, kind: "queue",   title: "ProcessTelegramUpdate", sub: "laravel queue · idempotent", tone: "azure" },
    { id: "session", x: 580, y: 60,  w: 200, h: 78, kind: "session", title: "Resolve AiSession", sub: "by telegram_chat_id", tone: "aurora" },
    { id: "orchestrator",  x: 320, y: 200, w: 200, h: 78, kind: "agent",   title: "Orchestrator", sub: "ollama · tool routing", tone: "cyan" },
    { id: "tool",    x: 580, y: 200, w: 200, h: 78, kind: "tool",    title: "Tool dispatch", sub: `${tools.length} registered`, tone: "warn" },
    { id: "store",   x: 60,  y: 200, w: 200, h: 78, kind: "store",   title: "Persist AiMessage", sub: "user · assistant · tool", tone: "azure" },
    { id: "send",    x: 580, y: 320, w: 200, h: 78, kind: "send",    title: "Reply on Telegram", sub: "bot.sendMessage", tone: "pos" },
  ];

  const edges = [
    ["trigger","queue"],
    ["queue","session"],
    ["session","orchestrator"],
    ["orchestrator","tool"],
    ["tool","send"],
    ["orchestrator","store"],
  ];

  const colorOf = (tone) => ({ cyan: "#5BD4FF", azure: "#5C7BFF", aurora: "#9D7BFF", warn: "#FFC56B", pos: "#5EE7A8" })[tone];
  const node = (id) => nodes.find(n => n.id === id);

  const palette = [
    { k: "Trigger",  items: ["Telegram message","Cron schedule","Webhook"], tone: "cyan" },
    { k: "Logic",    items: ["If / else","Switch","Loop","Wait"], tone: "azure" },
    { k: "Tools",    items: tools.slice(0, 4).map(t => t.name), tone: "aurora" },
    { k: "Action",   items: ["Telegram reply","Save AiMessage","Persist task","Set reminder"], tone: "pos" },
  ];

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="07 · COMPOSE"
        title="Orchestrator flow"
        sub="The Telegram → Queue → Orchestrator → Tools pipeline that powers every reply."
        right={
          <div className="flex items-center gap-2">
            <Pill tone="pos">canonical · production flow</Pill>
            <Btn icon={<Icon.Play/>}>Test run</Btn>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-3" eyebrow="PALETTE" title="Drop in">
          <div className="space-y-4">
            {palette.map((p, i) => (
              <div key={i}>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45 mb-2">{p.k}</div>
                <div className="space-y-1.5">
                  {p.items.filter(Boolean).map((t, ti) => (
                    <div key={ti} className="glass-strong rounded-[10px] px-3 py-2 flex items-center gap-2 text-[12.5px] hover:border-white/[0.18] cursor-grab transition reveal" style={{ animationDelay: `${(i*4+ti) * 30}ms` }}>
                      <span className="w-1.5 h-1.5 rounded-full" style={{ background: colorOf(p.tone), boxShadow: `0 0 6px ${colorOf(p.tone)}` }}/>
                      <span className="text-white/82 truncate">{t}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-9 overflow-hidden" eyebrow="CANVAS" title="webhook → reply" right={<div className="flex gap-2"><Pill>v 1.0</Pill><Pill tone="cyan">{nodes.length} nodes · {edges.length} edges</Pill></div>}>
          <div className="relative h-[460px] rounded-[14px] overflow-hidden" style={{
            background: "radial-gradient(800px 320px at 30% 20%, rgba(91,212,255,0.05), transparent 60%)",
            border: "1px solid rgba(255,255,255,0.05)",
            backgroundImage: "radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px)",
            backgroundSize: "20px 20px",
          }}>
            <svg viewBox={`0 0 ${W} ${H}`} className="absolute inset-0 w-full h-full">
              {edges.map(([a,b], i) => {
                const A = node(a), B = node(b);
                const x1 = A.x + A.w, y1 = A.y + A.h/2;
                const x2 = B.x, y2 = B.y + B.h/2;
                const cx = (x1 + x2) / 2;
                const path = `M${x1} ${y1} C${cx} ${y1}, ${cx} ${y2}, ${x2} ${y2}`;
                return (
                  <g key={i}>
                    <path d={path} stroke={colorOf(B.tone)} strokeWidth="1.5" fill="none" opacity="0.4" />
                    <path d={path} stroke={colorOf(B.tone)} strokeWidth="1.5" fill="none" strokeDasharray="4 8" opacity="0.85" style={{ filter: `drop-shadow(0 0 4px ${colorOf(B.tone)})` }}>
                      <animate attributeName="stroke-dashoffset" from="0" to="-24" dur="1.2s" repeatCount="indefinite"/>
                    </path>
                    <circle cx={x2-4} cy={y2} r="3" fill={colorOf(B.tone)} style={{ filter: `drop-shadow(0 0 4px ${colorOf(B.tone)})` }}/>
                  </g>
                );
              })}
              {nodes.map(n => {
                const c = colorOf(n.tone);
                return (
                  <g key={n.id}>
                    <rect x={n.x} y={n.y} width={n.w} height={n.h} rx="14" fill="rgba(15,19,28,0.85)" stroke={c} strokeOpacity="0.55" />
                    <rect x={n.x} y={n.y} width={n.w} height={n.h} rx="14" fill="none" stroke={c} strokeOpacity="0.18" strokeWidth="3" style={{ filter: `blur(6px)` }}/>
                    <text x={n.x+16} y={n.y+22} fontFamily="Geist Mono" fontSize="9" fill="rgba(255,255,255,0.45)" letterSpacing="2">{n.kind.toUpperCase()}</text>
                    <text x={n.x+16} y={n.y+44} fontFamily="Geist" fontSize="14" fill="rgba(255,255,255,0.92)">{n.title}</text>
                    <text x={n.x+16} y={n.y+64} fontFamily="Geist" fontSize="11.5" fill="rgba(255,255,255,0.55)">{n.sub}</text>
                    <circle cx={n.x} cy={n.y+n.h/2} r="4" fill="#0F131C" stroke={c}/>
                    <circle cx={n.x+n.w} cy={n.y+n.h/2} r="4" fill={c} style={{ filter: `drop-shadow(0 0 4px ${c})` }}/>
                  </g>
                );
              })}
            </svg>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 font-mono text-[11px]">
            <div><div className="text-white/45">tools registered</div><div className="text-white tabular">{tools.length}</div></div>
            <div><div className="text-white/45">sessions</div><div className="text-[#5EE7A8] tabular">{D.sessions?.total || 0}</div></div>
            <div><div className="text-white/45">queue depth</div><div className="text-white tabular">{D.queue?.total_depth || 0}</div></div>
            <div><div className="text-white/45">failed jobs</div><div className="text-white tabular">{D.queue?.failed || 0}</div></div>
          </div>
        </Card>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 9 — DEPLOYMENT / SYSTEM CENTER
   ========================================================= */
function DeploymentCenter() {
  const D = __D();
  const meta = D.meta || {};

  const envs = [
    { name: meta.env || "local", url: window.location.host, region: "—", commit: "—", status: "healthy", cpu: 0, deployed: "—", tone: "pos" },
  ];

  const buildLog = useTyper(
    `▸ laravel ${meta.laravel} on PHP ${meta.php}\n▸ provider: ${meta.provider} · model: ${meta.model || '—'}\n▸ tools registered: ${D.agents?.tool_count || 0}\n▸ sessions: ${D.sessions?.total || 0}\n▸ queue driver: ${D.queue?.driver}\n▸ queue depth: ${D.queue?.total_depth || 0}\n▸ failed jobs: ${D.queue?.failed || 0}\n✓ webhook: /${meta.webhook}\n✓ system online`,
    { speed: 12 }
  );
  const cpu = useTicker(56, { step: 4, period: 700 });

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="08 · SHIPYARD"
        title="System center"
        sub="Runtime, queue, and webhook diagnostics for the assistant."
        right={
          <div className="flex items-center gap-2">
            <Pill tone="cyan">env · {meta.env}</Pill>
            <Btn icon={<Icon.Code/>}>Open repo</Btn>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        {envs.map((e, i) => (
          <Card key={i} className="col-span-12 lg:col-span-4" eyebrow="HOST" title={
            <div className="flex items-center gap-2">
              <span>{e.name}</span>
              <Pill tone={e.tone}>{e.status}</Pill>
            </div>
          } right={<button className="text-white/40 hover:text-white"><Icon.Globe/></button>}>
            <div className="font-mono text-[11.5px] text-white/55 truncate">{e.url}</div>
            <div className="grid grid-cols-2 gap-3 mt-4">
              <div className="glass-strong rounded-[10px] p-3">
                <div className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/40">laravel</div>
                <div className="font-mono text-[12.5px] text-white/85 mt-0.5">{meta.laravel}</div>
              </div>
              <div className="glass-strong rounded-[10px] p-3">
                <div className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/40">php</div>
                <div className="text-[14px] tabular mt-0.5">{meta.php}</div>
              </div>
              <div className="glass-strong rounded-[10px] p-3">
                <div className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/40">cpu (mock)</div>
                <div className="text-[16px] tabular mt-0.5">{cpu.toFixed(0)}%</div>
              </div>
              <div className="glass-strong rounded-[10px] p-3">
                <div className="font-mono text-[10px] uppercase tracking-[0.14em] text-white/40">timezone</div>
                <div className="text-[12.5px] mt-0.5">{meta.timezone}</div>
              </div>
            </div>
          </Card>
        ))}

        <Card className="col-span-12 lg:col-span-8" eyebrow="STATUS" title="System diagnostics" right={<Pill tone="cyan"><LiveDot tone="cyan"/> running</Pill>}>
          <div className="grid grid-cols-3 sm:grid-cols-6 gap-2">
            {[
              { k: "webhook", st: "done" },
              { k: "queue", st: D.queue?.driver === 'sync' ? 'queued' : 'running' },
              { k: "ollama", st: meta.model ? "running" : "queued" },
              { k: "tools", st: (D.agents?.tool_count || 0) > 0 ? "done" : "queued" },
              { k: "memory", st: (D.sessions?.total || 0) > 0 ? "done" : "queued" },
              { k: "reminders", st: (D.reminders?.active || 0) > 0 ? "running" : "queued" },
            ].map((s, i) => {
              const tone = s.st === 'done' ? 'pos' : s.st === 'running' ? 'cyan' : 'default';
              return (
                <div key={i} className="glass-strong rounded-[10px] p-3 reveal" style={{ animationDelay: `${i * 70}ms` }}>
                  <div className="flex items-center justify-between">
                    <div className="font-mono text-[11px] uppercase tracking-[0.14em] text-white/65">{s.k}</div>
                    {s.st === 'running' ? <LiveDot tone="cyan"/> : s.st === 'done' ? <Icon.Check/> : <span className="w-1.5 h-1.5 rounded-full bg-white/20"/>}
                  </div>
                  <div className="font-mono text-[11px] text-white/45 mt-2">{s.st}</div>
                </div>
              );
            })}
          </div>

          <div className="mt-5 rounded-[12px] glass-strong p-4 font-mono text-[12px] leading-[1.65] whitespace-pre-wrap text-white/82 max-h-[240px] overflow-y-auto">
            {buildLog}<span className="caret"/>
          </div>
        </Card>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 10 — PRODUCTIVITY HUB (tasks + reminders + learning)
   ========================================================= */
function ProductivityHub() {
  const D = __D();
  const tasks = D.tasks || {};
  const reminders = D.reminders || {};
  const learning = D.learning || {};

  const blocks = (tasks.upcoming || []).slice(0, 8).map(t => ({
    t: t.due_at ? t.due_at.slice(11, 16) : '—',
    l: t.title,
    k: t.priority || 'task',
    done: t.status === 'done',
    current: t.status === 'in_progress',
    status: t.status,
  }));

  const habits = [
    { name: `${reminders.active || 0} active reminders`, done: reminders.active || 0, total: Math.max(reminders.active || 0, 5), tone: 'cyan' },
    { name: `${tasks.in_progress || 0} tasks in progress`,    done: tasks.in_progress || 0, total: Math.max((tasks.in_progress || 0) + (tasks.pending || 0), 5), tone: 'azure' },
    { name: `${learning.lessons_done || 0}/${learning.lessons_total || 0} lessons`, done: learning.lessons_done || 0, total: learning.lessons_total || 1, tone: 'pos' },
    { name: `${reminders.daily || 0} daily routines`, done: reminders.daily || 0, total: Math.max(reminders.daily || 0, 3), tone: 'warn' },
  ];
  const focus = useTicker(72, { step: 2, period: 1000, min: 40, max: 96 });

  const completionPct = (tasks.done && (tasks.done + tasks.pending + tasks.in_progress))
    ? Math.round((tasks.done / (tasks.done + tasks.pending + tasks.in_progress)) * 100)
    : 0;

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="09 · LIFE"
        title={<>The <span className="font-serif italic">human</span> layer</>}
        sub="Tasks, reminders, and learning tracks — the things your assistant actually keeps for you."
        right={<div className="flex items-center gap-2"><Pill tone="cyan"><LiveDot tone="cyan"/> FOCUS · {focus.toFixed(0)}%</Pill></div>}
      />

      <div className="grid grid-cols-12 gap-5">
        <div className="col-span-12 lg:col-span-4 glass p-6 ring-aurora underglow-azure">
          <div className="flex items-center justify-between">
            <div>
              <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">completion · all time</div>
              <div className="text-[64px] tracking-[-0.03em] font-medium tabular leading-none mt-2">{completionPct}<span className="text-[18px] text-white/40">%</span></div>
            </div>
            <Donut value={completionPct} size={92} stroke={7} color="#5C7BFF" label={<div className="font-serif italic text-cyan-glow text-[15px]">flow</div>}/>
          </div>
          <div className="text-[13px] text-white/65 mt-3 leading-relaxed">{tasks.done || 0} done · {tasks.in_progress || 0} in progress · {tasks.pending || 0} pending.</div>
          <div className="grid grid-cols-3 gap-3 mt-5 font-mono text-[11px]">
            <div><div className="text-white/45">today</div><div className="text-cyan-glow tabular">{(tasks.today || []).length}</div></div>
            <div><div className="text-white/45">tracks</div><div className="text-white tabular">{learning.active_tracks || 0}</div></div>
            <div><div className="text-white/45">reminders</div><div className="text-[#5EE7A8] tabular">{reminders.active || 0}</div></div>
          </div>
        </div>

        <Card className="col-span-12 lg:col-span-8" eyebrow="UPCOMING" title="Next tasks" right={<Pill tone="cyan">{tasks.in_progress || 0} active</Pill>}>
          <div className="space-y-1">
            {blocks.length === 0 && <div className="py-8 text-[13px] text-white/45 italic text-center">No upcoming tasks. Send <span className="font-mono text-cyan-glow">/task</span> to your bot.</div>}
            {blocks.map((b, i) => (
              <div key={i} className={`grid grid-cols-[50px_1fr_auto] sm:grid-cols-[60px_1fr_120px_24px] items-center gap-3 py-2.5 border-b border-white/[0.04] last:border-0 reveal ${b.current ? 'bg-cyan-glow/[0.04] -mx-2 px-2 rounded-[10px] border-cyan-glow/20' : ''}`} style={{ animationDelay: `${i * 60}ms` }}>
                <div className="font-mono text-[12px] text-white/55 tabular">{b.t}</div>
                <div className="flex items-center gap-3 min-w-0">
                  {b.done ? <span className="w-4 h-4 rounded-full grid place-items-center shrink-0" style={{ background: "#5EE7A8", color: "#06080D" }}><Icon.Check/></span>
                    : b.current ? <LiveDot tone="cyan"/>
                    : <span className="w-3 h-3 rounded-full border border-white/20 shrink-0"/>}
                  <div className="min-w-0">
                    <div className={`text-[14px] truncate ${b.done ? 'text-white/50 line-through decoration-white/30' : 'text-white/92'}`}>{b.l}</div>
                    <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/40 mt-0.5">{b.k}</div>
                  </div>
                </div>
                <div><Pill tone={b.status === 'in_progress' ? 'cyan' : b.status === 'done' ? 'pos' : 'default'}>{b.status}</Pill></div>
                <div className="hidden sm:block"><Icon.Arrow/></div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-4" eyebrow="REMINDERS" title="Active">
          <div className="space-y-2.5 max-h-[320px] overflow-y-auto pr-1">
            {(reminders.list || []).length === 0 && <div className="text-[12.5px] text-white/45 italic">No active reminders.</div>}
            {(reminders.list || []).map((r, i) => (
              <div key={r.id || i} className="glass-strong rounded-[10px] p-3">
                <div className="flex items-center justify-between gap-2">
                  <div className="text-[13px] text-white/85 truncate">{r.message}</div>
                  <Pill tone={r.frequency === 'daily' ? 'cyan' : r.frequency === 'weekly' ? 'azure' : 'pos'}>{r.frequency}</Pill>
                </div>
                <div className="font-mono text-[10.5px] text-white/45 mt-1">
                  {r.time_of_day ? `time ${r.time_of_day}` : ''}{r.time_of_day && r.remind_human ? ' · ' : ''}{r.remind_human || ''}
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-4" eyebrow="HABITS / TRACKS" title="This week">
          <div className="space-y-3.5">
            {habits.map((h, i) => (
              <div key={i}>
                <div className="flex items-center justify-between mb-1.5">
                  <div className="text-[13px] text-white/85 truncate">{h.name}</div>
                  <div className="font-mono text-[11px] text-white/55 tabular">{h.done}/{h.total}</div>
                </div>
                <div className="flex gap-1">
                  {Array.from({ length: Math.max(1, h.total) }).map((_, k) => (
                    <div key={k} className="flex-1 h-1.5 rounded-full" style={{
                      background: k < h.done ? `linear-gradient(90deg, ${h.tone === 'cyan' ? '#5BD4FF' : h.tone === 'azure' ? '#5C7BFF' : h.tone === 'warn' ? '#FFC56B' : '#5EE7A8'}55, ${h.tone === 'cyan' ? '#5BD4FF' : h.tone === 'azure' ? '#5C7BFF' : h.tone === 'warn' ? '#FFC56B' : '#5EE7A8'})` : 'rgba(255,255,255,0.05)',
                    }}/>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-4" eyebrow="LEARNING" title="Active tracks">
          <div className="space-y-2.5 max-h-[320px] overflow-y-auto pr-1">
            {(learning.tracks || []).length === 0 && <div className="text-[12.5px] text-white/45 italic">No learning tracks yet.</div>}
            {(learning.tracks || []).map((tr, i) => (
              <div key={tr.id || i} className="glass-strong rounded-[10px] p-3">
                <div className="flex items-center justify-between gap-2">
                  <div className="text-[13px] text-white/90 truncate">{tr.topic}</div>
                  <Pill tone={tr.status === 'active' ? 'cyan' : tr.status === 'paused' ? 'warn' : 'pos'}>{tr.status}</Pill>
                </div>
                <div className="font-mono text-[10.5px] text-white/45 mt-0.5">{tr.level} · {tr.lessons_done}/{tr.lessons} lessons · {tr.quizzes} quizzes</div>
                {tr.last_activity && <div className="font-mono text-[10px] text-white/35 mt-1">last · {tr.last_activity}</div>}
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}

window.ApiPlayground = ApiPlayground;
window.WorkflowBuilder = WorkflowBuilder;
window.DeploymentCenter = DeploymentCenter;
window.ProductivityHub = ProductivityHub;
