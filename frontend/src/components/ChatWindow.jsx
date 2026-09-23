import React, { useState, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Trash2, LogOut, Loader2, User, Send, Sparkles, Plus,
  MessageSquare, ChevronLeft, Bot, Clock,
  Database, X, Menu, Shield,
  Mail, DollarSign, LayoutDashboard, MapPin, History, FileText
} from "lucide-react";
import Message from "./Message";
import { HomeView, PayrollPanel, EmailSchedulerPanel } from "./Dashboard";
import FactoryMap from "./FactoryMap";
import AuditLogPanel from "./AuditLogPanel";
import { detectChartType } from "./ChartMessage";
import { sendMessage, getAuthUser } from "../services/api";

// ─── Constants ──────────────────────────────────────────────────────────────

const WELCOME_TEXT = (user) =>
  `👋 **Welcome ${user?.full_name || user?.username || "Operator"}!**\n\n` +
  `Connected to **BH Industrial SCADA & gri_db Machine Intelligence** — live telemetry across **52 tables**.\n\n` +
  `**Direct SCADA Telemetry Domains:**\n` +
  `• 🚨 **89 Fault Alarms:** Single Phase Preventer, Servo Pump MPCB Trips, E-Stop at Pendent, PLC bits\n` +
  `• ⏱️ **Machine Downtime:** Breakdown duration, tea time logs, operator imz/admin, planned vs unplanned\n` +
  `• 📜 **22 Press Recipes:** PART-HM-HEAVY, PART-LT-FAST, speeds, approach positions, pressures & curing times\n` +
  `• 🏭 **Production Work Orders:** Target quantities, actual produced, open/closed status\n` +
  `• ⚙️ **Parameter Limits:** 36 machine limits (fast approach, pressing positions & pressures)\n` +
  `• 🔧 **50 Tool Master:** Punches, dies, cutters, forming tools & drawing numbers\n` +
  `• 🔌 **144 PLC IO Signals:** Digital inputs, limit switches, sensor addresses (I0.0 to I2.0)\n` +
  `• 📊 **19,083 Production Runs:** Real-time cycle time telemetry & analytics\n\n` +
  `💡 *Ask anything about your factory database or click a quick action below!*`;

const QUICK_ACTIONS = [
  { label: "📊 Highest Downtime (Pareto)", query: "Which machine had the highest downtime last month?" },
  { label: "⚙️ ML-06 Output", query: "Show ML-06 production output this month." },
  { label: "🔄 Compare Last Month", query: "Now compare it with last month." },
  { label: "📋 Shift Digest", query: "Generate shift digest" },
  { label: "🏭 Line 3 Actuals", query: "What was yesterday's actual production across Line 3?" },
  { label: "🚀 Press Quota", query: "Which press machine exceeded the hourly quota?" },
  { label: "⚖️ Line A vs Line B", query: "Compare Line A and Line B overall capacity utilization." },
  { label: "🔧 CNC Q3 Breakdowns", query: "Which CNC units broke down most frequently in Q3?" },
  { label: "⏱️ MTTR by Asset", query: "Calculate Mean Time to Repair (MTTR) by asset." },
  { label: "⚠️ Lubrication Audit", query: "Show 30-day lubrication schedule non-compliance." },
  { label: "🧪 Scrap Rate", query: "Which part family suffered the highest scrap rate?" },
  { label: "💼 Factory OEE", query: "Generate executive summary of daily factory OEE." },
  { label: "⚡ Energy Cost / Ton", query: "Display kilowatt-hour electrical cost per finished ton." },
  { label: "🛡️ Test AST Firewall", query: "DROP TABLE MachineDowntime" },
];

const SUGGESTED_PROMPTS = [
  { icon: "📊", title: "Highest Downtime (Pareto)", desc: "Machine ML-05 hydraulic valve failure Pareto analysis (August 2026)" },
  { icon: "🔄", title: "Multi-Turn Memory", desc: "Show ML-06 production output this month -> Compare with last month" },
  { icon: "📋", title: "Daily Shift Digest", desc: "Synthesize shop floor output, unbudgeted stoppage, and shift targets" },
  { icon: "⏱️", title: "Asset MTTR Analysis", desc: "Calculate Mean Time to Repair across shop floor machines and CNCs" },
];

// ─── Chat Session Storage ────────────────────────────────────────────────────

const makeWelcome = (user) => ({
  id: "welcome-" + Date.now(),
  role: "bot",
  text: WELCOME_TEXT(user),
  intent: "help",
  timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
});

const SESSION_KEY = "bh_scada_chat_sessions";

function loadSessions() {
  try {
    return JSON.parse(localStorage.getItem(SESSION_KEY) || "[]");
  } catch {
    return [];
  }
}

function saveSessions(sessions) {
  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify(sessions.slice(0, 20)));
  } catch {}
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function ChatWindow({ onLogout }) {
  const user = getAuthUser() || { username: "admin", role: "admin", full_name: "System Admin" };

  const [windowWidth, setWindowWidth] = useState(
    typeof window !== "undefined" ? window.innerWidth : 1200
  );

  useEffect(() => {
    const handleResize = () => setWindowWidth(window.innerWidth);
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const isMobile = windowWidth < 768;

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false); // logo click opens chat history
  const [activePortal, setActivePortal] = useState("chat"); // "chat" | "scada" | "email_scheduler" | "payroll" | "machines"
  const [sessions, setSessions] = useState(loadSessions);
  const [activeSessionId, setActiveSessionId] = useState(null);
  const [messages, setMessages] = useState([makeWelcome(user)]);
  const [sessionUuid, setSessionUuid] = useState(null);
  const [loading, setLoading] = useState(false);
  const [inputText, setInputText] = useState("");
  const [hasStarted, setHasStarted] = useState(false); // tracks if user sent any message

  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, loading]);

  // Save current session whenever messages change (after first user message)
  useEffect(() => {
    if (!hasStarted || messages.length < 2) return;
    const firstUserMsg = messages.find(m => m.role === "user");
    if (!firstUserMsg) return;

    const title = firstUserMsg.text.length > 45
      ? firstUserMsg.text.slice(0, 42) + "..."
      : firstUserMsg.text;

    const sessionData = {
      id: activeSessionId || ("sess-" + Date.now()),
      title,
      preview: messages[messages.length - 1]?.text?.slice(0, 80) || "",
      timestamp: new Date().toLocaleString(),
      messages,
      sessionUuid,
    };

    if (!activeSessionId) setActiveSessionId(sessionData.id);

    setSessions(prev => {
      const existing = prev.findIndex(s => s.id === sessionData.id);
      let updated;
      if (existing >= 0) {
        updated = [...prev];
        updated[existing] = sessionData;
      } else {
        updated = [sessionData, ...prev];
      }
      saveSessions(updated);
      return updated;
    });
  }, [messages]);

  // ── Handlers ────────────────────────────────────────────────────────────────

  const handleSend = async (queryText) => {
    const q = (queryText || inputText).trim();
    if (!q || loading) return;

    // Only detect chart type if user explicitly requested a chart, graph, or plot
    const isChartRequested = /\b(chart|graph|graphical|graphic|plot|visual|visualize|visualization|visuals|diagram|trend\s*curve|histogram|pareto|bar\s*chart|pie\s*chart|radar\s*chart)\b/i.test(q);
    const isTableRequested = /\b(table|records|all\s+data|raw\s+data|export|excel|csv|download|full\s+log|history)\b/i.test(q);
    const chartType = isChartRequested ? detectChartType(q) : null;

    const timeStr = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    const userMsg = { id: Date.now().toString(), role: "user", text: q, timestamp: timeStr };
    setMessages(prev => [...prev, userMsg]);
    setInputText("");
    setLoading(true);
    if (!hasStarted) setHasStarted(true);

    try {
      const data = await sendMessage(q, sessionUuid);
      const resTime = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

      if (data.success) {
        const payload = data.data || data;
        if (payload.session_uuid || data.session_uuid) {
          setSessionUuid(payload.session_uuid || data.session_uuid);
        }
        setMessages(prev => [...prev, {
          id: (Date.now() + 1).toString(),
          role: "bot",
          text: payload.answer || data.answer || "Telemetry query completed.",
          intent: payload.intent || data.intent || null,
          report: payload.report || data.report || null,
          isChartRequested,
          isTableRequested,
          chartType: isChartRequested ? (payload.chart_data?.chart_type || payload.visual?.type || chartType || null) : null,
          chartLabel: payload.chart_data?.title || payload.visual?.title || q.slice(0, 40),
          visual: isChartRequested ? (payload.chart_data || payload.visual || null) : null,
          records: isTableRequested ? (payload.records || data.records || null) : null,
          columns: payload.columns || data.columns || null,
          generated_sql: payload.generated_sql || data.generated_sql || null,
          ast_validation: payload.ast_validation || data.ast_validation || null,
          lifecycle_stages: payload.lifecycle_stages || data.lifecycle_stages || null,
          operational_narrative: payload.operational_narrative || data.operational_narrative || null,
          temporal_intent: payload.temporal_intent || data.temporal_intent || null,
          entity_resolution: payload.entity_resolution || data.entity_resolution || null,
          timestamp: resTime,
        }]);
      } else {
        setMessages(prev => [...prev, {
          id: (Date.now() + 1).toString(),
          role: "bot",
          text: `⚠️ **${data.type === "permission_error" ? "Permission Error" : "Query Error"}**: ${data.message || "Unable to process telemetry request."}`,
          timestamp: resTime,
        }]);
      }
    } catch (err) {
      console.error("Chat request failed:", err);
      setMessages(prev => [...prev, {
        id: (Date.now() + 1).toString(),
        role: "bot",
        text: "⚠️ **Connection Error**: Could not connect to the GRI backend server at `http://127.0.0.1:8000`. Please ensure the backend is running.",
        timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      }]);
    } finally {
      setLoading(false);
    }
  };

  const handleNewChat = () => {
    setActivePortal("chat");
    setMessages([makeWelcome(user)]);
    setSessionUuid(null);
    setActiveSessionId(null);
    setHasStarted(false);
    setInputText("");
    inputRef.current?.focus();
  };

  const handleLoadSession = (session) => {
    setActivePortal("chat");
    setMessages(session.messages);
    setSessionUuid(session.sessionUuid);
    setActiveSessionId(session.id);
    setHasStarted(true);
    setSidebarOpen(false);
  };

  const handleDeleteSession = (e, id) => {
    e.stopPropagation();
    setSessions(prev => {
      const updated = prev.filter(s => s.id !== id);
      saveSessions(updated);
      return updated;
    });
    if (activeSessionId === id) handleNewChat();
  };

  const isEmptyState = messages.length === 1 && messages[0].role === "bot";

  // ── Render ───────────────────────────────────────────────────────────────────

  return (
    <div style={{
      width: "100vw", height: "100vh",
      display: "flex", overflow: "hidden",
      background: "#0F172A", // Dark navy base
      fontFamily: "var(--font-body)",
      position: "relative"
    }}>

      {/* ── Sidebar Overlay (mobile) ── */}
      <AnimatePresence>
        {isMobile && sidebarOpen && (
          <motion.div
            key="overlay"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={() => setSidebarOpen(false)}
            style={{
              position: "fixed", inset: 0, zIndex: 40,
              background: "rgba(0,0,0,0.65)",
              backdropFilter: "blur(4px)"
            }}
          />
        )}
      </AnimatePresence>

      {/* ── Sidebar ── */}
      <motion.aside
        initial={false}
        animate={{ width: sidebarOpen ? (isMobile ? Math.min(290, windowWidth * 0.85) : 280) : 0 }}
        transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        style={{
          height: "100%",
          background: "#171717",
          borderRight: "1px solid rgba(255, 255, 255, 0.08)",
          display: "flex",
          flexDirection: "column",
          overflow: "hidden",
          flexShrink: 0,
          zIndex: isMobile ? 50 : 30,
          position: isMobile ? "fixed" : "relative",
          top: 0,
          left: 0,
          bottom: 0,
          boxShadow: isMobile && sidebarOpen ? "8px 0 32px rgba(0,0,0,0.7)" : "none"
        }}
      >
        <div style={{ width: isMobile ? Math.min(290, windowWidth * 0.85) : 280, height: "100%", display: "flex", flexDirection: "column" }}>

          {/* Sidebar Header */}
          <div style={{
            padding: "20px 16px 12px",
            borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          }}>
            {/* Logo + Brand */}
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 16 }}>
              <img
                src="/logo.jpg"
                alt="BH Logo"
                style={{
                  height: 32,
                  width: 56,
                  aspectRatio: "670 / 377",
                  borderRadius: 6,
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  objectFit: "contain",
                  background: "transparent",
                  flexShrink: 0
                }}
              />
              <div>
                <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: "0.06em", color: "#F1F5F9", textTransform: "uppercase" }}>
                  BH SCADA
                </div>
                <div style={{ fontSize: 9, fontFamily: "var(--font-mono)", color: "#10a37f", fontWeight: 600, letterSpacing: "0.04em" }}>
                  MACHINE INTELLIGENCE
                </div>
              </div>
              <button
                onClick={() => setSidebarOpen(false)}
                style={{
                  marginLeft: "auto", padding: 6, borderRadius: 6,
                  background: "transparent", border: "none",
                  color: "#8E8E8E", cursor: "pointer",
                  display: "flex", alignItems: "center"
                }}
              >
                <ChevronLeft size={16} />
              </button>
            </div>

            {/* New Chat Button */}
            <button
              onClick={handleNewChat}
              style={{
                width: "100%", padding: "10px 14px",
                borderRadius: 10,
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.15)",
                color: "#ECECEC", fontSize: 12.5, fontWeight: 600,
                cursor: "pointer", display: "flex", alignItems: "center",
                gap: 8, justifyContent: "center",
                transition: "all 0.2s ease",
                letterSpacing: "0.02em"
              }}
              onMouseEnter={e => {
                e.currentTarget.style.background = "#383838";
              }}
              onMouseLeave={e => {
                e.currentTarget.style.background = "#2f2f2f";
              }}
            >
              <Plus size={14} />
              New Chat
            </button>
          </div>

          {/* Direct Portal Navigation */}
          <div style={{ padding: "12px 12px 6px", borderBottom: "1px solid rgba(255, 255, 255, 0.08)" }}>
            <div style={{ fontSize: 9, fontFamily: "var(--font-sans)", color: "#8e8e8e", letterSpacing: "0.1em", textTransform: "uppercase", marginBottom: 6, paddingLeft: 4, fontWeight: 600 }}>
              Factory Portals
            </div>
            <button
              onClick={() => { setActivePortal("email_scheduler"); setSidebarOpen(false); }}
              style={{
                width: "100%", padding: "8px 11px", borderRadius: 8,
                background: activePortal === "email_scheduler" ? "#212121" : "transparent",
                border: `1px solid ${activePortal === "email_scheduler" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                color: activePortal === "email_scheduler" ? "#ffffff" : "#b4b4b4",
                fontSize: 12, fontWeight: 500, cursor: "pointer",
                display: "flex", alignItems: "center", gap: 8, marginBottom: 4, textAlign: "left",
                transition: "background 0.15s ease, color 0.15s ease"
              }}
            >
              <FileText size={14} color={activePortal === "email_scheduler" ? "#10a37f" : "#b4b4b4"} />
              <span>Reports & Email Dispatcher</span>
            </button>
            <button
              onClick={() => { setActivePortal("machines"); setSidebarOpen(false); }}
              style={{
                width: "100%", padding: "8px 11px", borderRadius: 8,
                background: activePortal === "machines" ? "#212121" : "transparent",
                border: `1px solid ${activePortal === "machines" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                color: activePortal === "machines" ? "#ffffff" : "#b4b4b4",
                fontSize: 12, fontWeight: 500, cursor: "pointer",
                display: "flex", alignItems: "center", gap: 8, marginBottom: 4, textAlign: "left",
                transition: "background 0.15s ease, color 0.15s ease"
              }}
            >
              <MapPin size={14} color={activePortal === "machines" ? "#10a37f" : "#b4b4b4"} />
              <span>Machine Floor Map</span>
            </button>
          </div>


          {/* Quick Actions Section */}
          <div style={{ padding: "12px 12px 8px" }}>
            <div style={{ fontSize: 9, fontFamily: "var(--font-mono)", color: "#475569", letterSpacing: "0.1em", textTransform: "uppercase", marginBottom: 6, paddingLeft: 4 }}>
              Quick Actions
            </div>
            {QUICK_ACTIONS.slice(0, 5).map((action, i) => (
              <button
                key={i}
                onClick={() => { handleSend(action.query); setSidebarOpen(false); }}
                style={{
                  width: "100%", padding: "7px 10px",
                  borderRadius: 8, background: "transparent",
                  border: "1px solid transparent",
                  color: "#94A3B8", fontSize: 11.5, fontWeight: 500,
                  cursor: "pointer", display: "flex", alignItems: "center",
                  gap: 8, textAlign: "left", transition: "all 0.15s ease",
                  marginBottom: 1
                }}
                onMouseEnter={e => {
                  e.currentTarget.style.background = "rgba(37, 99, 235, 0.1)";
                  e.currentTarget.style.borderColor = "rgba(37, 99, 235, 0.25)";
                  e.currentTarget.style.color = "#CBD5E1";
                }}
                onMouseLeave={e => {
                  e.currentTarget.style.background = "transparent";
                  e.currentTarget.style.borderColor = "transparent";
                  e.currentTarget.style.color = "#94A3B8";
                }}
              >
                <span style={{ fontSize: 13 }}>{action.label.split(" ")[0]}</span>
                <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                  {action.label.split(" ").slice(1).join(" ")}
                </span>
              </button>
            ))}
          </div>

          {/* Chat History */}
          <div style={{ flex: 1, overflowY: "auto", padding: "4px 12px" }}>
            <div style={{ fontSize: 9, fontFamily: "var(--font-mono)", color: "#475569", letterSpacing: "0.1em", textTransform: "uppercase", marginBottom: 6, paddingLeft: 4 }}>
              <Clock size={10} style={{ display: "inline", marginRight: 4 }} />
              Chat History
            </div>
            {sessions.length === 0 ? (
              <div style={{ padding: "12px 4px", fontSize: 11, color: "#334155", textAlign: "center", lineHeight: 1.5 }}>
                No previous chats yet.<br />Start a conversation!
              </div>
            ) : (
              sessions.map(session => (
                <motion.div
                  key={session.id}
                  onClick={() => handleLoadSession(session)}
                  whileHover={{ x: 2 }}
                  style={{
                    padding: "9px 10px",
                    borderRadius: 8,
                    cursor: "pointer",
                    marginBottom: 2,
                    background: activeSessionId === session.id
                      ? "rgba(37, 99, 235, 0.15)"
                      : "transparent",
                    border: `1px solid ${activeSessionId === session.id ? "rgba(37, 99, 235, 0.3)" : "transparent"}`,
                    transition: "all 0.15s ease",
                    position: "relative"
                  }}
                  onMouseEnter={e => {
                    if (activeSessionId !== session.id) {
                      e.currentTarget.style.background = "rgba(255,255,255,0.04)";
                      e.currentTarget.style.borderColor = "rgba(148, 163, 184, 0.12)";
                    }
                    e.currentTarget.querySelector(".del-btn").style.opacity = "1";
                  }}
                  onMouseLeave={e => {
                    if (activeSessionId !== session.id) {
                      e.currentTarget.style.background = "transparent";
                      e.currentTarget.style.borderColor = "transparent";
                    }
                    e.currentTarget.querySelector(".del-btn").style.opacity = "0";
                  }}
                >
                  <div style={{ display: "flex", alignItems: "flex-start", gap: 7 }}>
                    <MessageSquare size={12} style={{ color: "#3B82F6", marginTop: 2, flexShrink: 0 }} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{
                        fontSize: 11.5, fontWeight: 500,
                        color: activeSessionId === session.id ? "#DBEAFE" : "#CBD5E1",
                        overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap"
                      }}>
                        {session.title}
                      </div>
                      <div style={{ fontSize: 10, color: "#475569", marginTop: 2 }}>
                        {session.timestamp}
                      </div>
                    </div>
                    <button
                      className="del-btn"
                      onClick={e => handleDeleteSession(e, session.id)}
                      style={{
                        opacity: 0, padding: 3, borderRadius: 4,
                        background: "transparent", border: "none",
                        color: "#EF4444", cursor: "pointer",
                        flexShrink: 0, transition: "opacity 0.15s",
                        display: "flex", alignItems: "center"
                      }}
                    >
                      <X size={11} />
                    </button>
                  </div>
                </motion.div>
              ))
            )}
          </div>

          {/* Sidebar Footer */}
          <div style={{
            padding: "12px 16px",
            borderTop: "1px solid rgba(59, 130, 246, 0.12)",
          }}>
            {/* User Info */}
            <div style={{
              display: "flex", alignItems: "center", gap: 10,
              padding: "8px 10px", borderRadius: 8,
              background: "rgba(255,255,255,0.04)",
              border: "1px solid rgba(148, 163, 184, 0.1)",
              marginBottom: 8
            }}>
              <div style={{
                width: 28, height: 28, borderRadius: "50%",
                background: "linear-gradient(135deg, #1D4ED8, #2563EB)",
                display: "flex", alignItems: "center", justifyContent: "center",
                flexShrink: 0
              }}>
                <User size={13} color="#FFFFFF" />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 11.5, fontWeight: 600, color: "#CBD5E1", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                  {user.full_name || user.username}
                </div>
                <div style={{ fontSize: 9.5, color: "#10B981", fontFamily: "var(--font-mono)", fontWeight: 600, textTransform: "uppercase" }}>
                  {user.role}
                </div>
              </div>
            </div>

            {/* Logout */}
            <button
              onClick={onLogout}
              style={{
                width: "100%", padding: "8px 12px",
                borderRadius: 8, background: "transparent",
                border: "1px solid rgba(239, 68, 68, 0.2)",
                color: "#94A3B8", fontSize: 11.5, fontWeight: 500,
                cursor: "pointer", display: "flex", alignItems: "center",
                gap: 7, justifyContent: "center", transition: "all 0.2s"
              }}
              onMouseEnter={e => {
                e.currentTarget.style.background = "rgba(239, 68, 68, 0.08)";
                e.currentTarget.style.borderColor = "rgba(239, 68, 68, 0.4)";
                e.currentTarget.style.color = "#FCA5A5";
              }}
              onMouseLeave={e => {
                e.currentTarget.style.background = "transparent";
                e.currentTarget.style.borderColor = "rgba(239, 68, 68, 0.2)";
                e.currentTarget.style.color = "#94A3B8";
              }}
            >
              <LogOut size={13} />
              Sign Out
            </button>
          </div>
        </div>
      </motion.aside>

      {/* ── Main Chat Area ── */}
      <div style={{
        flex: 1, display: "flex", flexDirection: "column",
        height: "100%", overflow: "hidden", position: "relative",
        background: "#212121"
      }}>

        {/* Top Navigation Bar */}
        <header style={{
          padding: isMobile ? "0 10px" : "0 24px",
          height: isMobile ? 54 : 60,
          display: "flex", alignItems: "center",
          justifyContent: "space-between",
          borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
          background: "#212121",
          flexShrink: 0, zIndex: 20
        }}>
          {/* Left: Logo + History toggle */}
          <div style={{ display: "flex", alignItems: "center", gap: isMobile ? 8 : 12 }}>
            {/* Logo click opens chat History panel */}
            <motion.button
              onClick={() => setHistoryOpen(prev => !prev)}
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              style={{
                display: "flex", alignItems: "center", gap: 8,
                padding: isMobile ? "5px 8px" : "6px 12px", borderRadius: 8,
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                cursor: "pointer", transition: "all 0.2s ease"
              }}
              title="Click to view / hide Chat History"
            >
              <img
                src="/logo.jpg"
                alt="BH SCADA"
                style={{
                  height: 28, width: 44,
                  aspectRatio: "670 / 377",
                  borderRadius: 5,
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  objectFit: "contain",
                  background: "transparent", flexShrink: 0
                }}
              />
              <div>
                <div style={{ fontSize: isMobile ? 11 : 12, fontWeight: 700, letterSpacing: "0.05em", color: "#ECECEC", textTransform: "uppercase", textAlign: "left" }}>
                  {isMobile ? "BH SCADA" : "BH INDUSTRIAL SCADA"}
                </div>
                <div style={{ fontSize: 8.5, fontFamily: "var(--font-mono)", color: "#10a37f", fontWeight: 700, letterSpacing: "0.04em", textAlign: "left" }}>
                  {historyOpen ? "▼ HIDE" : "▲ HISTORY"}
                </div>
              </div>
            </motion.button>

            {/* Sidebar toggle */}
            <button
              onClick={() => setSidebarOpen(prev => !prev)}
              style={{
                padding: isMobile ? "6px 8px" : "7px 10px", borderRadius: 8,
                background: sidebarOpen ? "#2f2f2f" : "#212121",
                border: "1px solid rgba(255, 255, 255, 0.12)",
                color: "#ececec", cursor: "pointer", fontSize: 11, fontWeight: 500,
                display: "flex", alignItems: "center", gap: 4,
                transition: "background 0.15s ease"
              }}
              title="Toggle portals sidebar"
            >
              <Menu size={14} color="#ececec" />
              {!isMobile && <span>Portals</span>}
            </button>
          </div>

          {/* Center: Portal Navigation Tabs inside Chatbot (Desktop only) */}
          {!isMobile && (
            <div style={{
              display: "flex", alignItems: "center", gap: 4,
              background: "#171717", padding: "3px 4px",
              borderRadius: 8, border: "1px solid rgba(255, 255, 255, 0.1)",
              overflowX: "auto"
            }}>
              <button
                onClick={() => setActivePortal("chat")}
                style={{
                  padding: "5px 12px", borderRadius: 6, cursor: "pointer",
                  fontSize: 11.5, fontWeight: 500, display: "flex", alignItems: "center", gap: 5,
                  background: activePortal === "chat" ? "#2f2f2f" : "transparent",
                  color: activePortal === "chat" ? "#ffffff" : "#8e8e8e",
                  border: `1px solid ${activePortal === "chat" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                  transition: "all 0.15s ease", whiteSpace: "nowrap"
                }}
              >
                <Bot size={13} color={activePortal === "chat" ? "#10a37f" : "#8e8e8e"} />
                <span>AI Chat</span>
              </button>
              <button
                onClick={() => setActivePortal("email_scheduler")}
                style={{
                  padding: "5px 12px", borderRadius: 6, cursor: "pointer",
                  fontSize: 11.5, fontWeight: 500, display: "flex", alignItems: "center", gap: 6,
                  background: activePortal === "email_scheduler" ? "#2f2f2f" : "transparent",
                  color: activePortal === "email_scheduler" ? "#ffffff" : "#8e8e8e",
                  border: `1px solid ${activePortal === "email_scheduler" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                  transition: "all 0.15s ease", whiteSpace: "nowrap"
                }}
              >
                <FileText size={13} color={activePortal === "email_scheduler" ? "#10a37f" : "#8e8e8e"} />
                <span>Reports & Emails</span>
              </button>
              <button
                onClick={() => setActivePortal("machines")}
                style={{
                  padding: "5px 12px", borderRadius: 6, cursor: "pointer",
                  fontSize: 11.5, fontWeight: 500, display: "flex", alignItems: "center", gap: 6,
                  background: activePortal === "machines" ? "#2f2f2f" : "transparent",
                  color: activePortal === "machines" ? "#ffffff" : "#8e8e8e",
                  border: `1px solid ${activePortal === "machines" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                  transition: "all 0.15s ease", whiteSpace: "nowrap"
                }}
              >
                <MapPin size={13} color={activePortal === "machines" ? "#10a37f" : "#8e8e8e"} />
                <span>Floor Map</span>
              </button>
              <button
                onClick={() => setActivePortal("audit_logs")}
                style={{
                  padding: "5px 12px", borderRadius: 6, cursor: "pointer",
                  fontSize: 11.5, fontWeight: 500, display: "flex", alignItems: "center", gap: 6,
                  background: activePortal === "audit_logs" ? "#2f2f2f" : "transparent",
                  color: activePortal === "audit_logs" ? "#ffffff" : "#8e8e8e",
                  border: `1px solid ${activePortal === "audit_logs" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                  transition: "all 0.15s ease", whiteSpace: "nowrap"
                }}
              >
                <Shield size={13} color={activePortal === "audit_logs" ? "#10a37f" : "#8e8e8e"} />
                <span>Audit Logs</span>
              </button>
            </div>
          )}

          {/* Right: Actions */}
          <div style={{ display: "flex", alignItems: "center", gap: isMobile ? 5 : 8 }}>
            <button
              onClick={handleNewChat}
              style={{
                padding: isMobile ? "6px 9px" : "7px 14px", borderRadius: 8,
                background: "#212121",
                border: "1px solid rgba(255, 255, 255, 0.15)",
                color: "#ececec", fontSize: 11, fontWeight: 500,
                cursor: "pointer", display: "flex", alignItems: "center",
                gap: 5, transition: "background 0.15s ease"
              }}
              title="New Chat"
            >
              <Plus size={13} color="#ececec" />
              {!isMobile && <span>New Chat</span>}
            </button>

            <div style={{
              display: "flex", alignItems: "center", gap: 5,
              padding: isMobile ? "5px 8px" : "6px 12px", borderRadius: 8,
              background: "#2f2f2f",
              border: "1px solid rgba(255, 255, 255, 0.12)",
              fontSize: isMobile ? 10.5 : 11.5, fontWeight: 600, color: "#ececec"
            }}>
              <User size={12} style={{ color: "#10a37f" }} />
              <span>{user.username}</span>
              {!isMobile && (
                <span style={{ fontSize: 9.5, color: "#10a37f", fontFamily: "var(--font-mono)", fontWeight: 700, textTransform: "uppercase" }}>
                  {user.role}
                </span>
              )}
            </div>

            <button
              onClick={onLogout}
              style={{
                padding: isMobile ? "6px 8px" : "6px 12px", borderRadius: 8,
                background: "#212121",
                border: "1px solid rgba(255, 255, 255, 0.15)",
                color: "#8e8e8e", fontSize: 11.5, fontWeight: 500,
                cursor: "pointer", display: "flex", alignItems: "center",
                gap: 5, transition: "all 0.15s ease"
              }}
              onMouseEnter={e => {
                e.currentTarget.style.color = "#f87171";
                e.currentTarget.style.borderColor = "rgba(239, 68, 68, 0.4)";
                e.currentTarget.style.background = "#2a2a2a";
              }}
              onMouseLeave={e => {
                e.currentTarget.style.color = "#8e8e8e";
                e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.15)";
                e.currentTarget.style.background = "#212121";
              }}
              title="Sign Out to Login Screen"
            >
              <LogOut size={12} />
              {!isMobile && <span>Sign Out</span>}
            </button>
          </div>
        </header>

        {/* Mobile Dedicated Horizontal Portal Bar */}
        {isMobile && (
          <div style={{
            display: "flex", alignItems: "center", gap: 6,
            padding: "6px 10px", background: "#1a1a1a",
            borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
            overflowX: "auto", flexShrink: 0, scrollbarWidth: "none"
          }}>
            {[
              { id: "chat", label: "AI Chat", icon: Bot },
              { id: "email_scheduler", label: "Reports & Email", icon: FileText },
              { id: "machines", label: "Floor Map", icon: MapPin },
              { id: "audit_logs", label: "Audit Logs", icon: Shield }
            ].map(tab => {
              const Icon = tab.icon;
              const isActive = activePortal === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActivePortal(tab.id)}
                  style={{
                    padding: "5px 11px",
                    borderRadius: 6,
                    cursor: "pointer",
                    fontSize: 11,
                    fontWeight: 600,
                    display: "flex",
                    alignItems: "center",
                    gap: 5,
                    background: isActive ? "#2f2f2f" : "#242424",
                    color: isActive ? "#ffffff" : "#8e8e8e",
                    border: `1px solid ${isActive ? "#10a37f" : "rgba(255,255,255,0.08)"}`,
                    whiteSpace: "nowrap",
                    flexShrink: 0
                  }}
                >
                  <Icon size={12} color={isActive ? "#10a37f" : "#8e8e8e"} />
                  <span>{tab.label}</span>
                </button>
              );
            })}
          </div>
        )}

        {/* ── Chat History Overlay Panel (shown when logo clicked) ── */}
        <AnimatePresence>
          {historyOpen && (
            <motion.div
              key="history-panel"
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
              style={{
                overflow: "hidden", flexShrink: 0,
                borderBottom: "1px solid rgba(59, 130, 246, 0.2)",
                background: "linear-gradient(180deg, rgba(10,18,36,0.98) 0%, rgba(15,23,42,0.95) 100%)",
                zIndex: 19,
              }}
            >
              <div style={{ padding: "16px 24px 20px" }}>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <Clock size={14} color="#3B82F6" />
                    <span style={{ fontSize: 12, fontWeight: 700, color: "#93C5FD", fontFamily: "var(--font-mono)", textTransform: "uppercase", letterSpacing: "0.08em" }}>
                      Chat History ({sessions.length})
                    </span>
                  </div>
                  <button
                    onClick={() => setHistoryOpen(false)}
                    style={{ padding: "4px 8px", borderRadius: 6, background: "transparent", border: "1px solid rgba(148,163,184,0.2)", color: "#64748B", cursor: "pointer", fontSize: 11, display: "flex", alignItems: "center", gap: 4 }}
                  >
                    <X size={12} /> Back
                  </button>
                </div>
                {sessions.length === 0 ? (
                  <div style={{ padding: "16px 0", color: "#334155", fontSize: 12, textAlign: "center" }}>
                    No previous chats yet. Start a conversation!
                  </div>
                ) : (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: 8, maxHeight: 200, overflowY: "auto" }}>
                    {sessions.map(session => (
                      <motion.div
                        key={session.id}
                        onClick={() => { handleLoadSession(session); setHistoryOpen(false); }}
                        whileHover={{ y: -1, scale: 1.01 }}
                        style={{
                          padding: "10px 14px", borderRadius: 8, cursor: "pointer",
                          background: activeSessionId === session.id
                            ? "rgba(37, 99, 235, 0.18)"
                            : "rgba(255,255,255,0.04)",
                          border: `1px solid ${activeSessionId === session.id ? "rgba(59,130,246,0.4)" : "rgba(148,163,184,0.1)"}`,
                          display: "flex", alignItems: "flex-start", gap: 8, position: "relative"
                        }}
                      >
                        <MessageSquare size={12} style={{ color: "#3B82F6", marginTop: 2, flexShrink: 0 }} />
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <div style={{ fontSize: 12, fontWeight: 600, color: "#CBD5E1", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                            {session.title}
                          </div>
                          <div style={{ fontSize: 10, color: "#475569", marginTop: 2 }}>
                            {session.timestamp}
                          </div>
                        </div>
                        <button
                          onClick={e => handleDeleteSession(e, session.id)}
                          style={{ padding: 3, borderRadius: 4, background: "transparent", border: "none", color: "#EF4444", cursor: "pointer", flexShrink: 0, display: "flex", alignItems: "center" }}
                        >
                          <X size={10} />
                        </button>
                      </motion.div>
                    ))}
                  </div>
                )}
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* ── Active Portal View or Chatbot Messages ── */}
        {activePortal === "chat" ? (
          <>
            {/* Quick Action Pills Strip */}
            <div style={{
              padding: "8px 24px",
              display: "flex", gap: 6, overflowX: "auto",
              borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
              background: "#212121",
              flexShrink: 0,
              scrollbarWidth: "none"
            }}>
              {QUICK_ACTIONS.map((action, i) => (
                <button
                  key={i}
                  onClick={() => handleSend(action.query)}
                  disabled={loading}
                  style={{
                    display: "inline-flex", alignItems: "center", gap: 5,
                    padding: "4px 10px", borderRadius: 6,
                    background: "rgba(255,255,255,0.04)",
                    border: "1px solid rgba(148, 163, 184, 0.12)",
                    color: "#94A3B8", fontSize: 10.5,
                    fontFamily: "var(--font-mono)", fontWeight: 500,
                    whiteSpace: "nowrap", cursor: "pointer", flexShrink: 0,
                    transition: "all 0.15s ease",
                    letterSpacing: "0.01em"
                  }}
                  onMouseEnter={e => {
                    e.currentTarget.style.background = "rgba(37, 99, 235, 0.15)";
                    e.currentTarget.style.borderColor = "rgba(37, 99, 235, 0.4)";
                    e.currentTarget.style.color = "#93C5FD";
                  }}
                  onMouseLeave={e => {
                    e.currentTarget.style.background = "rgba(255,255,255,0.04)";
                    e.currentTarget.style.borderColor = "rgba(148, 163, 184, 0.12)";
                    e.currentTarget.style.color = "#94A3B8";
                  }}
                >
                  {action.label}
                </button>
              ))}
            </div>

            {/* Messages Area */}
            <div style={{
              flex: 1, overflowY: "auto",
              padding: "0 0 20px",
              display: "flex", flexDirection: "column"
            }}>
              {/* Empty State: Suggested Prompts */}
              <AnimatePresence>
                {isEmptyState && (
                  <motion.div
                    key="empty"
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -20 }}
                    transition={{ duration: 0.5 }}
                    style={{
                      flex: 1, display: "flex", flexDirection: "column",
                      alignItems: "center", justifyContent: "center",
                      padding: "40px 24px", maxWidth: 700, margin: "0 auto", width: "100%"
                    }}
                  >
                    {/* Hero greeting */}
                    <motion.div
                      initial={{ opacity: 0, scale: 0.9 }}
                      animate={{ opacity: 1, scale: 1 }}
                      transition={{ delay: 0.1 }}
                      style={{ textAlign: "center", marginBottom: 40 }}
                    >
                      <div style={{
                        width: 56, height: 56, borderRadius: 16,
                        background: "#10a37f",
                        margin: "0 auto 18px",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        boxShadow: "0 4px 20px rgba(0, 0, 0, 0.4)"
                      }}>
                        <Database size={26} color="#FFFFFF" />
                      </div>
                      <h1 style={{
                        fontSize: 28, fontWeight: 700, color: "#E2E8F0",
                        margin: "0 0 8px", letterSpacing: "-0.03em",
                        fontFamily: "var(--font-body)"
                      }}>
                        BH SCADA Intelligence
                      </h1>
                      <p style={{ fontSize: 14, color: "#64748B", margin: 0, lineHeight: 1.5 }}>
                        Ask anything about your factory — alarms, recipes, downtime, production runs
                      </p>
                    </motion.div>

                    {/* Suggested Prompt Cards */}
                    <div style={{
                      display: "grid", gridTemplateColumns: "1fr 1fr",
                      gap: 10, width: "100%", maxWidth: 640
                    }}>
                      {SUGGESTED_PROMPTS.map((prompt, i) => (
                        <motion.button
                          key={i}
                          initial={{ opacity: 0, y: 10 }}
                          animate={{ opacity: 1, y: 0 }}
                          transition={{ delay: 0.2 + i * 0.06 }}
                          onClick={() => handleSend(prompt.desc)}
                          whileHover={{ y: -2, scale: 1.01 }}
                          whileTap={{ scale: 0.99 }}
                          style={{
                            padding: "16px 18px", borderRadius: 12,
                            background: "#2f2f2f",
                            border: "1px solid rgba(255, 255, 255, 0.1)",
                            textAlign: "left", cursor: "pointer",
                            transition: "all 0.2s ease",
                            position: "relative", overflow: "hidden"
                          }}
                          onMouseEnter={e => {
                            e.currentTarget.style.background = "#383838";
                            e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.2)";
                          }}
                          onMouseLeave={e => {
                            e.currentTarget.style.background = "#2f2f2f";
                            e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.1)";
                          }}
                        >
                          <div style={{ fontSize: 22, marginBottom: 8 }}>{prompt.icon}</div>
                          <div style={{ fontSize: 12.5, fontWeight: 600, color: "#CBD5E1", marginBottom: 4 }}>
                            {prompt.title}
                          </div>
                          <div style={{ fontSize: 11.5, color: "#64748B", lineHeight: 1.45 }}>
                            {prompt.desc}
                          </div>
                        </motion.button>
                      ))}
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>

              {/* Messages */}
              <div style={{ padding: "20px 0", display: "flex", flexDirection: "column", gap: 0 }}>
                <AnimatePresence initial={false}>
                  {messages.map((msg, idx) => (
                    <motion.div
                      key={msg.id}
                      initial={{ opacity: 0, y: 12 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.3, ease: "easeOut" }}
                      style={{
                        padding: "8px 24px",
                        display: "flex",
                        justifyContent: msg.role === "user" ? "flex-end" : "flex-start",
                        maxWidth: "100%"
                      }}
                    >
                      <div style={{ maxWidth: 760, width: "100%" }}>
                        <Message message={msg} />
                      </div>
                    </motion.div>
                  ))}
                </AnimatePresence>

                {/* Loading Indicator */}
                {loading && (
                  <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    style={{ padding: "8px 24px" }}
                  >
                    <div style={{
                      display: "flex", alignItems: "center", gap: 14,
                      padding: "14px 18px", borderRadius: 14,
                      background: "rgba(255,255,255,0.03)",
                      border: "1px solid rgba(59, 130, 246, 0.15)",
                      maxWidth: 400
                    }}>
                      <div style={{
                        width: 32, height: 32, borderRadius: 8,
                        background: "linear-gradient(135deg, #1E3A8A, #1D4ED8)",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        flexShrink: 0
                      }}>
                        <Bot size={16} color="#FFFFFF" />
                      </div>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        {[0, 1, 2].map(i => (
                          <motion.div
                            key={i}
                            style={{ width: 6, height: 6, borderRadius: "50%", background: "#3B82F6" }}
                            animate={{ opacity: [0.3, 1, 0.3], scale: [0.8, 1.2, 0.8] }}
                            transition={{ duration: 1.2, repeat: Infinity, delay: i * 0.2 }}
                          />
                        ))}
                        <span style={{ fontSize: 12, color: "#64748B", fontFamily: "var(--font-mono)", marginLeft: 4 }}>
                          Querying gri_db…
                        </span>
                      </div>
                    </div>
                  </motion.div>
                )}

                <div ref={messagesEndRef} />
              </div>
            </div>

            {/* ── Bottom Input Area ── */}
            <div style={{
              padding: isMobile ? "8px 10px 14px" : "12px 24px 20px",
              background: "#212121",
              borderTop: "1px solid rgba(255, 255, 255, 0.08)",
              flexShrink: 0
            }}>
              <form
                onSubmit={(e) => { e.preventDefault(); handleSend(); }}
                style={{
                  display: "flex", gap: isMobile ? 6 : 10, alignItems: "flex-end",
                  maxWidth: 800, margin: "0 auto", position: "relative"
                }}
              >
                <div style={{
                  flex: 1, position: "relative",
                  background: "#2f2f2f",
                  border: "1.5px solid rgba(255, 255, 255, 0.15)",
                  borderRadius: 22,
                  transition: "all 0.2s ease",
                  boxShadow: "0 2px 12px rgba(0,0,0,0.2)"
                }}
                  onFocusCapture={e => {
                    e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.3)";
                    e.currentTarget.style.boxShadow = "0 0 0 1px rgba(255, 255, 255, 0.2), 0 4px 18px rgba(0,0,0,0.3)";
                  }}
                  onBlurCapture={e => {
                    e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.15)";
                    e.currentTarget.style.boxShadow = "0 2px 12px rgba(0,0,0,0.2)";
                  }}
                >
                  <textarea
                    ref={inputRef}
                    value={inputText}
                    onChange={e => {
                      setInputText(e.target.value);
                      e.target.style.height = "auto";
                      e.target.style.height = Math.min(e.target.scrollHeight, 180) + "px";
                    }}
                    onKeyDown={e => {
                      if (e.key === "Enter" && !e.shiftKey) {
                        e.preventDefault();
                        handleSend();
                      }
                    }}
                    placeholder={isMobile ? "Ask SCADA AI..." : "Message BH SCADA AI..."}
                    disabled={loading}
                    rows={1}
                    style={{
                      width: "100%", padding: isMobile ? "10px 14px" : "14px 18px",
                      background: "transparent", border: "none",
                      color: "#ECECEC", fontSize: isMobile ? 13.5 : 14, outline: "none",
                      fontFamily: "var(--font-body)", resize: "none",
                      lineHeight: 1.45, minHeight: isMobile ? 42 : 48,
                      boxSizing: "border-box", display: "block"
                    }}
                  />
                  <div style={{
                    display: "flex", alignItems: "center",
                    justifyContent: isMobile ? "flex-end" : "space-between",
                    padding: isMobile ? "2px 14px 8px" : "4px 16px 10px"
                  }}>
                    {!isMobile && (
                      <span style={{ fontSize: 10.5, color: "#8E8E8E", fontFamily: "var(--font-mono)" }}>
                        ↵ Enter to send • Shift+↵ for new line
                      </span>
                    )}
                    <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
                      <span style={{ width: 6, height: 6, borderRadius: "50%", background: "#10a37f" }} />
                      <span style={{ fontSize: 9.5, color: "#10a37f", fontFamily: "var(--font-mono)", fontWeight: 600 }}>
                        gri_db ONLINE
                      </span>
                    </div>
                  </div>
                </div>

                {/* Send Button — Touch ergonomic on mobile */}
                <motion.button
                  type="submit"
                  disabled={!inputText.trim() || loading}
                  whileHover={{ scale: inputText.trim() && !loading ? 1.05 : 1 }}
                  whileTap={{ scale: 0.95 }}
                  style={{
                    width: isMobile ? 40 : 44, height: isMobile ? 40 : 44, borderRadius: "50%",
                    border: "none", cursor: !inputText.trim() || loading ? "default" : "pointer",
                    background: !inputText.trim() || loading
                      ? "#383838"
                      : "#FFFFFF",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    flexShrink: 0, alignSelf: "flex-end",
                    boxShadow: !inputText.trim() || loading
                      ? "none"
                      : "0 2px 8px rgba(0, 0, 0, 0.35)",
                    transition: "all 0.2s ease"
                  }}
                >
                  {loading
                    ? <Loader2 size={16} style={{ color: "#8E8E8E", animation: "spin 1s linear infinite" }} />
                    : <Send size={isMobile ? 15 : 17} color={!inputText.trim() ? "#676767" : "#000000"} />
                  }
                </motion.button>
              </form>

              <div style={{
                textAlign: "center", marginTop: 8,
                fontSize: 10.5, color: "#8E8E8E",
                fontFamily: "var(--font-body)"
              }}>
                BH SCADA AI can make mistakes. Check important info.
              </div>
            </div>
          </>
        ) : (
          /* ── Portal View EMBEDDED Directly Inside ChatWindow ── */
          <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden", position: "relative", background: "#212121" }}>
            {/* Sub-bar with title & Back to Chat button */}
            <div style={{
              padding: "10px 24px",
              background: "#171717",
              borderBottom: "1px solid rgba(255, 255, 255, 0.1)",
              display: "flex", alignItems: "center", justifyContent: "space-between", flexShrink: 0
            }}>
              <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                <span style={{ fontSize: 18 }}>
                  {activePortal === "email_scheduler" ? "📄" : activePortal === "audit_logs" ? "🛡️" : "🗺️"}
                </span>
                <div>
                  <span style={{ fontSize: 13, fontWeight: 600, color: "#ececec" }}>
                    {activePortal === "email_scheduler"
                      ? "Custom Reports & Email Dispatcher"
                      : activePortal === "audit_logs"
                      ? "Database Security & Audit Trail"
                      : "Interactive Machine Floor Map"}
                  </span>
                  <span style={{ fontSize: 11, color: "#8e8e8e", marginLeft: 8 }}>
                    • gri_db Telemetry
                  </span>
                </div>
              </div>
              <button
                onClick={() => setActivePortal("chat")}
                style={{
                  display: "flex", alignItems: "center", gap: 6,
                  padding: "6px 14px", borderRadius: 8,
                  background: "#2f2f2f",
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  color: "#ececec", fontSize: 12, fontWeight: 500,
                  cursor: "pointer",
                  transition: "background 0.15s ease"
                }}
                onMouseEnter={e => { e.currentTarget.style.background = "#383838"; }}
                onMouseLeave={e => { e.currentTarget.style.background = "#2f2f2f"; }}
              >
                <Bot size={14} color="#10a37f" />
                <span>← Return to AI Chat</span>
              </button>
            </div>


            {/* Scrollable Portal View */}
            <div style={{ flex: 1, overflowY: "auto", padding: (activePortal === "email_scheduler" || activePortal === "audit_logs") ? "0" : "20px 24px" }}>
              {activePortal === "email_scheduler" && <EmailSchedulerPanel />}
              {activePortal === "machines" && <FactoryMap />}
              {activePortal === "audit_logs" && <AuditLogPanel />}
            </div>

            {/* In-Portal Quick AI Query Dock */}
            <div style={{
              padding: "10px 24px",
              background: "rgba(10, 16, 30, 0.95)",
              borderTop: "1px solid rgba(59, 130, 246, 0.15)",
              display: "flex", alignItems: "center", gap: 12, flexShrink: 0
            }}>
              <Sparkles size={15} color="#3B82F6" />
              <input
                type="text"
                placeholder={`Ask AI about this ${activePortal === "email_scheduler" ? "report generator or email schedule" : "factory machine map"}... (Press Enter to ask AI)`}
                onKeyDown={(e) => {
                  if (e.key === "Enter" && e.currentTarget.value.trim()) {
                    const q = e.currentTarget.value.trim();
                    e.currentTarget.value = "";
                    setActivePortal("chat");
                    handleSend(q);
                  }
                }}
                style={{
                  flex: 1, background: "rgba(255,255,255,0.05)",
                  border: "1px solid rgba(148, 163, 184, 0.15)",
                  borderRadius: 8, padding: "8px 14px", color: "#E2E8F0",
                  fontSize: 12.5, outline: "none"
                }}
              />
              <span style={{ fontSize: 10, color: "#64748B", fontFamily: "var(--font-mono)", whiteSpace: "nowrap" }}>
                Auto-switches to AI Chatbot
              </span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
