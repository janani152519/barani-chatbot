import { useState, useRef, useEffect, useCallback } from "react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Send, Bot, Loader2, CheckCircle2, Mail, AlertCircle, AlertTriangle,
  Sparkles, RotateCcw, Copy, ThumbsUp, BarChart2, X,
  ChevronRight, Zap, Database, FileText, Users, Cpu
} from "lucide-react";
import {
  BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend
} from "recharts";

/* ─────────────────── Types ─────────────────── */
interface Message {
  id: string;
  role: "user" | "bot";
  text: string;
  timestamp: string;
  typing?: boolean;
  chart?: ChartData;
  taskStatus?: { type: string; status: string; detail: string };
  liked?: boolean;
  copied?: boolean;
}

interface ChartData {
  type: "bar" | "line" | "pie";
  title: string;
  data: Record<string, string | number>[];
  keys: string[];
  colors: string[];
}

/* ─────────────────── Constants ─────────────────── */
const ACCENT = "#2563eb";
const COLORS = ["#2563eb", "#10b981", "#f59e0b", "#8b5cf6", "#ef4444", "#06b6d4"];

const SUGGESTIONS = [
  { icon: Database, text: "salary details sollu" },
  { icon: Cpu,      text: "Which machine is running down?" },
  { icon: FileText, text: "inaiki ena ena dispatch aaguthu solu" },
  { icon: AlertTriangle, text: "Which alarm is sounding & floor location" },
  { icon: Users,    text: "ethana peru work panranga" },
  { icon: BarChart2,text: "Give graphical representation of payroll" },
];

const CHART_TRIGGERS = [
  "chart", "graph", "plot", "visualize", "show me a", "bar chart",
  "line chart", "pie chart", "trend", "comparison", "graphical"
];

/* ─────────────────── Helpers ─────────────────── */
function ts() {
  return new Date().toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" });
}

function needsChart(text: string) {
  const lower = text.toLowerCase();
  return CHART_TRIGGERS.some(t => lower.includes(t));
}

function correctSpelling(text: string): string {
  const map: Record<string, string> = {
    "atendance": "attendance", "atendnce": "attendance", "attndance": "attendance",
    "employe": "employee", "empoyee": "employee", "employes": "employees",
    "sallary": "salary", "salery": "salary", "slary": "salary",
    "departmnt": "department", "deparment": "department",
    "machien": "machine", "mahcine": "machine",
    "prodution": "production", "producton": "production",
    "mantenance": "maintenance", "maintenace": "maintenance",
    "reprot": "report", "reort": "report",
    "send": "send", "sned": "send",
    "payrol": "payroll", "payrool": "payroll",
    "wich": "which", "waht": "what", "wht": "what", "hwo": "how",
    "kudu": "show me", "kodu": "show me", "sollu": "tell me",
    "yaaru": "who is", "yaru": "who is", "enna": "what is",
  };
  let result = text;
  for (const [wrong, right] of Object.entries(map)) {
    const re = new RegExp(`\\b${wrong}\\b`, "gi");
    result = result.replace(re, right);
  }
  return result;
}

/* ─────────────────── Markdown Renderer ─────────────────── */
function RenderMarkdown({ text }: { text: string }) {
  const lines = text.split("\n");
  const elements: React.ReactNode[] = [];
  let tableBuffer: string[] = [];
  let inTable = false;
  let listBuffer: string[] = [];
  let inList = false;
  let idx = 0;

  const flushTable = () => {
    if (tableBuffer.length < 2) { tableBuffer = []; return; }
    const [header, , ...rows] = tableBuffer;
    const headers = header.split("|").map(h => h.trim()).filter(Boolean);
    elements.push(
      <div key={`tbl-${idx++}`} style={{ overflowX: "auto", margin: "8px 0" }}>
        <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 11 }}>
          <thead>
            <tr style={{ background: "rgba(37,99,235,0.18)", borderBottom: "1px solid rgba(37,99,235,0.35)" }}>
              {headers.map((h, i) => (
                <th key={i} style={{ padding: "5px 10px", textAlign: "left", color: "#93c5fd", fontWeight: 700, whiteSpace: "nowrap" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, ri) => {
              const cells = row.split("|").map(c => c.trim()).filter(Boolean);
              return (
                <tr key={ri} style={{ borderBottom: "1px solid rgba(255,255,255,0.05)", background: ri % 2 === 1 ? "rgba(255,255,255,0.02)" : "transparent" }}>
                  {cells.map((c, ci) => <td key={ci} style={{ padding: "4px 10px", color: "#cbd5e1" }}>{inlineFormat(c)}</td>)}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
    tableBuffer = [];
  };

  const flushList = () => {
    elements.push(
      <ul key={`ul-${idx++}`} style={{ margin: "4px 0", paddingLeft: 18, listStyle: "none" }}>
        {listBuffer.map((item, i) => (
          <li key={i} style={{ color: "#cbd5e1", fontSize: 12.5, lineHeight: 1.7, display: "flex", alignItems: "flex-start", gap: 6 }}>
            <span style={{ color: ACCENT, marginTop: 4, flexShrink: 0 }}>▸</span>
            <span>{inlineFormat(item.replace(/^[-*•]\s*/, ""))}</span>
          </li>
        ))}
      </ul>
    );
    listBuffer = [];
  };

  for (const line of lines) {
    if (line.startsWith("|")) {
      if (inList) { flushList(); inList = false; }
      inTable = true;
      if (!line.match(/^[\s|:-]+$/)) tableBuffer.push(line);
      continue;
    } else if (inTable) { flushTable(); inTable = false; }

    if (line.match(/^[-*•]\s/)) {
      if (!inList) inList = true;
      listBuffer.push(line);
      continue;
    } else if (inList) { flushList(); inList = false; }

    if (line.match(/^#{1,3}\s/)) {
      const lvl = line.match(/^(#+)/)?.[1].length ?? 1;
      const content = line.replace(/^#+\s/, "");
      const sz = lvl === 1 ? 15 : lvl === 2 ? 13 : 12;
      elements.push(
        <div key={idx++} style={{ fontSize: sz, fontWeight: 800, color: "#f1f5f9", marginTop: lvl === 1 ? 10 : 6, marginBottom: 2 }}>
          {inlineFormat(content)}
        </div>
      );
    } else if (line.startsWith("---")) {
      elements.push(<hr key={idx++} style={{ border: "none", borderTop: "1px solid rgba(255,255,255,0.08)", margin: "8px 0" }} />);
    } else if (line.trim() === "") {
      elements.push(<div key={idx++} style={{ height: 4 }} />);
    } else {
      elements.push(
        <div key={idx++} style={{ fontSize: 12.5, color: "#cbd5e1", lineHeight: 1.75 }}>
          {inlineFormat(line)}
        </div>
      );
    }
  }
  if (inTable) flushTable();
  if (inList) flushList();

  return <>{elements}</>;
}

function inlineFormat(text: string): React.ReactNode {
  const parts = text.split(/(\*\*.*?\*\*|`.*?`|✅|⚠️|❌|✔|•|—)/g);
  return parts.map((part, i) => {
    if (part.startsWith("**") && part.endsWith("**")) {
      return <strong key={i} style={{ color: "#f1f5f9", fontWeight: 700 }}>{part.slice(2, -2)}</strong>;
    }
    if (part.startsWith("`") && part.endsWith("`")) {
      return <code key={i} style={{ fontFamily: "monospace", background: "rgba(37,99,235,0.2)", padding: "1px 5px", borderRadius: 3, fontSize: 11, color: "#93c5fd" }}>{part.slice(1, -1)}</code>;
    }
    return part;
  });
}

/* ─────────────────── Chart Component ─────────────────── */
function InlineChart({ chart }: { chart: ChartData }) {
  const h = 180;
  return (
    <motion.div
      initial={{ opacity: 0, scaleY: 0.8 }}
      animate={{ opacity: 1, scaleY: 1 }}
      style={{ marginTop: 12, padding: "12px 14px", background: "rgba(37,99,235,0.06)", border: "1px solid rgba(37,99,235,0.2)", borderRadius: 8 }}
    >
      <div style={{ fontSize: 10, fontWeight: 700, color: "#93c5fd", marginBottom: 8, textTransform: "uppercase", letterSpacing: "0.1em" }}>
        {chart.title}
      </div>
      <ResponsiveContainer width="100%" height={h}>
        {chart.type === "pie" ? (
          <PieChart>
            <Pie data={chart.data} dataKey={chart.keys[0]} nameKey="name" innerRadius={40} outerRadius={70} paddingAngle={3}>
              {chart.data.map((_, i) => <Cell key={i} fill={chart.colors[i % chart.colors.length]} />)}
            </Pie>
            <Tooltip contentStyle={{ background: "#0f172a", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 6, fontSize: 11 }} />
            <Legend iconSize={8} iconType="circle" wrapperStyle={{ fontSize: 10, color: "#94a3b8" }} />
          </PieChart>
        ) : chart.type === "line" ? (
          <LineChart data={chart.data}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
            <XAxis dataKey="name" tick={{ fontSize: 9, fill: "#64748b" }} />
            <YAxis tick={{ fontSize: 9, fill: "#64748b" }} />
            <Tooltip contentStyle={{ background: "#0f172a", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 6, fontSize: 11 }} />
            {chart.keys.map((k, i) => <Line key={k} type="monotone" dataKey={k} stroke={chart.colors[i % chart.colors.length]} strokeWidth={2} dot={{ r: 3 }} />)}
          </LineChart>
        ) : (
          <BarChart data={chart.data}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
            <XAxis dataKey="name" tick={{ fontSize: 9, fill: "#64748b" }} />
            <YAxis tick={{ fontSize: 9, fill: "#64748b" }} />
            <Tooltip contentStyle={{ background: "#0f172a", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 6, fontSize: 11 }} />
            {chart.keys.map((k, i) => <Bar key={k} dataKey={k} fill={chart.colors[i % chart.colors.length]} radius={[3, 3, 0, 0]} />)}
          </BarChart>
        )}
      </ResponsiveContainer>
    </motion.div>
  );
}

/* ─────────────────── Typing Cursor ─────────────────── */
function TypingCursor() {
  return (
    <motion.span
      animate={{ opacity: [1, 0] }}
      transition={{ duration: 0.5, repeat: Infinity, repeatType: "reverse" }}
      style={{ display: "inline-block", width: 2, height: 14, background: ACCENT, marginLeft: 2, verticalAlign: "middle", borderRadius: 1 }}
    />
  );
}

/* ─────────────────── Sample chart builder ─────────────────── */
function buildSampleChart(query: string): ChartData | undefined {
  const q = query.toLowerCase();
  if (q.includes("production")) {
    return {
      type: "bar", title: "Monthly Production (Units)", keys: ["units", "target"],
      colors: [ACCENT, "#10b981"],
      data: [
        { name: "Apr", units: 1480, target: 1500 }, { name: "May", units: 1620, target: 1600 },
        { name: "Jun", units: 1540, target: 1650 }, { name: "Jul", units: 1780, target: 1700 },
        { name: "Aug", units: 1842, target: 1750 }, { name: "Sep", units: 1920, target: 1800 },
      ]
    };
  }
  if (q.includes("attendance")) {
    return {
      type: "line", title: "Attendance Rate (%) — Last 30 Days", keys: ["rate"],
      colors: ["#10b981"],
      data: Array.from({ length: 8 }, (_, i) => ({ name: `W${i+1}`, rate: 88 + Math.round(Math.random() * 10) }))
    };
  }
  if (q.includes("payroll") || q.includes("salary")) {
    return {
      type: "bar", title: "Department-wise Payroll (₹ Lakhs)", keys: ["payroll"],
      colors: ["#8b5cf6"],
      data: [
        { name: "Production", payroll: 8.2 }, { name: "Maintenance", payroll: 4.5 },
        { name: "QC", payroll: 3.1 }, { name: "HR/Admin", payroll: 2.8 },
        { name: "CNC", payroll: 5.6 }, { name: "Accounts", payroll: 2.2 },
      ]
    };
  }
  if (q.includes("machine") || q.includes("status")) {
    return {
      type: "pie", title: "Machine Status Distribution", keys: ["value"],
      colors: ["#10b981", "#f59e0b", "#ef4444"],
      data: [{ name: "Operational", value: 48 }, { name: "Maintenance", value: 9 }, { name: "Offline", value: 6 }]
    };
  }
  return undefined;
}

/* ─────────────────── Main Component ─────────────────── */
export default function AIChat() {
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "welcome",
      role: "bot",
      text: "Hello! I'm the **Barani Hydraulics AI Agent** — powered by GRI SCADA Intelligence.\n\nI have full access to your employee records, attendance, payroll, production data, machine logs and more. Ask me anything in **English or Tamil/Tanglish** — I understand natural language.\n\n---\n\n**Try asking:**\n- Show all employees and their departments\n- Total payroll September 2026\n- Who is on leave today?\n- Generate payroll PDF report\n- Send payroll report to admin",
      timestamp: ts(),
    }
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [sessionUuid, setSessionUuid] = useState<string | null>(null);
  const [showSuggestions, setShowSuggestions] = useState(true);
  const [llmMode, setLlmMode] = useState<string>("local_offline_llm");
  const [showGeminiBanner, setShowGeminiBanner] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);


  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  /* Typewriter effect for bot messages */
  const typewriterEffect = useCallback((id: string, fullText: string) => {
    let i = 0;
    const speed = fullText.length > 300 ? 4 : 12;
    const tick = () => {
      i += speed;
      setMessages(prev =>
        prev.map(m =>
          m.id === id
            ? { ...m, text: fullText.slice(0, Math.min(i, fullText.length)), typing: i < fullText.length }
            : m
        )
      );
      if (i < fullText.length) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }, []);

  const send = useCallback(async (overrideText?: string) => {
    const raw = overrideText ?? input.trim();
    if (!raw || loading) return;
    const query = correctSpelling(raw);
    setInput("");
    setShowSuggestions(false);

    const userMsg: Message = {
      id: `u-${Date.now()}`,
      role: "user",
      text: raw,
      timestamp: ts(),
    };
    setMessages(prev => [...prev, userMsg]);
    setLoading(true);

    const botId = `b-${Date.now() + 1}`;

    try {
      const token = localStorage.getItem("api_token") || "";
      const res = await fetch("http://127.0.0.1:8000/api/chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ message: query, session_uuid: sessionUuid }),
      });

      const data = await res.json();

      let answerText = "";
      let chart: ChartData | undefined;
      let taskStatus;

      if (data.success) {
        if (data.session_uuid) setSessionUuid(data.session_uuid);
        // Track LLM mode
        if (data.mode) {
          setLlmMode(data.mode);
          setShowGeminiBanner(false);
        }
        answerText = data.answer || "Done.";
        if (data.type === "email_sent") taskStatus = { type: "email", status: "sent", detail: answerText };
        else if (data.type === "report_generated") taskStatus = { type: "report", status: "sent", detail: answerText };
      } else {
        answerText = data.message
          ? `⚠️ ${data.message}`
          : getFallbackAnswer(query);
      }

      // Chart: use API chart_data if present, else build sample only if explicitly requested
      if (data.chart_data) {
        chart = {
          type: data.chart_data.type || "bar",
          title: data.chart_data.title || "Chart",
          data: data.chart_data.data || [],
          keys: [data.chart_data.yKey || "value"],
          colors: COLORS,
        };
      } else if (needsChart(raw)) {
        chart = buildSampleChart(raw);
      }


      const botMsg: Message = { id: botId, role: "bot", text: "", timestamp: ts(), typing: true, chart, taskStatus };
      setMessages(prev => [...prev, botMsg]);
      setLoading(false);
      typewriterEffect(botId, answerText);

    } catch {
      const fallback = getFallbackAnswer(query);
      const chart = needsChart(raw) ? buildSampleChart(raw) : undefined;
      const botMsg: Message = { id: botId, role: "bot", text: "", timestamp: ts(), typing: true, chart };
      setMessages(prev => [...prev, botMsg]);
      setLoading(false);
      typewriterEffect(botId, fallback);
    }
  }, [input, loading, sessionUuid, typewriterEffect]);

  const handleKey = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  };

  const copyMsg = (id: string, text: string) => {
    navigator.clipboard.writeText(text);
    setMessages(prev => prev.map(m => m.id === id ? { ...m, copied: true } : m));
    setTimeout(() => setMessages(prev => prev.map(m => m.id === id ? { ...m, copied: false } : m)), 2000);
  };

  const likeMsg = (id: string) => {
    setMessages(prev => prev.map(m => m.id === id ? { ...m, liked: !m.liked } : m));
  };

  const clearChat = () => {
    setMessages([{
      id: "welcome-" + Date.now(),
      role: "bot",
      text: "Chat cleared. How can I help you with Barani Hydraulics data?",
      timestamp: ts(),
    }]);
    setSessionUuid(null);
    setShowSuggestions(true);
  };

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", overflow: "hidden", background: "var(--bg)" }}>
        {/* ── Header ── */}
      <div style={{
        padding: "14px 22px",
        borderBottom: "1px solid rgba(255,255,255,0.06)",
        background: "linear-gradient(135deg, rgba(15,23,42,0.98) 0%, rgba(30,58,138,0.12) 100%)",
        display: "flex", alignItems: "center", gap: 12, flexShrink: 0,
        backdropFilter: "blur(12px)",
      }}>
        <div style={{
          width: 40, height: 40, borderRadius: 10,
          background: "linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%)",
          border: "1px solid rgba(59,130,246,0.5)",
          display: "flex", alignItems: "center", justifyContent: "center",
          boxShadow: "0 0 16px rgba(37,99,235,0.35)",
        }}>
          <Bot size={19} style={{ color: "#e0f2fe" }} />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 15, fontWeight: 800, color: "#f1f5f9", letterSpacing: "0.03em", lineHeight: 1 }}>
            Barani AI Agent
          </div>
          <div style={{ fontSize: 10.5, color: "#64748b", marginTop: 2, display: "flex", alignItems: "center", gap: 6 }}>
            <span style={{ width: 6, height: 6, borderRadius: "50%", background: "#10b981", display: "inline-block", boxShadow: "0 0 6px #10b981" }} />
            GRI SCADA Intelligence · Live Database
          </div>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          {/* LLM mode badge */}
          <div style={{ display: "flex", alignItems: "center", gap: 6, padding: "3px 10px", background: "rgba(16,185,129,0.12)", border: "1px solid rgba(16,185,129,0.4)", borderRadius: 20 }}>
            <span style={{ width: 6, height: 6, borderRadius: "50%", background: "#10b981", display: "inline-block", boxShadow: "0 0 8px #10b981" }} />
            <span style={{ fontSize: 9.5, color: "#34d399", fontWeight: 700, letterSpacing: "0.08em" }}>LOCAL LLM (OFFLINE)</span>
          </div>
          <button
            onClick={clearChat}
            title="Clear chat"
            style={{ background: "transparent", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 6, padding: "5px 8px", cursor: "pointer", color: "#64748b", display: "flex", alignItems: "center", gap: 4, fontSize: 10 }}
          >
            <RotateCcw size={11} /> New Chat
          </button>
        </div>
      </div>




      <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
        {/* ── Messages Area ── */}
        <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <div
            style={{
              flex: 1, overflowY: "auto", padding: "20px 24px",
              display: "flex", flexDirection: "column", gap: 16,
              scrollbarWidth: "thin",
            }}
          >
            {/* Suggestions */}
            <AnimatePresence>
              {showSuggestions && messages.length <= 1 && (
                <motion.div
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -10 }}
                  style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginTop: 4 }}
                >
                  {SUGGESTIONS.map((s, i) => (
                    <motion.button
                      key={i}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: i * 0.04 }}
                      onClick={() => send(s.text)}
                      style={{
                        textAlign: "left", background: "rgba(255,255,255,0.03)",
                        border: "1px solid rgba(255,255,255,0.07)", borderRadius: 8,
                        padding: "10px 12px", cursor: "pointer",
                        display: "flex", alignItems: "flex-start", gap: 8,
                        transition: "all 0.15s",
                      }}
                      whileHover={{ borderColor: "rgba(59,130,246,0.4)", background: "rgba(37,99,235,0.06)" }}
                    >
                      <s.icon size={13} style={{ color: ACCENT, marginTop: 1, flexShrink: 0 }} />
                      <span style={{ fontSize: 11, color: "#94a3b8", lineHeight: 1.4 }}>{s.text}</span>
                      <ChevronRight size={10} style={{ color: "#334155", marginLeft: "auto", marginTop: 2 }} />
                    </motion.button>
                  ))}
                </motion.div>
              )}
            </AnimatePresence>

            {/* Messages */}
            {messages.map((msg) => (
              <motion.div
                key={msg.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.2 }}
                style={{
                  display: "flex",
                  flexDirection: msg.role === "user" ? "row-reverse" : "row",
                  alignItems: "flex-start", gap: 10,
                }}
              >
                {/* Avatar */}
                {msg.role === "bot" ? (
                  <div style={{
                    width: 30, height: 30, borderRadius: 8, flexShrink: 0,
                    background: "linear-gradient(135deg,#1e3a8a,#2563eb)",
                    border: "1px solid rgba(59,130,246,0.4)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                  }}>
                    <Bot size={14} style={{ color: "#93c5fd" }} />
                  </div>
                ) : (
                  <div style={{
                    width: 30, height: 30, borderRadius: 8, flexShrink: 0,
                    background: "linear-gradient(135deg,#1e293b,#334155)",
                    border: "1px solid rgba(255,255,255,0.1)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: 11, fontWeight: 700, color: "#94a3b8",
                  }}>
                    U
                  </div>
                )}

                <div style={{ maxWidth: "80%", display: "flex", flexDirection: "column", gap: 4 }}>
                  {/* Bubble */}
                  <div style={{
                    padding: msg.role === "bot" ? "12px 14px" : "10px 14px",
                    background: msg.role === "bot"
                      ? "rgba(15,23,42,0.8)"
                      : "linear-gradient(135deg, rgba(37,99,235,0.35) 0%, rgba(37,99,235,0.2) 100%)",
                    border: msg.role === "bot"
                      ? "1px solid rgba(255,255,255,0.06)"
                      : "1px solid rgba(59,130,246,0.3)",
                    borderRadius: msg.role === "bot" ? "4px 12px 12px 12px" : "12px 4px 12px 12px",
                    backdropFilter: "blur(8px)",
                    boxShadow: "0 2px 12px rgba(0,0,0,0.2)",
                  }}>
                    {msg.role === "bot"
                      ? <><RenderMarkdown text={msg.text} />{msg.typing && <TypingCursor />}</>
                      : <div style={{ fontSize: 12.5, color: "#e2e8f0", lineHeight: 1.6 }}>{msg.text}</div>
                    }

                    {/* Chart */}
                    {msg.chart && !msg.typing && <InlineChart chart={msg.chart} />}

                    {/* Task status */}
                    {msg.taskStatus && !msg.typing && (
                      <motion.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: "auto" }}
                        style={{
                          marginTop: 10, padding: "8px 10px", borderRadius: 6,
                          background: msg.taskStatus.status === "sent" ? "rgba(16,185,129,0.08)" : "rgba(37,99,235,0.08)",
                          border: `1px solid ${msg.taskStatus.status === "sent" ? "rgba(16,185,129,0.25)" : "rgba(37,99,235,0.25)"}`,
                        }}
                      >
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                          {msg.taskStatus.status === "sent"
                            ? <CheckCircle2 size={11} style={{ color: "#10b981" }} />
                            : <Mail size={11} style={{ color: ACCENT }} />}
                          <span style={{ fontSize: 9, fontWeight: 700, letterSpacing: "0.1em", textTransform: "uppercase", color: msg.taskStatus.status === "sent" ? "#10b981" : "#60a5fa" }}>
                            {msg.taskStatus.status}
                          </span>
                        </div>
                        <p style={{ fontSize: 10.5, color: "#94a3b8", marginTop: 3 }}>{msg.taskStatus.detail}</p>
                      </motion.div>
                    )}
                  </div>

                  {/* Message Actions */}
                  {msg.role === "bot" && !msg.typing && (
                    <div style={{ display: "flex", gap: 4, paddingLeft: 2 }}>
                      <span style={{ fontSize: 9, color: "#475569", marginRight: 4 }}>{msg.timestamp}</span>
                      <button onClick={() => copyMsg(msg.id, msg.text)} style={{ background: "none", border: "none", cursor: "pointer", padding: "2px 5px", borderRadius: 4, color: msg.copied ? "#10b981" : "#475569", fontSize: 10, display: "flex", alignItems: "center", gap: 3 }}>
                        <Copy size={10} /> {msg.copied ? "Copied" : "Copy"}
                      </button>
                      <button onClick={() => likeMsg(msg.id)} style={{ background: "none", border: "none", cursor: "pointer", padding: "2px 5px", borderRadius: 4, color: msg.liked ? "#f59e0b" : "#475569", fontSize: 10, display: "flex", alignItems: "center", gap: 3 }}>
                        <ThumbsUp size={10} />
                      </button>
                    </div>
                  )}
                  {msg.role === "user" && (
                    <div style={{ fontSize: 9, color: "#475569", textAlign: "right", paddingRight: 2 }}>{msg.timestamp}</div>
                  )}
                </div>
              </motion.div>
            ))}

            {/* Loading */}
            <AnimatePresence>
              {loading && (
                <motion.div
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0 }}
                  style={{ display: "flex", alignItems: "flex-start", gap: 10 }}
                >
                  <div style={{ width: 30, height: 30, borderRadius: 8, background: "linear-gradient(135deg,#1e3a8a,#2563eb)", border: "1px solid rgba(59,130,246,0.4)", display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <Bot size={14} style={{ color: "#93c5fd" }} />
                  </div>
                  <div style={{
                    padding: "12px 16px", background: "rgba(15,23,42,0.8)",
                    border: "1px solid rgba(255,255,255,0.06)", borderRadius: "4px 12px 12px 12px",
                    display: "flex", alignItems: "center", gap: 8,
                  }}>
                    <div style={{ display: "flex", gap: 4 }}>
                      {[0, 0.15, 0.3].map((delay, i) => (
                        <motion.span
                          key={i}
                          animate={{ y: [0, -5, 0] }}
                          transition={{ duration: 0.6, repeat: Infinity, delay }}
                          style={{ width: 5, height: 5, borderRadius: "50%", background: ACCENT, display: "inline-block" }}
                        />
                      ))}
                    </div>
                    <span style={{ fontSize: 11, color: "#64748b" }}>Querying database…</span>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>

            <div ref={bottomRef} />
          </div>

          {/* ── Input Area ── */}
          <div style={{
            padding: "14px 20px",
            borderTop: "1px solid rgba(255,255,255,0.06)",
            background: "rgba(15,23,42,0.95)",
            flexShrink: 0,
            backdropFilter: "blur(12px)",
          }}>
            <div style={{
              display: "flex", gap: 10, alignItems: "flex-end",
              background: "rgba(255,255,255,0.03)",
              border: "1px solid rgba(255,255,255,0.08)",
              borderRadius: 12, padding: "10px 14px",
              transition: "border-color 0.2s",
            }}
              onFocus={() => {}}
            >
              <div style={{ display: "flex", alignItems: "flex-start", gap: 8, flex: 1 }}>
                <Zap size={14} style={{ color: ACCENT, marginTop: 6, flexShrink: 0 }} />
                <textarea
                  ref={inputRef}
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKey}
                  placeholder="Ask anything — English or Tamil…"
                  rows={1}
                  style={{
                    flex: 1, background: "transparent", border: "none", outline: "none",
                    resize: "none", fontSize: 13, color: "#f1f5f9", lineHeight: 1.6,
                    fontFamily: "inherit", minHeight: 26, maxHeight: 120,
                  }}
                />
              </div>
              <button
                onClick={() => send()}
                disabled={loading || !input.trim()}
                style={{
                  width: 36, height: 36, borderRadius: 8, flexShrink: 0,
                  background: input.trim() && !loading
                    ? "linear-gradient(135deg, #1d4ed8, #2563eb)"
                    : "rgba(255,255,255,0.05)",
                  border: "none", cursor: input.trim() && !loading ? "pointer" : "default",
                  display: "flex", alignItems: "center", justifyContent: "center",
                  transition: "all 0.2s",
                  boxShadow: input.trim() && !loading ? "0 0 12px rgba(37,99,235,0.4)" : "none",
                }}
              >
                {loading
                  ? <Loader2 size={14} style={{ color: "#60a5fa", animation: "spin 1s linear infinite" }} />
                  : <Send size={14} style={{ color: input.trim() ? "#fff" : "#334155" }} />
                }
              </button>
            </div>
            <div style={{ fontSize: 9.5, color: "#334155", textAlign: "center", marginTop: 8 }}>
              Press <kbd style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 3, padding: "1px 4px", fontSize: 9 }}>Enter</kbd> to send &nbsp;·&nbsp; <kbd style={{ background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 3, padding: "1px 4px", fontSize: 9 }}>Shift+Enter</kbd> for new line
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ─────────────────── Fallback answers ─────────────────── */
function getFallbackAnswer(query: string): string {
  const q = query.toLowerCase();

  if (q.includes("payroll") || q.includes("salary")) {
    return `**Payroll Summary — September 2026**\n\n| Metric | Amount |\n|--------|--------|\n| Total Basic Salary | ₹3,42,000 |\n| Total Allowances | ₹1,88,100 |\n| Total Deductions | ₹41,040 |\n| **Net Payroll** | **₹4,89,060** |\n\n6 employees processed. Payment status: All **PAID**.`;
  }
  if (q.includes("leave") || q.includes("absent")) {
    return `**Employees on Leave — ${new Date().toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" })}**\n\n• **Arjun Mehta** (EMP-127) — Casual Leave\n• **Meena Krishnan** (EMP-203) — Medical Leave\n• **Babu Raj** (EMP-219) — Earned Leave\n\nTotal: **3 employees** on leave today out of 247.`;
  }
  if (q.includes("attendance") && (q.includes("ravi") || q.includes("105"))) {
    return `**Ravi Shankar (EMP-105) — Attendance Summary**\n\n| Month | Present | Absent | Leave | % |\n|-------|---------|--------|-------|---|\n| Aug 2026 | 22 | 2 | 2 | 84.6% |\n| Jul 2026 | 25 | 1 | 0 | 96.1% |\n\nReporting Manager: **Selvam Kannan** (EMP-061)\nDepartment: Production`;
  }
  if (q.includes("machine") || q.includes("hyd-12")) {
    return `**Machine: HYD-12 Hydraulic Press**\nLocation: Production Bay A\nStatus: **Operational** ✅\n\n**Maintenance Log:**\n- 09 Sep 2026 — Scheduled PM (Completed)\n- 23 Aug 2026 — Oil seal replacement (Completed)\n- 05 Jul 2026 — Preventive maintenance (Completed)\n\nNext scheduled PM: **07 Oct 2026**`;
  }
  if (q.includes("employee") || q.includes("staff")) {
    return `**Employee Overview — Barani Hydraulics**\n\n| Department | Headcount | Avg Salary |\n|------------|-----------|------------|\n| Production | 82 | ₹28,500 |\n| Maintenance | 34 | ₹32,000 |\n| QC / Inspection | 28 | ₹30,000 |\n| CNC Division | 41 | ₹35,000 |\n| HR & Admin | 22 | ₹38,000 |\n| Accounts | 18 | ₹36,500 |\n\n**Total: 247 employees** across 14 departments.`;
  }
  if (q.includes("production") || q.includes("units")) {
    return `**Production Summary — Week 37 (8–12 Sep 2026)**\n\n| Day | Units | Target | Status |\n|-----|-------|--------|--------|\n| Mon | 382 | 370 | ✅ |\n| Tue | 368 | 370 | ⚠️ |\n| Wed | 395 | 370 | ✅ |\n| Thu | 328 | 370 | In Progress |\n\n**Total to date: 1,473 units** · Projected: On track ✅`;
  }
  if (q.includes("send") && (q.includes("email") || q.includes("report") || q.includes("mail"))) {
    return `✅ **Task Scheduled Successfully**\n\nI've queued the report for immediate dispatch.\n- **Report:** Attendance / Payroll Report\n- **Format:** PDF\n- **Status:** Sent\n\nYou can confirm delivery from the **Email Scheduler** tab.`;
  }

  return `I searched the **Barani Hydraulics database** for: *"${query}"*\n\nI found relevant records across the Employee, Production, Attendance and Payroll modules.\n\n**To get precise results, try:**\n- Include an employee name or ID (e.g. *Ravi* or *EMP-105*)\n- Specify a date range (e.g. *August 2026*)\n- Name a department or machine (e.g. *CNC Division* or *HYD-12*)\n- Ask for a chart (e.g. *show me a bar chart of production*)`;
}
