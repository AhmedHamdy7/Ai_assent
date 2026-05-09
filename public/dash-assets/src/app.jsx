// App entry — wires the shell, handles routing, refresh & mobile sidebar
const { useState, useEffect, useRef, useMemo, useCallback } = React;

function App() {
  const [active, setActive] = useState("command");
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [, forceTick] = useState(0);

  const t = window.useTweaks ? window.useTweaks(window.__TWEAKS_DEFAULTS) : [window.__TWEAKS_DEFAULTS, () => {}];
  const [tweaks, setTweak] = t;

  useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault(); setPaletteOpen(true);
      }
      if (e.key === 'Escape') setSidebarOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Lock scroll when mobile sidebar is open
  useEffect(() => {
    if (sidebarOpen) document.body.style.overflow = 'hidden';
    else document.body.style.overflow = '';
    return () => { document.body.style.overflow = ''; };
  }, [sidebarOpen]);

  // Apply density / accent / particles via runtime CSS vars
  useEffect(() => {
    const r = document.documentElement;
    if (tweaks.accent === "azure") {
      r.style.setProperty('--cyan', '#5C7BFF');
    } else if (tweaks.accent === "aurora") {
      r.style.setProperty('--cyan', '#9D7BFF');
    } else {
      r.style.setProperty('--cyan', '#5BD4FF');
    }
    document.querySelector('.scene-grid').style.opacity = tweaks.grid ? '1' : '0';
    document.querySelector('canvas#particles').style.opacity = tweaks.particles ? '1' : '0';
  }, [tweaks]);

  const refresh = useCallback(async () => {
    if (refreshing) return;
    setRefreshing(true);
    try {
      const url = window.__DASH_REFRESH_URL || '/dashboard/data';
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      window.__DASH_DATA = data;
      forceTick(n => n + 1);
    } catch (e) {
      console.warn('refresh failed', e);
    } finally {
      setRefreshing(false);
    }
  }, [refreshing]);

  const screen = useMemo(() => {
    switch (active) {
      case "command":  return <CommandCenter/>;
      case "agents":   return <AgentMonitoring/>;
      case "memory":   return <MemoryGraph/>;
      case "telegram": return <TelegramConsole/>;
      case "queue":    return <QueueMonitor/>;
      case "cost":     return <CostAnalytics/>;
      case "api":      return <ApiPlayground/>;
      case "workflow": return <WorkflowBuilder/>;
      case "deploy":   return <DeploymentCenter/>;
      case "life":     return <ProductivityHub/>;
      case "system":   return <DesignSystem/>;
      default:         return <CommandCenter/>;
    }
  }, [active]);

  return (
    <div className="flex min-h-screen" data-screen-label={NAV_MAP[active]?.label || "Design system"}>
      <Sidebar
        active={active}
        onChange={setActive}
        onOpenPalette={() => setPaletteOpen(true)}
        mobileOpen={sidebarOpen}
        onMobileClose={() => setSidebarOpen(false)}
      />
      <main className="flex-1 min-w-0 flex flex-col lg:h-screen lg:overflow-hidden">
        <TopBar
          activeId={active}
          onOpenPalette={() => setPaletteOpen(true)}
          onOpenSidebar={() => setSidebarOpen(true)}
          onRefresh={refresh}
          refreshing={refreshing}
        />
        <div key={active} className="flex-1 lg:overflow-y-auto px-3 sm:px-5 lg:px-7 py-5 lg:py-6 reveal">
          <div className="max-w-[1480px] mx-auto pb-20">
            {screen}
          </div>
        </div>
      </main>

      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} onJump={setActive}/>

      {window.TweaksPanel && (
        <window.TweaksPanel title="Tweaks">
          <window.TweakSection title="Surface">
            <window.TweakRadio label="Accent hue" value={tweaks.accent} onChange={v => setTweak('accent', v)}
              options={["cyan","azure","aurora"]}/>
            <window.TweakSlider label="Glow intensity" value={tweaks.glow} min={0} max={1.5} step={0.05} onChange={v => setTweak('glow', v)}/>
          </window.TweakSection>
          <window.TweakSection title="Scene">
            <window.TweakToggle label="Particle field" value={tweaks.particles} onChange={v => setTweak('particles', v)}/>
            <window.TweakToggle label="Grid overlay" value={tweaks.grid} onChange={v => setTweak('grid', v)}/>
            <window.TweakToggle label="Mono data" value={tweaks.monoData} onChange={v => setTweak('monoData', v)}/>
          </window.TweakSection>
        </window.TweaksPanel>
      )}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);

// Successful mount — kill the boot diagnostic timer.
if (window.__BOOT_TIMER) {
  clearTimeout(window.__BOOT_TIMER);
  window.__BOOT_TIMER = null;
}
