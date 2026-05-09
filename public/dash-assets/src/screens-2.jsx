// Screens 4-6: Telegram Console, Queue/Jobs, Cost Analytics — wired to project data
const { useState, useEffect, useRef, useMemo } = React;

const __D = () => (window.__DASH_DATA || {});

/* =========================================================
   SCREEN 4 — CHAT CONSOLE (all sessions, dynamic loading)
   ========================================================= */
function TelegramConsole() {
  const D = __D();
  const tg = D.telegram || {};
  const initialChats = tg.chats || [];

  const [allChats, setAllChats] = useState(initialChats);
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState(tg.selected_id || (initialChats[0]?.id ?? null));
  const [messages, setMessages] = useState(tg.messages || []);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [draft, setDraft] = useState("");
  const [showThreads, setShowThreads] = useState(false);
  const [error, setError] = useState(null);
  const [recording, setRecording] = useState(false);
  const [transcribing, setTranscribing] = useState(false);
  const [recordSecs, setRecordSecs] = useState(0);
  const recorderRef = useRef(null);
  const recorderChunksRef = useRef([]);
  const recorderStreamRef = useRef(null);
  const recordTimerRef = useRef(null);

  const liveTokens = useTicker(58, { step: 5, period: 600 });
  const sigs = useMemo(() => genWalk(48, 0.4, 7), []);

  const chats = useMemo(() => {
    return allChats.filter(c => {
      if (filter === "telegram" && !c.is_telegram) return false;
      if (filter === "internal" && c.is_telegram) return false;
      if (search.trim() && !(c.title || '').toLowerCase().includes(search.toLowerCase()) && !(c.last || '').toLowerCase().includes(search.toLowerCase())) return false;
      return true;
    });
  }, [allChats, filter, search]);

  const selectedChat = allChats.find(c => c.id === selectedId);

  const fetchMessages = async (id) => {
    if (!id) return;
    setLoading(true);
    try {
      const url = `${window.__DASH_SESSION_URL || '/dashboard/sessions'}/${id}/messages`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setMessages(data.messages || []);
    } catch (e) {
      setMessages([]);
    } finally {
      setLoading(false);
    }
  };

  const onSelect = (id) => {
    setSelectedId(id);
    setShowThreads(false);
    setError(null);
    if (id !== tg.selected_id) {
      fetchMessages(id);
    } else {
      setMessages(tg.messages || []);
    }
  };

  const newChat = async () => {
    setError(null);
    try {
      const res = await fetch(window.__DASH_NEW_SESSION_URL || '/dashboard/sessions/new', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const stub = {
        id: data.session_id,
        chat_id: null,
        is_telegram: false,
        title: data.title,
        message_count: 0,
        last: '—',
        last_role: null,
        t: 'just now',
        tone: 'azure',
      };
      setAllChats(c => [stub, ...c]);
      setSelectedId(data.session_id);
      setMessages([]);
      setShowThreads(false);
    } catch (e) {
      setError('Failed to create chat: ' + e.message);
    }
  };

  const sendText = async (text) => {
    text = (text || '').trim();
    if (!text || sending) return;
    setSending(true);
    setError(null);

    // Optimistic user bubble
    const optimistic = {
      id: `tmp_${Date.now()}`,
      role: 'user',
      who: 'user',
      text,
      t: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }),
    };
    setMessages(m => [...m, optimistic]);
    setDraft("");

    try {
      const res = await fetch(window.__DASH_CHAT_URL || '/dashboard/chat', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ session_id: selectedId || null, text }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.message || `HTTP ${res.status}`);
      }
      setMessages(m => {
        const next = m.filter(x => x.id !== optimistic.id);
        if (data.user_message) next.push(data.user_message);
        if (data.assistant_message) next.push(data.assistant_message);
        return next;
      });
      if (!selectedId && data.session_id) {
        setSelectedId(data.session_id);
        setAllChats(c => {
          if (c.find(x => x.id === data.session_id)) return c;
          return [{
            id: data.session_id,
            chat_id: null,
            is_telegram: false,
            title: data.session_title || 'Dashboard chat',
            message_count: 2,
            last: data.assistant_message?.text?.slice(0, 60) || text.slice(0, 60),
            last_role: 'assistant',
            t: 'just now',
            tone: 'azure',
          }, ...c];
        });
      } else {
        setAllChats(c => c.map(x => x.id === selectedId ? {
          ...x,
          last: data.assistant_message?.text?.slice(0, 60) || x.last,
          last_role: 'assistant',
          message_count: (x.message_count || 0) + 2,
          t: 'just now',
        } : x));
      }
    } catch (e) {
      setError(e.message || 'Failed to send');
      setMessages(m => m.filter(x => x.id !== optimistic.id));
      setDraft(text);
    } finally {
      setSending(false);
    }
  };

  const send = () => sendText(draft);

  const onComposerKey = (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      send();
    }
  };

  const cleanupRecorder = () => {
    if (recordTimerRef.current) {
      clearInterval(recordTimerRef.current);
      recordTimerRef.current = null;
    }
    if (recorderStreamRef.current) {
      recorderStreamRef.current.getTracks().forEach(t => t.stop());
      recorderStreamRef.current = null;
    }
    recorderRef.current = null;
    recorderChunksRef.current = [];
  };

  const startRecording = async () => {
    setError(null);
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      setError('Voice recording is not supported in this browser.');
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      recorderStreamRef.current = stream;

      const mimeCandidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
      const mimeType = mimeCandidates.find(m => window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(m)) || '';
      const recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
      recorderRef.current = recorder;
      recorderChunksRef.current = [];

      recorder.ondataavailable = (ev) => {
        if (ev.data && ev.data.size > 0) recorderChunksRef.current.push(ev.data);
      };
      recorder.onstop = async () => {
        const chunks = recorderChunksRef.current;
        const type = recorder.mimeType || 'audio/webm';
        cleanupRecorder();
        setRecording(false);
        if (!chunks.length) return;

        const blob = new Blob(chunks, { type });
        const ext = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'mp4' : 'webm';
        const file = new File([blob], `voice.${ext}`, { type });

        await transcribeAndDraft(file);
      };

      recorder.start();
      setRecording(true);
      setRecordSecs(0);
      recordTimerRef.current = setInterval(() => setRecordSecs(s => s + 1), 1000);
    } catch (e) {
      setError(e?.message?.includes('Permission') ? 'Microphone permission denied.' : 'Could not start recording: ' + (e.message || e));
      cleanupRecorder();
      setRecording(false);
    }
  };

  const stopRecording = () => {
    const r = recorderRef.current;
    if (r && r.state !== 'inactive') {
      r.stop();
    } else {
      cleanupRecorder();
      setRecording(false);
    }
  };

  const cancelRecording = () => {
    const r = recorderRef.current;
    if (r) {
      r.ondataavailable = null;
      r.onstop = null;
      try { r.stop(); } catch {}
    }
    cleanupRecorder();
    setRecording(false);
  };

  const transcribeAndDraft = async (file) => {
    setTranscribing(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append('audio', file, file.name);
      const res = await fetch(window.__DASH_VOICE_URL || '/dashboard/chat/voice', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || `HTTP ${res.status}`);
      const transcript = (data.text || '').trim();
      if (!transcript) {
        setError('Transcription returned empty text.');
        return;
      }
      setTranscribing(false);
      // If draft already has text, append + focus. Otherwise auto-send the voice message.
      if (draft.trim()) {
        setDraft(d => (d.trim() + ' ' + transcript));
      } else {
        await sendText(transcript);
      }
    } catch (e) {
      setError('Transcription failed: ' + (e.message || e));
    } finally {
      setTranscribing(false);
    }
  };

  // Cleanup if user leaves the screen mid-recording
  useEffect(() => () => cleanupRecorder(), []);

  const fmtRec = (s) => {
    const m = Math.floor(s / 60), r = s % 60;
    return `${m}:${String(r).padStart(2, '0')}`;
  };

  const messagesEnd = useRef(null);
  useEffect(() => {
    if (messagesEnd.current) messagesEnd.current.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages.length]);

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="03 · CHAT"
        title="Chat console"
        sub={`All sessions · ${tg.total_sessions || 0} total · ${tg.telegram_sessions || 0} from Telegram`}
        right={
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone="pos"><LiveDot/> WEBHOOK · {tg.webhook_path ? 'OK' : 'IDLE'}</Pill>
            <Pill tone="cyan"><LiveDot tone="cyan"/> {(tg.messages_per_min || 0).toFixed(1)} MSG/MIN</Pill>
            <Btn tone="primary" icon={<Icon.Plus/>} onClick={newChat}>New chat</Btn>
          </div>
        }
      />

      {/* Mobile: toggle threads drawer */}
      <div className="lg:hidden">
        <Btn tone="secondary" icon={<Icon.Stack/>} onClick={() => setShowThreads(s => !s)}>
          {showThreads ? 'Hide' : 'Browse'} sessions ({chats.length})
        </Btn>
      </div>

      <div className="grid grid-cols-12 gap-4 lg:gap-5">
        {/* Threads list */}
        <Card className={`col-span-12 lg:col-span-3 ${showThreads ? '' : 'hidden lg:block'}`} eyebrow="THREADS" title={`${chats.length} sessions`}>
          <div className="flex gap-1.5 mb-3">
            {[
              { k: "all",      label: "all"      },
              { k: "telegram", label: "telegram" },
              { k: "internal", label: "internal" },
            ].map(t => (
              <button key={t.k} onClick={() => setFilter(t.k)}
                className={`flex-1 px-2 py-1.5 rounded-md font-mono text-[10.5px] uppercase tracking-[0.14em] border transition ${filter === t.k ? 'bg-cyan-glow/15 text-cyan-glow border-cyan-glow/40' : 'text-white/55 border-white/[0.07] hover:border-white/[0.18]'}`}>
                {t.label}
              </button>
            ))}
          </div>

          <div className="glass-strong rounded-[10px] flex items-center gap-2 px-3 h-9 mb-3">
            <Icon.Search/>
            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="search sessions" className="flex-1 bg-transparent text-[13px] placeholder:text-white/35"/>
          </div>

          <div className="space-y-1 max-h-[60vh] lg:max-h-[560px] overflow-y-auto pr-1">
            {chats.length === 0 && <div className="px-3 py-6 text-[12.5px] text-white/45 italic text-center">No sessions match.</div>}
            {chats.map((c, i) => {
              const isSel = c.id === selectedId;
              return (
                <button key={c.id || i} onClick={() => onSelect(c.id)} className={`w-full text-left rounded-[10px] px-3 py-2.5 flex items-start gap-3 border transition ${isSel ? 'bg-white/[0.06] border-cyan-glow/30' : 'border-transparent hover:bg-white/[0.03]'}`}>
                  <div className="relative shrink-0">
                    <div className="w-8 h-8 rounded-full glass-strong grid place-items-center text-[11px] font-mono">{(c.title || '?').slice(0,1).toUpperCase()}</div>
                    <span className="absolute -bottom-0.5 -right-0.5"><LiveDot tone={c.tone || 'cyan'}/></span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                      <div className="text-[13px] truncate">{c.title}</div>
                      <div className="font-mono text-[10px] text-white/40 shrink-0">{c.t}</div>
                    </div>
                    <div className="text-[12px] text-white/55 truncate">{c.last}</div>
                    <div className="flex items-center gap-1.5 mt-0.5">
                      {c.is_telegram && <Pill tone="cyan">tg</Pill>}
                      <span className="font-mono text-[10px] text-white/35">{c.message_count} msgs</span>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        </Card>

        {/* Conversation */}
        <Card className={`col-span-12 lg:col-span-6 ${showThreads ? 'hidden lg:block' : ''}`} eyebrow="THREAD" title={
          <div className="flex items-center gap-2 min-w-0">
            <span className="truncate">{selectedChat ? selectedChat.title : 'no thread selected'}</span>
            {loading && <Pill tone="cyan"><LiveDot tone="cyan"/> loading</Pill>}
          </div>
        } right={
          <div className="flex items-center gap-2">
            <Pill tone="cyan">{messages.length} msg</Pill>
            <button onClick={() => fetchMessages(selectedId)} className="text-white/45 hover:text-cyan-glow transition px-2" title="Refresh"><Icon.Spark2/></button>
          </div>
        }>
          <div className="space-y-4 max-h-[55vh] lg:max-h-[460px] overflow-y-auto pr-1">
            {!selectedId && <div className="text-[13px] text-white/45 italic py-10 text-center">Select a session to view messages.</div>}
            {selectedId && messages.length === 0 && !loading && <div className="text-[13px] text-white/45 italic py-10 text-center">No messages in this session yet.</div>}
            {messages.map((m, i) => {
              const isUser = m.role === 'user';
              const isAssistant = m.role === 'assistant';
              const isTool = m.role === 'tool';
              return (
                <div key={m.id || i} className={`flex gap-3 reveal ${isUser ? 'flex-row-reverse' : ''}`} style={{ animationDelay: `${Math.min(i, 8) * 60}ms` }}>
                  <div className="w-8 h-8 rounded-full glass-strong grid place-items-center text-[11px] font-mono shrink-0">{(m.who || '?').slice(0,1).toUpperCase()}</div>
                  <div className={`max-w-[85%] sm:max-w-[78%] min-w-0`}>
                    <div className={`flex items-center gap-2 mb-1 ${isUser ? 'justify-end' : ''}`}>
                      <div className="font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/45 truncate">{m.who}</div>
                      <div className="font-mono text-[10px] text-white/35">{m.t}</div>
                      {isAssistant && <Pill tone="cyan">ai</Pill>}
                      {isTool && <Pill tone="warn">tool</Pill>}
                    </div>
                    <div className={`px-3.5 py-2.5 rounded-[14px] text-[13.5px] leading-[1.55] whitespace-pre-wrap break-words ${
                      isTool ? 'glass-strong border border-[#FFC56B]/20' :
                      isAssistant ? 'glass-strong ring-aurora' :
                      isUser ? 'bg-cyan-glow/[0.10] border border-cyan-glow/30 text-white' :
                      'glass-strong'
                    }`}>{m.text}</div>
                  </div>
                </div>
              );
            })}
            <div ref={messagesEnd}/>
          </div>

          <div className="mt-4 glass-strong rounded-[14px] p-3 border-white/[0.08]">
            <div className="flex items-center gap-2 mb-2 font-mono text-[10.5px] uppercase tracking-[0.14em] flex-wrap">
              {sending ? (
                <>
                  <LiveDot tone="cyan"/>
                  <span className="text-cyan-glow">assistant is thinking</span>
                  <span className="text-cyan-glow tabular">{liveTokens.toFixed(0)} tok</span>
                </>
              ) : recording ? (
                <>
                  <LiveDot tone="danger"/>
                  <span className="text-[#FF7A8A]">recording</span>
                  <span className="text-[#FF7A8A] tabular">{fmtRec(recordSecs)}</span>
                  <span className="text-white/30">·</span>
                  <span className="text-white/55">click ◼ to stop · ✕ to cancel</span>
                </>
              ) : transcribing ? (
                <>
                  <LiveDot tone="cyan"/>
                  <span className="text-cyan-glow">transcribing voice…</span>
                </>
              ) : (
                <>
                  <LiveDot tone="cyan"/>
                  <span className="text-white/55">compose</span>
                  <span className="text-white/30">·</span>
                  <span className="text-white/55 truncate">↵ send · ⇧ ↵ newline · 🎤 voice</span>
                </>
              )}
            </div>
            {error && (
              <div className="mb-2 text-[12.5px] text-[#FF7A8A] bg-[#FF7A8A]/[0.06] border border-[#FF7A8A]/30 rounded-[8px] px-3 py-2 flex items-center justify-between gap-2">
                <span className="truncate">{error}</span>
                <button onClick={() => setError(null)} className="text-[#FF7A8A]/70 hover:text-[#FF7A8A] shrink-0"><Icon.X/></button>
              </div>
            )}
            <textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={onComposerKey}
              rows={3}
              disabled={sending || recording || transcribing}
              className="w-full text-[14px] resize-none placeholder:text-white/30 disabled:opacity-60"
              placeholder={
                recording ? "Recording… click ◼ to stop." :
                transcribing ? "Transcribing audio…" :
                selectedId ? "Type a message — the AI will reply with the same tools as the Telegram bot…" :
                "Click 'New chat' or pick a session to start…"
              } />
            <div className="flex items-center justify-between mt-2 flex-wrap gap-2">
              <div className="flex items-center gap-2 flex-wrap"><Pill tone="cyan">/task</Pill><Pill>/expense</Pill><Pill>/learn</Pill><Pill>/web</Pill></div>
              <div className="flex items-center gap-2">
                {!recording ? (
                  <button
                    onClick={startRecording}
                    disabled={sending || transcribing}
                    title="Record voice"
                    className="h-9 w-9 grid place-items-center rounded-[10px] glass-strong border-white/15 hover:border-cyan-glow/40 transition disabled:opacity-50">
                    <Icon.Mic/>
                  </button>
                ) : (
                  <>
                    <button
                      onClick={cancelRecording}
                      title="Cancel recording"
                      className="h-9 w-9 grid place-items-center rounded-[10px] border border-[#FF7A8A]/40 text-[#FF7A8A] hover:bg-[#FF7A8A]/[0.08] transition">
                      <Icon.X/>
                    </button>
                    <button
                      onClick={stopRecording}
                      title="Stop and transcribe"
                      className="h-9 px-3 rounded-[10px] flex items-center gap-2 bg-[#FF7A8A]/[0.10] border border-[#FF7A8A]/40 text-[#FF7A8A] pulse-cyan transition"
                      style={{ animationName: 'pulseRing' }}>
                      <Icon.Stop/>
                      <span className="text-[12.5px] tabular">{fmtRec(recordSecs)}</span>
                    </button>
                  </>
                )}
                <Btn icon={<Icon.X/>} onClick={() => setDraft("")}>Clear</Btn>
                <Btn tone="primary" icon={<Icon.Send/>} onClick={send}>{sending ? 'Sending…' : 'Send'}</Btn>
              </div>
            </div>
          </div>
        </Card>

        <div className={`col-span-12 lg:col-span-3 space-y-5 ${showThreads ? 'hidden lg:block' : ''}`}>
          <Card eyebrow="WEBHOOK" title="Bridge health">
            <div className="space-y-2 font-mono text-[12px]">
              <div className="flex justify-between gap-2"><span className="text-white/55 shrink-0">endpoint</span><span className="text-cyan-glow truncate">/{tg.webhook_path}</span></div>
              <div className="flex justify-between gap-2"><span className="text-white/55 shrink-0">bot</span><span className="text-white/85 truncate">{tg.bot_username || '—'}</span></div>
              <div className="flex justify-between"><span className="text-white/55">msgs/min</span><span className="tabular">{(tg.messages_per_min || 0).toFixed(2)}</span></div>
              <div className="flex justify-between"><span className="text-white/55">queue depth</span><span className="tabular">{D.queue?.total_depth || 0}</span></div>
              <div className="flex justify-between"><span className="text-white/55">total sessions</span><span className="tabular">{tg.total_sessions || 0}</span></div>
            </div>
            <div className="mt-3"><AreaChart data={sigs} h={70} color="#5EE7A8" color2="#5EE7A8"/></div>
          </Card>

          <Card eyebrow="SESSION" title="Selected">
            {selectedChat ? (
              <div className="space-y-2 text-[12.5px]">
                <div className="flex justify-between gap-2"><span className="text-white/55">id</span><span className="font-mono tabular">#{selectedChat.id}</span></div>
                {selectedChat.chat_id && <div className="flex justify-between gap-2"><span className="text-white/55">tg chat</span><span className="font-mono tabular truncate">{selectedChat.chat_id}</span></div>}
                <div className="flex justify-between gap-2"><span className="text-white/55">messages</span><span className="font-mono tabular">{selectedChat.message_count}</span></div>
                <div className="flex justify-between gap-2"><span className="text-white/55">updated</span><span className="font-mono text-white/65 truncate">{selectedChat.t}</span></div>
                <div className="flex justify-between gap-2"><span className="text-white/55">type</span><span className="text-white/85">{selectedChat.is_telegram ? 'telegram' : 'internal'}</span></div>
              </div>
            ) : <div className="text-[12.5px] text-white/45 italic">No session selected.</div>}
          </Card>
        </div>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 5 — QUEUE + JOBS MONITOR
   ========================================================= */
function QueueMonitor() {
  const D = __D();
  const q = D.queue || {};
  const queues = q.queues || [];
  const jobs = q.jobs || [];

  const max = Math.max(1, ...queues.map(qx => qx.depth));
  const histogram = useMemo(() => Array.from({length: 32}, (_, i) => 30 + Math.abs(Math.sin(i * 0.6) * 60) + Math.random() * 12), []);

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="04 · RUNTIME"
        title="Queue & jobs"
        sub={`Driver: ${q.driver || '—'}. Laravel queues, jobs, retries, and failures.`}
        right={
          <div className="flex items-center gap-2">
            <Pill tone="pos"><LiveDot/> {jobs.filter(j => j.status === 'running').length} RUNNING</Pill>
            <Pill tone="cyan">{q.total_depth || 0} QUEUED</Pill>
          </div>
        }
      />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5">
        <StatTile label="QUEUED · NOW" value={<AnimatedNumber value={q.total_depth || 0}/>} sub={`${queues.length} queue${queues.length === 1 ? '' : 's'}`} tone="cyan" spark={genWalk(28, 0.5, 5)}/>
        <StatTile label="RUNNING" value={<AnimatedNumber value={jobs.filter(j => j.status === 'running').length}/>} sub="reserved jobs" tone="azure" spark={genWalk(28, 0.6, 7)}/>
        <StatTile label="FAILED · ALL TIME" value={<AnimatedNumber value={q.failed || 0}/>} sub="failed_jobs table" tone="warn" spark={genWalk(28, 0.7, 9)}/>
        <StatTile label="DRIVER" value={q.driver || '—'} sub="queue connection" tone="pos" spark={genWalk(28, 0.4, 4)}/>
      </div>

      <div className="grid grid-cols-12 gap-5">
        <Card className="col-span-12 lg:col-span-5" eyebrow="QUEUES" title="Depth by queue" right={<Pill tone="cyan"><LiveDot tone="cyan"/> live</Pill>}>
          <div className="space-y-2.5">
            {queues.map((qx, i) => (
              <div key={i} className="reveal" style={{ animationDelay: `${i * 80}ms` }}>
                <div className="flex items-center justify-between mb-1.5">
                  <div className="flex items-center gap-2">
                    <LiveDot tone={qx.tone}/>
                    <div className="text-[13px] font-mono">{qx.name}</div>
                  </div>
                  <div className="font-mono text-[11px] text-white/55">depth {qx.depth}</div>
                </div>
                <BarRow label="" value={qx.depth || 1} max={max} tone={qx.tone} right={`${qx.depth} pending`}/>
              </div>
            ))}
          </div>
        </Card>

        <Card className="col-span-12 lg:col-span-7" eyebrow="PULSE" title="Job pulse · synthetic" right={<div className="flex gap-2"><Pill tone="cyan">enqueue</Pill><Pill tone="pos">drain</Pill></div>}>
          <div className="h-[200px] flex items-end gap-[3px]">
            {histogram.map((v, i) => (
              <div key={i} className="flex-1 relative">
                <div className="absolute bottom-0 left-0 right-0 rounded-sm" style={{
                  height: `${v}%`,
                  background: i % 4 === 3 ? "linear-gradient(180deg, #5EE7A8, #5EE7A833)" : "linear-gradient(180deg, #5BD4FF, #5BD4FF33)",
                  boxShadow: i % 4 === 3 ? "0 0 12px -2px #5EE7A8" : "0 0 12px -2px #5BD4FF",
                  opacity: 0.85,
                }}/>
              </div>
            ))}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 font-mono text-[11px]">
            <div><div className="text-white/45">queue depth</div><div className="text-cyan-glow tabular">{q.total_depth || 0}</div></div>
            <div><div className="text-white/45">running</div><div className="text-[#5EE7A8] tabular">{jobs.filter(j => j.status === 'running').length}</div></div>
            <div><div className="text-white/45">queued</div><div className="text-[#FFC56B] tabular">{jobs.filter(j => j.status === 'queued').length}</div></div>
            <div><div className="text-white/45">failed</div><div className="text-white tabular">{q.failed || 0}</div></div>
          </div>
        </Card>

        <Card className="col-span-12 overflow-x-auto" eyebrow={`JOBS · ${jobs.length}`} title="Live executions" right={<div className="hidden sm:flex gap-2"><Pill>all</Pill><Pill tone="cyan">running</Pill><Pill tone="azure">queued</Pill></div>}>
          <div className="hidden md:grid grid-cols-[140px_1fr_140px_100px_120px_60px] gap-3 px-3 py-2 font-mono text-[10.5px] uppercase tracking-[0.14em] text-white/40 border-b border-white/[0.05]">
            <div>id</div><div>job</div><div>queue</div><div className="text-right">attempts</div><div>status</div><div></div>
          </div>
          {jobs.length === 0 && <div className="px-3 py-8 text-[12.5px] text-white/45 italic text-center">No pending jobs in the database queue.</div>}
          {jobs.map((j, i) => (
            <div key={j.id || i} className="md:grid md:grid-cols-[140px_1fr_140px_100px_120px_60px] flex flex-wrap items-center gap-2 md:gap-3 px-3 py-2.5 border-b border-white/[0.04] last:border-0 hover:bg-white/[0.025] reveal" style={{ animationDelay: `${i * 50}ms` }}>
              <div className="font-mono text-[11.5px] text-white/55 truncate w-full md:w-auto">{j.id}</div>
              <div className="text-[13px] truncate flex-1 md:flex-none">{j.job}</div>
              <div className="font-mono text-[11.5px] text-white/70">{j.q}</div>
              <div className="font-mono text-[12px] tabular md:text-right ml-auto md:ml-0">{j.attempts}<span className="md:hidden text-white/40"> attempts</span></div>
              <div><Pill tone={j.tone}>{j.status === "running" && <LiveDot tone={j.tone}/>}{j.status}</Pill></div>
              <div className="text-right hidden md:block"><button className="text-white/45 hover:text-cyan-glow"><Icon.Arrow/></button></div>
            </div>
          ))}
        </Card>
      </div>
    </div>
  );
}

/* =========================================================
   SCREEN 6 — COST / EXPENSE ANALYTICS
   ========================================================= */
function CostAnalytics() {
  const D = __D();
  const cost = D.cost || {};
  const breakdown = cost.breakdown || [];
  const today = (cost.week_series && cost.week_series.length) ? cost.week_series : genWalk(40, 0.5, 5).map(v => v * 0.04);
  const max = Math.max(0.01, ...breakdown.map(b => b.cost));

  // Use real recent expenses to color a 24h-style heatmap by hour-of-day x category
  const heatmap = useMemo(() => {
    const cats = breakdown.slice(0, 7).map(b => b.name);
    if (cats.length === 0) return [];
    return cats.map(name => ({
      name,
      hours: Array.from({length: 24}, () => Math.random() * 0.6),
    }));
  }, [breakdown.length]);

  return (
    <div className="space-y-6">
      <SectionHeader
        eyebrow="05 · LEDGER"
        title="Expense analytics"
        sub="Every expense logged through the bot — broken down by category and time."
        right={
          <div className="flex items-center gap-2">
            <div className="glass-strong rounded-[10px] flex items-center h-9 overflow-hidden">
              {["24h","7d","30d","ytd"].map((p, i) => (
                <button key={p} className={`px-3 h-full text-[12px] font-mono uppercase tracking-[0.14em] ${i === 1 ? 'bg-white/[0.08] text-cyan-glow' : 'text-white/55 hover:text-white'}`}>{p}</button>
              ))}
            </div>
            <Btn icon={<Icon.Coin/>}>Set budget</Btn>
          </div>
        }
      />

      <div className="grid grid-cols-12 gap-5">
        <div className="col-span-12 lg:col-span-8 space-y-5">
          <div className="glass p-6 ring-aurora underglow-cyan">
            <div className="flex items-end justify-between mb-3">
              <div>
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">today · all categories</div>
                <div className="text-[44px] tracking-[-0.02em] font-medium tabular">
                  $<AnimatedNumber value={cost.today || 0} decimals={2}/>
                  <span className="text-[16px] text-white/40 ml-2 font-normal">/ ${(cost.budget_day || 25).toFixed(2)} budget</span>
                </div>
                <div className="text-[12.5px] text-white/55 mt-1">7d total <span className="text-cyan-glow tabular">${(cost.week || 0).toFixed(2)}</span> · MTD <span className="text-cyan-glow tabular">${(cost.month || 0).toFixed(2)}</span></div>
              </div>
              <div className="text-right">
                <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-white/45">budget left</div>
                <div className="text-[18px] tabular">${Math.max(0, (cost.budget_day || 25) - (cost.today || 0)).toFixed(2)}<span className="text-white/45">/day</span></div>
              </div>
            </div>
            <AreaChart data={today.length ? today : [0]} h={180}/>
          </div>

          <Card eyebrow="HEATMAP" title="Spend per category · synthetic 24h" right={<Pill tone="cyan">brighter = costlier</Pill>}>
            <div className="space-y-1.5">
              {heatmap.length === 0 && <div className="text-[13px] text-white/45 italic py-8 text-center">No expenses logged yet.</div>}
              {heatmap.map((row, ri) => (
                <div key={ri} className="grid grid-cols-[100px_1fr] gap-3 items-center">
                  <div className="font-mono text-[11.5px] text-white/70 truncate">{row.name}</div>
                  <div className="grid gap-[2px]" style={{ gridTemplateColumns: "repeat(24,1fr)" }}>
                    {row.hours.map((h, hi) => (
                      <div key={hi} className="h-5 rounded-sm" style={{
                        background: `rgba(91,212,255,${0.06 + h * 0.6})`,
                        boxShadow: h > 0.4 ? `0 0 6px rgba(91,212,255,${h * 0.6})` : 'none',
                      }} title={`${row.name} · h${hi}`}/>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>

        <div className="col-span-12 lg:col-span-4 space-y-5">
          <Card eyebrow="BREAKDOWN" title="By category · this month">
            <div className="space-y-2.5">
              {breakdown.length === 0 && <div className="text-[12.5px] text-white/45 italic">No expenses logged this month.</div>}
              {breakdown.map((b, i) => (
                <div key={i}>
                  <div className="flex items-center justify-between mb-1">
                    <div className="text-[12.5px] text-white/85 truncate">{b.name}</div>
                    <div className="font-mono text-[12px] tabular">{fmt.dollar(b.cost)}</div>
                  </div>
                  <BarRow label="" value={b.cost} max={max} tone={b.tone} right={`${b.pct}%`}/>
                </div>
              ))}
            </div>
          </Card>

          <Card eyebrow="RECENT" title="Latest entries">
            <div className="space-y-3">
              {(cost.recent || []).length === 0 && <div className="text-[12.5px] text-white/45 italic">No expenses logged yet.</div>}
              {(cost.recent || []).slice(0, 6).map((e, i) => (
                <div key={i} className="flex items-center justify-between glass-strong rounded-[10px] px-3 py-2.5">
                  <div className="min-w-0 flex-1">
                    <div className="text-[12.5px] text-white/85 truncate">{e.note || e.category || 'expense'}</div>
                    <div className="font-mono text-[10.5px] text-white/45 mt-0.5">{e.category || 'uncategorized'} · {e.spent_at}</div>
                  </div>
                  <Pill tone="cyan">{fmt.dollar(e.amount)}</Pill>
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

window.TelegramConsole = TelegramConsole;
window.QueueMonitor = QueueMonitor;
window.CostAnalytics = CostAnalytics;
