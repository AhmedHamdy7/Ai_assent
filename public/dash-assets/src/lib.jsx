// Shared hooks + utilities for the hamdix OS prototype
const { useState, useEffect, useRef, useMemo, useCallback, createContext, useContext } = React;

// Spring-ish tween hook (no deps)
function useSpring(target, { stiffness = 0.12, damping = 0.78 } = {}) {
  const [val, setVal] = useState(target);
  const v = useRef(0);
  const cur = useRef(target);
  useEffect(() => {
    let raf;
    const tick = () => {
      const f = (target - cur.current) * stiffness;
      v.current = (v.current + f) * damping;
      cur.current += v.current;
      if (Math.abs(target - cur.current) < 0.001 && Math.abs(v.current) < 0.001) {
        cur.current = target; setVal(target); return;
      }
      setVal(cur.current);
      raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [target]);
  return val;
}

// Animated number — counts up smoothly
function AnimatedNumber({ value, decimals = 0, format }) {
  const v = useSpring(value, { stiffness: 0.08, damping: 0.82 });
  const out = format ? format(v) : v.toFixed(decimals);
  return <span className="tabular">{out}</span>;
}

// Magnetic hover wrapper
function Magnet({ children, strength = 8, className = "", ...props }) {
  const ref = useRef(null);
  const onMove = (e) => {
    const r = ref.current.getBoundingClientRect();
    const x = e.clientX - r.left - r.width / 2;
    const y = e.clientY - r.top - r.height / 2;
    ref.current.style.transform = `translate(${(x / r.width) * strength}px, ${(y / r.height) * strength}px)`;
  };
  const onLeave = () => { if (ref.current) ref.current.style.transform = ""; };
  return (
    <div ref={ref} onMouseMove={onMove} onMouseLeave={onLeave} className={`magnet ${className}`} {...props}>
      {children}
    </div>
  );
}

// Stagger children by delay
function Stagger({ children, delay = 70, start = 0 }) {
  return React.Children.map(children, (c, i) =>
    React.cloneElement(c, {
      style: { ...(c.props.style || {}), animationDelay: `${start + i * delay}ms` },
      className: `${c.props.className || ""} reveal`.trim(),
    })
  );
}

// Live ticker — generates a random walk
function useTicker(initial = 50, { min = 0, max = 100, step = 1.5, period = 1200 } = {}) {
  const [v, setV] = useState(initial);
  useEffect(() => {
    const id = setInterval(() => {
      setV(prev => {
        const n = prev + (Math.random() - 0.5) * 2 * step;
        return Math.max(min, Math.min(max, n));
      });
    }, period);
    return () => clearInterval(id);
  }, [period, min, max, step]);
  return v;
}

// Current time
function useClock() {
  const [t, setT] = useState(new Date());
  useEffect(() => { const id = setInterval(() => setT(new Date()), 1000); return () => clearInterval(id); }, []);
  return t;
}

// Sparkline path generator
function sparkPath(values, w = 100, h = 30, pad = 2) {
  if (!values.length) return "";
  const min = Math.min(...values), max = Math.max(...values);
  const span = max - min || 1;
  const dx = (w - pad * 2) / (values.length - 1);
  return values.map((v, i) => {
    const x = pad + i * dx;
    const y = h - pad - ((v - min) / span) * (h - pad * 2);
    return `${i === 0 ? "M" : "L"}${x.toFixed(1)} ${y.toFixed(1)}`;
  }).join(" ");
}

function smoothPath(values, w = 100, h = 30, pad = 2) {
  if (values.length < 2) return sparkPath(values, w, h, pad);
  const min = Math.min(...values), max = Math.max(...values);
  const span = max - min || 1;
  const dx = (w - pad * 2) / (values.length - 1);
  const pts = values.map((v, i) => [pad + i * dx, h - pad - ((v - min) / span) * (h - pad * 2)]);
  let d = `M${pts[0][0]} ${pts[0][1]}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const [x1, y1] = pts[i], [x2, y2] = pts[i + 1];
    const cx = (x1 + x2) / 2;
    d += ` C${cx} ${y1} ${cx} ${y2} ${x2} ${y2}`;
  }
  return d;
}

// Generates a random walk
function genWalk(n = 32, seed = Math.random(), variance = 8) {
  const out = [];
  let v = 50 + (seed - 0.5) * 20;
  for (let i = 0; i < n; i++) {
    v += (Math.random() - 0.5) * variance;
    v = Math.max(5, Math.min(95, v));
    out.push(v);
  }
  return out;
}

// Format helpers
const fmt = {
  ms: (n) => `${Math.round(n)}ms`,
  pct: (n) => `${n.toFixed(1)}%`,
  dollar: (n) => `$${n.toFixed(2)}`,
  k: (n) => n >= 1000 ? `${(n / 1000).toFixed(1)}k` : `${Math.round(n)}`,
  short: (n) => {
    if (n >= 1e6) return `${(n / 1e6).toFixed(1)}M`;
    if (n >= 1e3) return `${(n / 1e3).toFixed(1)}k`;
    return `${Math.round(n)}`;
  },
};

// Text typer (live stream effect)
function useTyper(text, { speed = 18, loop = false } = {}) {
  const [out, setOut] = useState("");
  useEffect(() => {
    let i = 0; setOut("");
    const id = setInterval(() => {
      i++;
      setOut(text.slice(0, i));
      if (i >= text.length) {
        if (loop) { i = 0; setTimeout(() => setOut(""), 1200); }
        else clearInterval(id);
      }
    }, speed);
    return () => clearInterval(id);
  }, [text, speed, loop]);
  return out;
}

// Time ago
function timeAgo(seconds) {
  if (seconds < 60) return `${seconds}s ago`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  return `${Math.floor(seconds / 86400)}d ago`;
}

Object.assign(window, {
  useSpring, AnimatedNumber, Magnet, Stagger, useTicker, useClock,
  sparkPath, smoothPath, genWalk, fmt, useTyper, timeAgo,
});
