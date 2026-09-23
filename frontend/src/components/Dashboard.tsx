import { useState, useEffect, useCallback } from "react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Home, Building2, Users, FolderOpen, Package, Factory,
  Cog, ClipboardList, Map, Settings, Bell, Search,
  ChevronRight, TrendingUp, Activity, LogOut, Bot,
  BarChart3, Calendar, AlertTriangle, CheckCircle2,
  MapPin, Wrench, Gauge, DollarSign, Mail, Plus, Trash2,
  Send, RefreshCw, X, Clock, CalendarDays, ArrowLeft, Download, FileText
} from "lucide-react";
import {
  AreaChart, Area, BarChart, Bar, LineChart, Line,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell,
} from "recharts";
import FactoryMap from "./FactoryMap";
import AIChat from "./AIChat";
import LocationView from "./LocationView";
import { getAuthUser, API_BASE } from "../services/api";

interface Props {
  role?: string;
  onLogout?: () => void;
  initialNav?: string;
  onBack?: () => void;
}

const NAV_ITEMS = [
  { icon: Home, label: "Home", id: "home" },
  { icon: Building2, label: "Company Structure", id: "structure" },
  { icon: Users, label: "Departments", id: "departments" },
  { icon: Users, label: "Employees", id: "employees" },
  { icon: FolderOpen, label: "Projects", id: "projects" },
  { icon: Package, label: "Inventory", id: "inventory" },
  { icon: Factory, label: "Production", id: "production" },
  { icon: Cog, label: "Machines", id: "machines" },
  { icon: Calendar, label: "Attendance", id: "attendance" },
  { icon: DollarSign, label: "Payroll", id: "payroll" },
  { icon: Mail, label: "Email Scheduler", id: "email_scheduler" },
  { icon: ClipboardList, label: "Reports", id: "reports" },
  { icon: MapPin, label: "Locations / Maps", id: "locations" },
  { icon: Bot, label: "AI Assistant", id: "ai" },
  { icon: Settings, label: "Settings", id: "settings" },
];

const STATS = [
  { label: "Total Employees", value: "247", delta: "+12", icon: Users, color: "#3b82f6" },
  { label: "Departments", value: "14", delta: "+1", icon: Building2, color: "#10b981" },
  { label: "Active Projects", value: "38", delta: "+5", icon: FolderOpen, color: "#f59e0b" },
  { label: "Production Units", value: "1,842", delta: "+128", icon: Factory, color: "#8b5cf6" },
  { label: "Machines", value: "63", delta: "+2", icon: Cog, color: "#06b6d4" },
  { label: "Pending Reports", value: "7", delta: "-3", icon: ClipboardList, color: "#ef4444" },
];

const PRODUCTION_DATA = [
  { month: "Apr", units: 1480, target: 1500 },
  { month: "May", units: 1620, target: 1600 },
  { month: "Jun", units: 1540, target: 1650 },
  { month: "Jul", units: 1780, target: 1700 },
  { month: "Aug", units: 1842, target: 1750 },
  { month: "Sep", units: 1920, target: 1800 },
];

const MACHINE_STATUS = [
  { name: "Operational", value: 48, color: "#10b981" },
  { name: "Maintenance", value: 9, color: "#f59e0b" },
  { name: "Offline", value: 6, color: "#ef4444" },
];

const RECENT_EMPLOYEES = [
  { id: "EMP-101", name: "Ravi Shankar", dept: "Production", role: "Senior Operator", status: "Active", attendance: 96 },
  { id: "EMP-114", name: "Priya Nair", dept: "Quality Control", role: "QC Inspector", status: "Active", attendance: 100 },
  { id: "EMP-127", name: "Arjun Mehta", dept: "Maintenance", role: "Technician", status: "On Leave", attendance: 78 },
  { id: "EMP-138", name: "Kavitha Devi", dept: "HR", role: "HR Executive", status: "Active", attendance: 94 },
  { id: "EMP-145", name: "Suresh Babu", dept: "CNC Division", role: "CNC Operator", status: "Active", attendance: 88 },
];

const ACTIVE_PROJECTS = [
  { code: "PRJ-2024-08", name: "Hydraulic Cylinder Series HC-500", dept: "Production", progress: 74, due: "28 Sep 2026" },
  { code: "PRJ-2024-11", name: "Pump Assembly Line Upgrade", dept: "Maintenance", progress: 42, due: "15 Oct 2026" },
  { code: "PRJ-2024-14", name: "CNC Precision Components Batch", dept: "CNC Division", progress: 91, due: "12 Sep 2026" },
  { code: "PRJ-2024-17", name: "Export Order — Gulf Region", dept: "Dispatch", progress: 60, due: "05 Nov 2026" },
];

const ACTIVITY_FEED = [
  { time: "09:42", msg: "Machine HYD-12 maintenance completed", type: "success" },
  { time: "09:15", msg: "EMP-138 submitted attendance report", type: "info" },
  { time: "08:58", msg: "Production target for PRJ-2024-14 achieved", type: "success" },
  { time: "08:30", msg: "Inventory alert: Valve O-rings below threshold", type: "warning" },
  { time: "08:05", msg: "Daily shift briefing recorded", type: "info" },
];

const TOOLTIP_STYLE = {
  backgroundColor: "#0f1a2e",
  border: "1px solid rgba(59,130,246,0.3)",
  borderRadius: 4,
  color: "#e2e8f0",
  fontSize: 12,
  fontFamily: "var(--font-mono)",
};

export default function Dashboard({ role = "Admin", onLogout, initialNav = "home", onBack }: Props) {
  const safeRole = role || "Admin";
  const [activeNav, setActiveNav] = useState(initialNav);
  const [search, setSearch] = useState("");
  const [notifications] = useState(4);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  useEffect(() => {
    if (initialNav) {
      setActiveNav(initialNav);
    }
  }, [initialNav]);

  const renderContent = () => {
    if (activeNav === "ai") return <AIChat />;
    if (activeNav === "locations") return <LocationView />;
    if (activeNav === "machines") return <FactoryMap />;
    if (activeNav === "payroll") return <PayrollPanel />;
    if (activeNav === "email_scheduler") return <EmailSchedulerPanel />;
    return <HomeView setActiveNav={setActiveNav} />;
  };

  return (
    <div style={{ display: "flex", height: "100vh", overflow: "hidden", background: "var(--bg)" }}>
      {/* Sidebar */}
      <motion.aside
        animate={{ width: sidebarCollapsed ? 52 : 220 }}
        transition={{ duration: 0.25, ease: "easeInOut" }}
        style={{
          background: "var(--surface)",
          borderRight: "1px solid var(--border)",
          display: "flex",
          flexDirection: "column",
          overflow: "hidden",
          flexShrink: 0,
          zIndex: 10,
        }}
      >
        {/* Logo */}
        <div
          style={{
            padding: sidebarCollapsed ? "16px 0" : "16px 16px",
            borderBottom: "1px solid var(--border)",
            display: "flex",
            alignItems: "center",
            gap: 10,
            cursor: "pointer",
          }}
          onClick={() => setSidebarCollapsed((c) => !c)}
        >
          <div
            style={{
              width: 32, height: 32,
              background: "#212121",
              border: "1px solid rgba(255, 255, 255, 0.15)",
              borderRadius: 6,
              display: "flex", alignItems: "center", justifyContent: "center",
              flexShrink: 0,
            }}
          >
            <svg width="18" height="14" viewBox="0 0 30 22" fill="none">
              <rect x="0" y="0" width="12" height="22" rx="1.5" fill="#ececec" />
              <rect x="0" y="9" width="12" height="4" rx="1" fill="#8e8e8e" />
              <path d="M16 0 L30 0 Q30 11 22 11 Q30 11 30 22 L16 22 Z" fill="#10a37f" />
            </svg>
          </div>

          <AnimatePresence>
            {!sidebarCollapsed && (
              <motion.div
                initial={{ opacity: 0, x: -8 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -8 }}
                transition={{ duration: 0.15 }}
              >
                <div style={{ fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 14, letterSpacing: "0.1em", color: "var(--text)", lineHeight: 1 }}>
                  BARANI
                </div>
                <div style={{ fontFamily: "var(--font-display)", fontSize: 9, letterSpacing: "0.14em", color: "var(--steel)", marginTop: 1 }}>
                  HYDRAULICS
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* Nav */}
        <nav style={{ flex: 1, padding: "10px 8px", overflowY: "auto", display: "flex", flexDirection: "column", gap: 2 }}>
          {NAV_ITEMS.map(({ icon: Icon, label, id }) => (
            <button
              key={id}
              className={`nav-item ${activeNav === id ? "active" : ""}`}
              onClick={() => setActiveNav(id)}
              title={sidebarCollapsed ? label : undefined}
              style={{ justifyContent: sidebarCollapsed ? "center" : "flex-start", padding: sidebarCollapsed ? "9px 0" : undefined }}
            >
              <Icon size={15} style={{ flexShrink: 0 }} />
              {!sidebarCollapsed && label}
            </button>
          ))}
        </nav>

        {/* Role / logout */}
        <div style={{ padding: "12px 8px", borderTop: "1px solid var(--border)" }}>
          {!sidebarCollapsed && (
            <div
              style={{
                padding: "8px 10px",
                background: "var(--card)",
                border: "1px solid var(--border)",
                borderRadius: 4,
                marginBottom: 6,
              }}
            >
              <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, letterSpacing: "0.14em", color: "var(--text-dim)", textTransform: "uppercase" }}>Role</div>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--accent)", marginTop: 2 }}>{safeRole}</div>
            </div>
          )}
          <button className="nav-item" onClick={onLogout} style={{ width: "100%", justifyContent: sidebarCollapsed ? "center" : "flex-start" }}>
            <LogOut size={14} />
            {!sidebarCollapsed && "Sign Out"}
          </button>
        </div>
      </motion.aside>

      {/* Main */}
      <div style={{ flex: 1, display: "flex", flexDirection: "column", overflow: "hidden" }}>
        {/* Top bar */}
        <header
          style={{
            height: 52,
            background: "var(--surface)",
            borderBottom: "1px solid var(--border)",
            display: "flex",
            alignItems: "center",
            padding: "0 20px",
            gap: 12,
            flexShrink: 0,
          }}
        >
          {onBack && (
            <button
              onClick={onBack}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 8,
                padding: "7px 16px",
                borderRadius: 8,
                background: "#212121",
                border: "1px solid rgba(255, 255, 255, 0.15)",
                color: "#ececec",
                fontWeight: 600,
                fontSize: 12,
                cursor: "pointer",
                flexShrink: 0,
                letterSpacing: "0.02em",
                transition: "background 0.15s ease, border-color 0.15s ease"
              }}
              title="Return to AI Chatbot interface"
            >
              <ArrowLeft size={15} color="#ececec" />
              ← Back to AI Chat
            </button>
          )}

          <div style={{ flex: 1, position: "relative", maxWidth: 440 }}>
            <Search size={13} style={{ position: "absolute", left: 10, top: "50%", transform: "translateY(-50%)", color: "var(--text-dim)" }} />
            <input
              className="field"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search employees, machines, projects…"
              style={{ width: "100%", paddingLeft: 30, paddingRight: 12, paddingTop: 7, paddingBottom: 7, fontSize: 12 }}
            />
          </div>

          <div style={{ flex: 1 }} />

          {/* Status */}
          <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
            <span className="status-dot green" />
            <span style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--success)", letterSpacing: "0.1em" }}>LIVE</span>
          </div>

          {/* Notifications */}
          <button
            style={{
              position: "relative", background: "none", border: "none", cursor: "pointer",
              color: "var(--text-muted)", padding: 6, borderRadius: 4,
              display: "flex", alignItems: "center",
            }}
          >
            <Bell size={16} />
            {notifications > 0 && (
              <span
                style={{
                  position: "absolute", top: 2, right: 2, width: 14, height: 14,
                  background: "#ef4444", borderRadius: "50%",
                  fontSize: 8, fontWeight: 700, color: "#fff",
                  display: "flex", alignItems: "center", justifyContent: "center",
                }}
              >
                {notifications}
              </span>
            )}
          </button>

          {/* Profile */}
          <div
            style={{
              display: "flex", alignItems: "center", gap: 8, cursor: "pointer",
              padding: "4px 8px", borderRadius: 4, border: "1px solid var(--border)",
              background: "var(--card)",
            }}
          >
            <div
              style={{
                width: 26, height: 26, borderRadius: "50%",
                background: "linear-gradient(135deg, #2563eb 0%, #3b82f6 100%)",
                display: "flex", alignItems: "center", justifyContent: "center",
                fontSize: 10, fontWeight: 700, color: "#fff",
              }}
            >
              {safeRole[0]}
            </div>
            <div>
              <div style={{ fontSize: 11, fontWeight: 600, color: "var(--text)", lineHeight: 1 }}>{safeRole === "Admin" ? "Rajesh Kumar" : safeRole === "HR" ? "Priya Sharma" : "Ravi Shankar"}</div>
              <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.1em" }}>{safeRole.toUpperCase()}</div>
            </div>
            <ChevronRight size={11} style={{ color: "var(--text-dim)" }} />
          </div>
        </header>

        {/* Content */}
        <main style={{ flex: 1, overflow: "auto", padding: activeNav === "ai" || activeNav === "locations" || activeNav === "machines" ? 0 : "20px" }}>
          <AnimatePresence mode="wait">
            <motion.div
              key={activeNav}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.22 }}
              style={{ height: "100%" }}
            >
              {renderContent()}
            </motion.div>
          </AnimatePresence>
        </main>
      </div>
    </div>
  );
}

// ── Home view ──────────────────────────────────────────────────────────────

export function HomeView({ setActiveNav }: { setActiveNav: (id: string) => void }) {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 20, paddingBottom: 32 }}>
      {/* Welcome */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <div>
          <h1
            style={{
              fontFamily: "var(--font-display)",
              fontWeight: 800, fontSize: 28, letterSpacing: "0.06em", color: "var(--text)", lineHeight: 1,
            }}
          >
            OPERATIONS OVERVIEW
          </h1>
          <p style={{ fontSize: 13, color: "var(--text-muted)", marginTop: 4 }}>
            Thursday, 11 September 2026 &nbsp;·&nbsp; Morning Shift Active
          </p>
        </div>
        <div style={{ display: "flex", gap: 8 }}>
          <button
            onClick={() => setActiveNav("ai")}
            style={{
              display: "flex", alignItems: "center", gap: 6, padding: "7px 14px",
              background: "rgba(37,99,235,0.15)", border: "1px solid rgba(59,130,246,0.35)",
              borderRadius: 4, color: "var(--accent)", cursor: "pointer",
              fontSize: 12, fontWeight: 500,
            }}
          >
            <Bot size={13} /> AI Assistant
          </button>
          <button
            onClick={() => setActiveNav("machines")}
            style={{
              display: "flex", alignItems: "center", gap: 6, padding: "7px 14px",
              background: "var(--card)", border: "1px solid var(--border)",
              borderRadius: 4, color: "var(--text-muted)", cursor: "pointer",
              fontSize: 12, fontWeight: 500,
            }}
          >
            <Map size={13} /> Factory Map
          </button>
        </div>
      </div>

      {/* Stats grid */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(170px, 1fr))", gap: 12 }}>
        {STATS.map((stat) => (
          <motion.div
            key={stat.label}
            className="stat-card"
            whileHover={{ borderColor: "rgba(59,130,246,0.5)" }}
          >
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
              <div>
                <div
                  style={{
                    fontFamily: "var(--font-mono)", fontSize: 9, letterSpacing: "0.12em",
                    color: "var(--text-dim)", textTransform: "uppercase", marginBottom: 8,
                  }}
                >
                  {stat.label}
                </div>
                <div
                  style={{
                    fontFamily: "var(--font-display)", fontWeight: 800,
                    fontSize: 32, color: "var(--text)", lineHeight: 1,
                  }}
                >
                  {stat.value}
                </div>
                <div
                  style={{
                    fontSize: 11, marginTop: 4,
                    color: stat.delta.startsWith("+") ? "var(--success)" : stat.delta === "0" ? "var(--text-dim)" : "#f87171",
                  }}
                >
                  {stat.delta} this month
                </div>
              </div>
              <div
                style={{
                  width: 34, height: 34, borderRadius: 4,
                  background: `${stat.color}18`,
                  border: `1px solid ${stat.color}30`,
                  display: "flex", alignItems: "center", justifyContent: "center",
                }}
              >
                <stat.icon size={16} style={{ color: stat.color }} />
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Charts row */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 300px", gap: 14 }}>
        {/* Production chart */}
        <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 6, padding: 18 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
            <div>
              <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, fontSize: 14, letterSpacing: "0.06em", color: "var(--text)" }}>
                PRODUCTION OUTPUT
              </div>
              <div style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)", marginTop: 2 }}>Units / month</div>
            </div>
            <TrendingUp size={14} style={{ color: "var(--success)" }} />
          </div>
          <ResponsiveContainer width="100%" height={160}>
            <AreaChart data={PRODUCTION_DATA}>
              <defs>
                <linearGradient id="prodGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#2563eb" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#2563eb" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="rgba(59,130,246,0.08)" />
              <XAxis dataKey="month" tick={{ fill: "#4a6080", fontSize: 10, fontFamily: "var(--font-mono)" }} axisLine={false} tickLine={false} />
              <YAxis tick={{ fill: "#4a6080", fontSize: 10, fontFamily: "var(--font-mono)" }} axisLine={false} tickLine={false} />
              <Tooltip contentStyle={TOOLTIP_STYLE} />
              <Area type="monotone" dataKey="target" stroke="#1d4ed8" strokeDasharray="4 4" strokeWidth={1.5} fill="none" dot={false} />
              <Area type="monotone" dataKey="units" stroke="#3b82f6" strokeWidth={2} fill="url(#prodGrad)" dot={{ fill: "#3b82f6", r: 3 }} />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Machine status */}
        <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 6, padding: 18 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
            <div>
              <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, fontSize: 14, letterSpacing: "0.06em", color: "var(--text)" }}>
                MACHINE STATUS
              </div>
              <div style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)", marginTop: 2 }}>63 units total</div>
            </div>
            <Gauge size={14} style={{ color: "var(--accent)" }} />
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 20 }}>
            <ResponsiveContainer width={130} height={130}>
              <PieChart>
                <Pie data={MACHINE_STATUS} dataKey="value" cx="50%" cy="50%" innerRadius={36} outerRadius={56} paddingAngle={3}>
                  {MACHINE_STATUS.map((entry, i) => (
                    <Cell key={i} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip contentStyle={TOOLTIP_STYLE} />
              </PieChart>
            </ResponsiveContainer>
            <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: 10 }}>
              {MACHINE_STATUS.map((s) => (
                <div key={s.name}>
                  <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                    <span style={{ fontSize: 11, color: "var(--text-muted)" }}>{s.name}</span>
                    <span style={{ fontFamily: "var(--font-mono)", fontSize: 11, color: s.color }}>{s.value}</span>
                  </div>
                  <div className="progress-bar">
                    <div className="progress-bar-fill" style={{ width: `${(s.value / 63) * 100}%`, background: s.color }} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Activity feed */}
        <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 6, padding: 18 }}>
          <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, fontSize: 14, letterSpacing: "0.06em", color: "var(--text)", marginBottom: 14 }}>
            ACTIVITY FEED
          </div>
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {ACTIVITY_FEED.map((item, i) => (
              <div key={i} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                <span
                  style={{
                    fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)",
                    letterSpacing: "0.04em", paddingTop: 2, flexShrink: 0,
                  }}
                >
                  {item.time}
                </span>
                <div style={{ display: "flex", gap: 7, alignItems: "flex-start" }}>
                  {item.type === "success" && <CheckCircle2 size={11} style={{ color: "var(--success)", marginTop: 1, flexShrink: 0 }} />}
                  {item.type === "warning" && <AlertTriangle size={11} style={{ color: "var(--warning)", marginTop: 1, flexShrink: 0 }} />}
                  {item.type === "info" && <Activity size={11} style={{ color: "var(--accent)", marginTop: 1, flexShrink: 0 }} />}
                  <span style={{ fontSize: 11, color: "var(--text-muted)", lineHeight: 1.4 }}>{item.msg}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Bottom row */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
        {/* Employees */}
        <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 6, padding: 18 }}>
          <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, fontSize: 14, letterSpacing: "0.06em", color: "var(--text)", marginBottom: 14 }}>
            RECENT EMPLOYEES
          </div>
          <table className="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Status</th>
                <th>Attendance</th>
              </tr>
            </thead>
            <tbody>
              {RECENT_EMPLOYEES.map((e) => (
                <tr key={e.id}>
                  <td style={{ fontFamily: "var(--font-mono)", color: "var(--accent)" }}>{e.id}</td>
                  <td style={{ color: "var(--text)", fontWeight: 500 }}>{e.name}</td>
                  <td>{e.dept}</td>
                  <td>
                    <span
                      style={{
                        fontSize: 10, padding: "2px 7px", borderRadius: 2,
                        fontFamily: "var(--font-mono)",
                        background: e.status === "Active" ? "rgba(16,185,129,0.12)" : "rgba(245,158,11,0.12)",
                        color: e.status === "Active" ? "#34d399" : "#fbbf24",
                        border: `1px solid ${e.status === "Active" ? "rgba(16,185,129,0.25)" : "rgba(245,158,11,0.25)"}`,
                      }}
                    >
                      {e.status}
                    </span>
                  </td>
                  <td>
                    <div style={{ display: "flex", alignItems: "center", gap: 7 }}>
                      <div className="progress-bar" style={{ width: 50 }}>
                        <div className="progress-bar-fill" style={{ width: `${e.attendance}%` }} />
                      </div>
                      <span style={{ fontFamily: "var(--font-mono)", fontSize: 10, color: "var(--text-muted)" }}>{e.attendance}%</span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Projects */}
        <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 6, padding: 18 }}>
          <div style={{ fontFamily: "var(--font-display)", fontWeight: 700, fontSize: 14, letterSpacing: "0.06em", color: "var(--text)", marginBottom: 14 }}>
            ACTIVE PROJECTS
          </div>
          <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
            {ACTIVE_PROJECTS.map((p) => (
              <div
                key={p.code}
                style={{ padding: "10px 12px", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 4 }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 6 }}>
                  <div>
                    <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text)" }}>{p.name}</div>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)", marginTop: 2 }}>
                      {p.code} &nbsp;·&nbsp; {p.dept}
                    </div>
                  </div>
                  <span
                    style={{
                      fontFamily: "var(--font-mono)", fontSize: 11, fontWeight: 600,
                      color: p.progress >= 80 ? "var(--success)" : p.progress >= 50 ? "var(--accent)" : "var(--warning)",
                    }}
                  >
                    {p.progress}%
                  </span>
                </div>
                <div className="progress-bar">
                  <div
                    className="progress-bar-fill"
                    style={{
                      width: `${p.progress}%`,
                      background: p.progress >= 80
                        ? "linear-gradient(90deg, #059669, #10b981)"
                        : "linear-gradient(90deg, var(--primary), var(--accent))",
                    }}
                  />
                </div>
                <div style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)", marginTop: 5 }}>
                  Due: {p.due}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── PayrollPanel ─────────────────────────────────────────────────────────────

export function PayrollPanel() {
  const API = API_BASE;
  const user = getAuthUser();
  const token = user?.token || localStorage.getItem("api_token") || localStorage.getItem("auth_token") || "";

  const now = new Date();
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [year, setYear] = useState(now.getFullYear());
  const [records, setRecords] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>({});
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [msg, setMsg] = useState("");
  const [sendEmails, setSendEmails] = useState("");
  const [payrollFormat, setPayrollFormat] = useState<"pdf" | "docx">("pdf");
  const [sending, setSending] = useState(false);

  const MONTHS = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

  const fetchPayroll = useCallback(async () => {
    setLoading(true);
    try {
      const r = await fetch(`${API}/payroll.php?month=${month}&year=${year}`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const d = await r.json();
      if (d.success) { setRecords(d.data.records || []); setSummary(d.data.summary || {}); }
    } catch { } finally { setLoading(false); }
  }, [month, year, API, token]);

  useEffect(() => { fetchPayroll(); }, [fetchPayroll]);

  const generatePayroll = async () => {
    setGenerating(true); setMsg("");
    try {
      const r = await fetch(`${API}/payroll.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "generate", month, year }),
      });
      const d = await r.json();
      setMsg(d.data?.message || d.error || "Done");
      if (d.success) fetchPayroll();
    } catch { setMsg("Error generating payroll"); } finally { setGenerating(false); }
  };

  const sendReport = async () => {
    setSending(true); setMsg("");
    const emailList = sendEmails.split(",").map(e => e.trim()).filter(Boolean);
    if (!emailList.length) { setMsg("Enter at least one email address"); setSending(false); return; }
    try {
      const r = await fetch(`${API}/payroll.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "send_report", month, year, recipients: emailList, format: payrollFormat }),
      });
      const d = await r.json();
      const attachInfo = d.data?.attachment_name ? ` (Attached: ${d.data.attachment_name})` : "";
      setMsg((d.data?.message || d.error || "Sent!") + attachInfo);
    } catch { setMsg("Error sending report"); } finally { setSending(false); }
  };

  const panelStyle: React.CSSProperties = {
    padding: 28, height: "100%", overflowY: "auto",
    background: "var(--bg)", color: "var(--text)",
  };
  const cardStyle: React.CSSProperties = {
    background: "var(--surface)", border: "1px solid var(--border)",
    borderRadius: 10, padding: 20, marginBottom: 20,
  };

  const fmt = (n: any) => "₹" + Number(n || 0).toLocaleString("en-IN", { minimumFractionDigits: 2 });

  return (
    <div style={panelStyle}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 24 }}>
        <DollarSign size={26} color="var(--accent)" />
        <div>
          <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700 }}>Payroll Management</h1>
          <p style={{ margin: 0, color: "var(--text-dim)", fontSize: 13 }}>Generate, review & email payroll reports to officials</p>
        </div>
      </div>

      {/* Controls */}
      <div style={{ ...cardStyle, display: "flex", gap: 16, alignItems: "flex-end", flexWrap: "wrap" }}>
        <div>
          <div style={{ fontSize: 11, color: "var(--text-dim)", marginBottom: 5 }}>MONTH</div>
          <select
            value={month}
            onChange={e => setMonth(+e.target.value)}
            style={{ background: "var(--card)", color: "var(--text)", border: "1px solid var(--border)", borderRadius: 6, padding: "8px 14px", fontSize: 13 }}
          >
            {MONTHS.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
          </select>
        </div>
        <div>
          <div style={{ fontSize: 11, color: "var(--text-dim)", marginBottom: 5 }}>YEAR</div>
          <select
            value={year}
            onChange={e => setYear(+e.target.value)}
            style={{ background: "var(--card)", color: "var(--text)", border: "1px solid var(--border)", borderRadius: 6, padding: "8px 14px", fontSize: 13 }}
          >
            {[2024, 2025, 2026, 2027].map(y => <option key={y}>{y}</option>)}
          </select>
        </div>
        <button
          onClick={fetchPayroll}
          style={{ background: "var(--card)", border: "1px solid var(--border)", color: "var(--text)", borderRadius: 6, padding: "9px 16px", cursor: "pointer", display: "flex", alignItems: "center", gap: 6, fontSize: 13 }}
        >
          <RefreshCw size={14} /> Refresh
        </button>
        <button
          onClick={generatePayroll}
          disabled={generating}
          style={{ background: "linear-gradient(135deg, #2563eb, #1d4ed8)", color: "#fff", border: "none", borderRadius: 6, padding: "9px 20px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 6 }}
        >
          {generating ? <><RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> Generating…</> : <><Plus size={14} /> Generate Payroll</>}
        </button>
      </div>

      {/* Summary Cards */}
      {summary.total_employees > 0 && (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(150px, 1fr))", gap: 12, marginBottom: 20 }}>
          {[
            { label: "Employees", val: summary.total_employees, color: "#3b82f6" },
            { label: "Total Basic", val: fmt(summary.total_basic), color: "#8b5cf6" },
            { label: "Allowances", val: fmt(summary.total_allowances), color: "#10b981" },
            { label: "Deductions", val: fmt(summary.total_deductions), color: "#ef4444" },
            { label: "Net Payable", val: fmt(summary.total_net_salary), color: "#f59e0b" },
            { label: "Paid", val: `${summary.paid_count || 0}/${summary.total_employees}`, color: "#06b6d4" },
          ].map(s => (
            <div key={s.label} style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 8, padding: "14px 16px", borderTop: `3px solid ${s.color}` }}>
              <div style={{ fontSize: 18, fontWeight: 700, color: s.color }}>{s.val}</div>
              <div style={{ fontSize: 10, color: "var(--text-dim)", textTransform: "uppercase", letterSpacing: "0.1em", marginTop: 3 }}>{s.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Payroll Table */}
      <div style={cardStyle}>
        <h3 style={{ margin: "0 0 14px", fontSize: 14, fontWeight: 600 }}>
          {MONTHS[month - 1]} {year} — Payroll Register ({records.length} employees)
        </h3>
        {loading ? (
          <div style={{ textAlign: "center", padding: 40, color: "var(--text-dim)" }}>
            <RefreshCw size={24} style={{ animation: "spin 1s linear infinite" }} />
            <div style={{ marginTop: 10 }}>Loading payroll data…</div>
          </div>
        ) : records.length === 0 ? (
          <div style={{ textAlign: "center", padding: 40, color: "var(--text-dim)" }}>
            <DollarSign size={40} style={{ opacity: 0.3 }} />
            <div style={{ marginTop: 10 }}>No payroll records found. Click "Generate Payroll" to create them.</div>
          </div>
        ) : (
          <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
              <thead>
                <tr style={{ background: "var(--card)" }}>
                  {["Emp Code", "Name", "Department", "Designation", "Basic", "Allowances", "Deductions", "Net Salary", "Status"].map(h => (
                    <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: "var(--text-dim)", fontSize: 10, textTransform: "uppercase", letterSpacing: "0.08em", borderBottom: "1px solid var(--border)", whiteSpace: "nowrap" }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {records.map((r, i) => (
                  <tr key={r.id} style={{ background: i % 2 === 0 ? "transparent" : "rgba(255,255,255,0.02)", borderBottom: "1px solid var(--border)" }}>
                    <td style={{ padding: "9px 12px", fontFamily: "var(--font-mono)", fontSize: 11 }}>{r.employee_code}</td>
                    <td style={{ padding: "9px 12px", fontWeight: 600 }}>{r.first_name} {r.last_name}</td>
                    <td style={{ padding: "9px 12px", color: "var(--text-dim)" }}>{r.department_name}</td>
                    <td style={{ padding: "9px 12px", color: "var(--text-dim)" }}>{r.designation}</td>
                    <td style={{ padding: "9px 12px", textAlign: "right" }}>{fmt(r.basic_salary)}</td>
                    <td style={{ padding: "9px 12px", textAlign: "right", color: "#10b981" }}>{fmt(r.allowances)}</td>
                    <td style={{ padding: "9px 12px", textAlign: "right", color: "#ef4444" }}>{fmt(r.deductions)}</td>
                    <td style={{ padding: "9px 12px", textAlign: "right", fontWeight: 700, color: "#f59e0b" }}>{fmt(r.net_salary)}</td>
                    <td style={{ padding: "9px 12px" }}>
                      <span style={{ padding: "3px 8px", borderRadius: 20, fontSize: 10, fontWeight: 700, background: r.payment_status === "paid" ? "#d1fae5" : "#fef3c7", color: r.payment_status === "paid" ? "#065f46" : "#92400e" }}>
                        {r.payment_status?.toUpperCase()}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Send Report */}
      <div style={cardStyle}>
        <h3 style={{ margin: "0 0 14px", fontSize: 14, fontWeight: 600, display: "flex", alignItems: "center", gap: 8 }}>
          <Mail size={16} color="var(--accent)" /> Send Payroll Report to Higher Official
        </h3>
        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "flex-end" }}>
          <div style={{ flex: 1, minWidth: 240 }}>
            <div style={{ fontSize: 11, color: "var(--text-dim)", marginBottom: 5 }}>RECIPIENT EMAIL(S) — separate with commas</div>
            <input
              value={sendEmails}
              onChange={e => setSendEmails(e.target.value)}
              placeholder="gm@bhipl.com, ceo@bhipl.com, finance@bhipl.com"
              style={{ width: "100%", background: "var(--card)", color: "var(--text)", border: "1px solid var(--border)", borderRadius: 6, padding: "9px 14px", fontSize: 13, boxSizing: "border-box" }}
            />
          </div>
          <div style={{ width: 140 }}>
            <div style={{ fontSize: 11, color: "var(--text-dim)", marginBottom: 5 }}>ATTACHMENT</div>
            <select
              value={payrollFormat}
              onChange={e => setPayrollFormat(e.target.value as "pdf" | "docx")}
              style={{ width: "100%", background: "var(--card)", color: "var(--text)", border: "1px solid var(--border)", borderRadius: 6, padding: "9px 10px", fontSize: 13 }}
            >
              <option value="pdf">📄 PDF Report</option>
              <option value="docx">📝 Word (.docx)</option>
            </select>
          </div>
          <button
            onClick={sendReport}
            disabled={sending || records.length === 0}
            style={{ background: "linear-gradient(135deg, #059669, #047857)", color: "#fff", border: "none", borderRadius: 6, padding: "9px 24px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 8, whiteSpace: "nowrap" }}
          >
            {sending ? <><RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> Sending…</> : <><Send size={14} /> Send Report</>}
          </button>
        </div>
        {msg && (
          <div style={{ marginTop: 12, padding: "10px 14px", background: msg.toLowerCase().includes("error") ? "rgba(239,68,68,0.1)" : "rgba(16,185,129,0.1)", border: `1px solid ${msg.toLowerCase().includes("error") ? "rgba(239,68,68,0.3)" : "rgba(16,185,129,0.3)"}`, borderRadius: 6, fontSize: 13, color: msg.toLowerCase().includes("error") ? "#ef4444" : "#10b981" }}>
            {msg}
          </div>
        )}
      </div>
    </div>
  );
}

// ─── EmailSchedulerPanel ──────────────────────────────────────────────────────

export function EmailSchedulerPanel() {
  const API = API_BASE;
  const user = getAuthUser();
  const token = user?.token || localStorage.getItem("api_token") || localStorage.getItem("auth_token") || "";

  const [jobs, setJobs] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [scheduleMode, setScheduleMode] = useState<"everyday" | "specific_dates">("everyday");
  const [form, setForm] = useState({
    report_type: "payroll",
    report_format: "pdf",
    send_time: "08:00",
    subject: "",
    message_body: "",
  });
  const [emailInputs, setEmailInputs] = useState<string[]>(["baranihydraluics@gmail.com"]);
  const [dateInputs, setDateInputs] = useState<string[]>([""]);
  const [formMsg, setFormMsg] = useState("");
  const [saving, setSaving] = useState(false);

  // ── Report Generation State ──────────────────────────────────────────────
  const [rptType, setRptType] = useState("payroll");
  const [rptFormat, setRptFormat] = useState<"pdf" | "docx">("pdf");
  const [rptEmails, setRptEmails] = useState("baranihydraluics@gmail.com");
  const [rptNote, setRptNote] = useState("");
  const [rptCustomSubject, setRptCustomSubject] = useState("");
  const [rptGenerating, setRptGenerating] = useState(false);
  const [rptPreview, setRptPreview] = useState<string>("");
  const [rptDownloadUrl, setRptDownloadUrl] = useState<string | null>(null);
  const [rptFileName, setRptFileName] = useState<string | null>(null);
  const [rptSending, setRptSending] = useState(false);
  const [rptMsg, setRptMsg] = useState("");

  // ── Scheduler Check State ────────────────────────────────────────────────
  const [checkingSched, setCheckingSched] = useState(false);
  const [schedCheckResult, setSchedCheckResult] = useState<string>("");

  // ── Outbox, Admin Mailbox & Live Temp Mailbox Vault State ─────────────────
  const [outbox, setOutbox] = useState<any[]>([]);
  const [adminMailbox, setAdminMailbox] = useState<any[]>([]);
  const [tempAccount, setTempAccount] = useState<any>(null);
  const [tempInbox, setTempInbox] = useState<any[]>([]);
  const [mailVaultTab, setMailVaultTab] = useState<"temp_mailbox" | "admin_mailbox" | "outbox">("outbox");
  const [loadingOutbox, setLoadingOutbox] = useState(false);
  const [loadingTemp, setLoadingTemp] = useState(false);
  const [tempSending, setTempSending] = useState(false);
  const [tempMsgAlert, setTempMsgAlert] = useState("");
  const [copiedEmail, setCopiedEmail] = useState(false);

  const REPORT_LABELS: Record<string, string> = {
    heat_calculation: "Heat Calculation of Hydraulic Press",
    payroll: "Amended True-Up Payroll Report",
    production: "Production Output Report",
    alarm: "Alarm & Fault Analysis Report",
    downtime: "Machine Downtime Report",
    maintenance: "Maintenance Activity Report",
    work_orders: "Work Orders Status Report",
  };

  const REPORT_TYPES = [
    { value: "heat_calculation", label: "🔥 Heat Calculation of Hydraulic Press" },
    { value: "payroll", label: "📄 Amended True-Up Payroll Report" },
    { value: "production", label: "🏭 Production Report" },
    { value: "alarm", label: "🚨 Alarm Report" },
    { value: "downtime", label: "⏱️ Downtime Report" },
    { value: "maintenance", label: "🔧 Maintenance Report" },
    { value: "work_orders", label: "📋 Work Orders Report" },
  ];

  const fetchTempAccount = useCallback(async () => {
    try {
      const r = await fetch(`${API}/temp_mail.php`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const d = await r.json();
      if (d.success) {
        const acct = d.data?.account || d.account;
        if (acct) setTempAccount(acct);
      }
    } catch {}
  }, [API, token]);

  const fetchTempInbox = useCallback(async () => {
    setLoadingTemp(true);
    try {
      const r = await fetch(`${API}/temp_mail.php?action=inbox`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const d = await r.json();
      if (d.success) {
        const msgs = d.data?.inbox || d.inbox || [];
        setTempInbox(msgs);
      }
    } catch {} finally { setLoadingTemp(false); }
  }, [API, token]);

  const generateNewTempAccount = async () => {
    setLoadingTemp(true);
    try {
      const r = await fetch(`${API}/temp_mail.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "new_account" }),
      });
      const d = await r.json();
      if (d.success) {
        const acct = d.data?.account || d.account;
        setTempAccount(acct);
        setTempInbox([]);
        setTempMsgAlert(`🎲 Fresh address created: ${acct.email}`);
      }
    } catch {
      setTempMsgAlert("Failed to generate new address.");
    } finally {
      setLoadingTemp(false);
    }
  };

  const sendTestToTempMail = async (format: "docx" | "pdf" = "docx") => {
    setTempSending(true);
    setTempMsgAlert("");
    try {
      const r = await fetch(`${API}/temp_mail.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "send_test", report_type: rptType, format }),
      });
      const d = await r.json();
      if (d.success) {
        setTempMsgAlert(`✅ Report sent and received in Temp Mailbox (${format.toUpperCase()})!`);
        fetchTempInbox();
        fetchOutbox();
      } else {
        setTempMsgAlert(`⚠️ ${d.message || "Failed to send"}`);
      }
    } catch {
      setTempMsgAlert(`⚠️ Error sending test report to temp mail`);
    } finally {
      setTempSending(false);
    }
  };

  const copyTempEmailToClipboard = () => {
    if (tempAccount?.email) {
      navigator.clipboard.writeText(tempAccount.email);
      setCopiedEmail(true);
      setTimeout(() => setCopiedEmail(false), 2000);
    }
  };

  const fetchOutbox = useCallback(async () => {
    setLoadingOutbox(true);
    try {
      const r = await fetch(`${API}/email.php`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const d = await r.json();
      if (d.success) {
        setOutbox(d.data?.outbox || d.outbox || []);
        setAdminMailbox(d.data?.admin_mailbox || d.admin_mailbox || []);
      }
    } catch {} finally { setLoadingOutbox(false); }
  }, [API, token]);

  const fetchJobs = useCallback(async () => {
    setLoading(true);
    try {
      const r = await fetch(`${API}/scheduled_emails.php`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const d = await r.json();
      if (d.success) setJobs(d.data?.jobs || d.jobs || []);
    } catch {} finally { setLoading(false); }
  }, [API, token]);

  useEffect(() => {
    fetchJobs();
    fetchOutbox();
    fetchTempAccount();
    fetchTempInbox();

    // Auto-poll inbox every 5 seconds for instant reactive delivery
    const interval = setInterval(() => {
      fetchTempInbox();
    }, 5000);

    // Automated background scheduler tick every 20 seconds: auto-dispatches scheduled emails at exact time
    const schedInterval = setInterval(async () => {
      try {
        const res = await fetch(`${API}/run_scheduler.php?format=json`);
        const json = await res.json();
        if (json.fired_count > 0) {
          fetchJobs();
          fetchOutbox();
        }
      } catch {}
    }, 20000);

    return () => {
      clearInterval(interval);
      clearInterval(schedInterval);
    };
  }, [API, fetchJobs, fetchOutbox, fetchTempAccount, fetchTempInbox]);


  const generateFormalReport = async () => {
    setRptGenerating(true); setRptMsg("");
    try {
      const label = REPORT_LABELS[rptType] || rptType;
      let subject = "";
      let report = "";

      if (rptType === "heat_calculation") {
        subject = "Heat Calculation Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the Heat Calculation of Hydraulic Press.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of heat generation during the operation of the hydraulic press.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else if (rptType === "payroll") {
        subject = "Payroll Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the Payroll and Employee Wage Breakdown.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of employee wages, allowances, and statutory deductions during the payroll cycle.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else if (rptType === "downtime") {
        subject = "Machine Downtime Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the Machine Downtime & Incident Tracking.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of equipment availability, stoppage durations, and preventive maintenance actions during the operation of the hydraulic press.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else if (rptType === "alarm") {
        subject = "SCADA Alarm & Fault Analysis Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the SCADA Alarm & Fault Analysis.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of alarm occurrences, safety interlocks, and machine diagnostics during the operation of the hydraulic press.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else if (rptType === "production") {
        subject = "Production Output Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the Production Output & Work Orders Status.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of production volume, machine cycle efficiency, and job orders completed during the operation of the hydraulic press.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else if (rptType === "maintenance") {
        subject = "Maintenance Activity Report — Barani Hydraulics";
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the Maintenance Activity & Preventive Inspection.

Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of equipment service schedules, hydraulic fluid condition, and preventive checks during the operation of the hydraulic press.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      } else {
        subject = `${label} Report — Barani Hydraulics`;
        report = `Dear Sir/Madam,

This message is from the Barani Hydraulics team regarding the ${label} Report.

Please find attached the official report for your review and reference. The document includes the relevant calculations and operational analysis prepared by our team.

Kindly review the attached report and let us know if any further information or assistance is required.

Thank you for your time and consideration.

Regards,
Barani Hydraulics Team
Barani Hydraulics (India) Pvt. Ltd.`;
      }

      setRptCustomSubject(subject);
      setRptPreview(report);
      setRptMsg(`✅ Formal cover letter template ready. The detailed calculations & tables will be included in the attached ${rptFormat.toUpperCase()}!`);
    } catch {
      setRptMsg("Could not fetch data. Check backend connection.");
    } finally {
      setRptGenerating(false);
    }
  };

  // Pre-generate report on first load or when report type changes
  useEffect(() => {
    generateFormalReport();
  }, [rptType]);

  const triggerFileDownload = async (url: string, filename?: string) => {
    try {
      setRptMsg(`⏳ Preparing download for ${filename || "report"}...`);
      const targetUrl = url.startsWith("http") ? url : `${API}${url.startsWith("/api") ? url.replace("/api", "") : url}`;
      const sep = targetUrl.includes("?") ? "&" : "?";
      const authedUrl = `${targetUrl}${sep}token=${encodeURIComponent(token)}`;
      const res = await fetch(authedUrl, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
      const blob = await res.blob();
      const blobUrl = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = blobUrl;
      a.download = filename || (url.includes(".docx") ? "report.docx" : "report.pdf");
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(blobUrl);
      setRptMsg(`✅ Download completed: ${filename || "report"}`);
    } catch {
      // Fallback
      window.open(url, "_blank");
      setRptMsg(`ℹ️ Download launched in browser tab.`);
    }
  };

  const sendFormalReport = async () => {
    if (!rptPreview) { setRptMsg("Generate a report first."); return; }
    const emailList = rptEmails.split(",").map(e => e.trim()).filter(Boolean);
    if (!emailList.length) { setRptMsg("Please enter at least one recipient email address."); return; }

    setRptSending(true); setRptMsg(""); setRptDownloadUrl(null);
    try {
      const subject = rptCustomSubject || (rptType === "heat_calculation"
        ? "Heat Calculation Report — Barani Hydraulics"
        : (rptType === "payroll"
          ? "Payroll Report — Barani Hydraulics"
          : `${REPORT_LABELS[rptType] || rptType} Report — Barani Hydraulics`));

      const r = await fetch(`${API}/email.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          recipients: emailList,
          report_type: rptType,
          format: rptFormat,
          subject: subject,
          report_body: rptPreview,
          notes: rptNote,
        }),
      });
      const d = await r.json();

      if (d.success) {
        const attachName = d.attachment_name || d.data?.attachment_name || d.report?.file_name || d.data?.report?.file_name;
        const dlUrl = d.attachment_url || d.data?.attachment_url || d.report?.download_url || d.data?.report?.download_url;
        if (dlUrl) {
          setRptDownloadUrl(dlUrl);
          setRptFileName(attachName);
        }
        setRptMsg(d.message || d.data?.message || `✅ Report dispatched with attached ${rptFormat.toUpperCase()}!`);
        setMailVaultTab("outbox");
        fetchOutbox();
      } else {
        setRptMsg(`⚠️ ${d.error || d.message || "Failed to dispatch email."}`);
      }
    } catch {
      setRptMsg("Network error while communicating with backend.");
    } finally {
      setRptSending(false);
    }
  };

  const sendToAdminDirectly = async () => {
    if (!rptPreview) { setRptMsg("Generate a report first."); return; }
    setRptSending(true); setRptMsg(""); setRptDownloadUrl(null);
    try {
      const subject = rptCustomSubject || (rptType === "heat_calculation"
        ? "Heat Calculation Report — Barani Hydraulics"
        : (rptType === "payroll"
          ? "Payroll Report — Barani Hydraulics"
          : `${REPORT_LABELS[rptType] || rptType} Report — Barani Hydraulics`));

      const r = await fetch(`${API}/email.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          recipients: ["admin@barani.com"],
          send_to_admin: true,
          report_type: rptType,
          format: rptFormat,
          subject: subject,
          report_body: rptPreview,
          notes: rptNote,
        }),
      });
      const d = await r.json();

      if (d.success) {
        const attachName = d.attachment_name || d.data?.attachment_name || d.report?.file_name || d.data?.report?.file_name;
        const dlUrl = d.attachment_url || d.data?.attachment_url || d.report?.download_url || d.data?.report?.download_url;
        if (dlUrl) {
          setRptDownloadUrl(dlUrl);
          setRptFileName(attachName);
        }
        setRptMsg(d.message || d.data?.message || `✅ Report delivered directly to Admin (admin@barani.com) with attached ${rptFormat.toUpperCase()}!`);
        fetchOutbox();
      } else {
        setRptMsg(`⚠️ ${d.error || d.message || "Failed to send email to admin."}`);
      }
    } catch {
      setRptMsg("Network error while communicating with backend.");
    } finally {
      setRptSending(false);
    }
  };

  const downloadReportDirectly = async () => {
    if (!rptPreview) { setRptMsg("Generate a report first."); return; }
    setRptGenerating(true);
    setRptMsg("⏳ Generating customized report file...");
    try {
      const subject = rptCustomSubject || `Official ${REPORT_LABELS[rptType] || rptType} — Barani Hydraulics`;
      const r = await fetch(`${API}/report.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          report_type: rptType,
          format: rptFormat,
          subject: subject,
          report_body: rptPreview,
          notes: rptNote,
        }),
      });
      const d = await r.json();
      const dlUrl = d.attachment_url || d.download_url || d.data?.attachment_url || d.data?.download_url || d.report?.download_url || d.data?.report?.download_url;
      const fName = d.attachment_name || d.file_name || d.data?.attachment_name || d.data?.file_name || d.report?.file_name || d.data?.report?.file_name;
      if (dlUrl) {
        setRptDownloadUrl(dlUrl);
        setRptFileName(fName);
        await triggerFileDownload(dlUrl, fName || `report.${rptFormat}`);
        fetchOutbox();
      } else {
        setRptMsg("⚠️ Could not generate report file for download.");
      }
    } catch {
      setRptMsg("Failed to generate file for download.");
    } finally {
      setRptGenerating(false);
    }
  };

  const saveJob = async () => {
    setSaving(true); setFormMsg("");
    const emails = emailInputs.filter(Boolean);
    if (!emails.length) { setFormMsg("Add at least one email address"); setSaving(false); return; }

    const dates = scheduleMode === "everyday" ? ["everyday"] : dateInputs.filter(Boolean);
    if (scheduleMode === "specific_dates" && !dates.length) {
      setFormMsg("Add at least one date for specific dates mode");
      setSaving(false);
      return;
    }

    try {
      const r = await fetch(`${API}/scheduled_emails.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          action: "create",
          ...form,
          schedule_mode: scheduleMode,
          subject: form.subject || (form.report_type === 'payroll' ? `Payroll Report — Barani Hydraulics (${form.report_format.toUpperCase()})` : `${REPORT_LABELS[form.report_type] || form.report_type} Report — Barani Hydraulics (${form.report_format.toUpperCase()})`),
          recipient_emails: emails,
          scheduled_dates: dates,
        }),
      });
      const d = await r.json();
      const msg = d.message || d.data?.message || d.error || (d.success ? "✅ Schedule saved!" : "Error");
      setFormMsg(msg);
      if (d.success) {
        fetchJobs();
        if (scheduleMode === "specific_dates") setDateInputs([""]);
      }
    } catch (err: any) {
      setFormMsg(`⚠️ ${err?.message || "Network error"}`);
    } finally {
      setSaving(false);
    }
  };

  const deleteJob = async (id: number) => {
    await fetch(`${API}/scheduled_emails.php?id=${id}`, {
      method: "DELETE",
      headers: { Authorization: `Bearer ${token}` },
    });
    fetchJobs();
  };

  const sendNow = async (id: number) => {
    try {
      const r = await fetch(`${API}/scheduled_emails.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "send_now", id }),
      });
      const d = await r.json();
      alert(d.message || d.data?.message || "Scheduled job executed!");
      fetchJobs();
      fetchOutbox();
    } catch {
      alert("Error triggering scheduled job.");
    }
  };

  const runSchedulerCheck = async () => {
    setCheckingSched(true); setSchedCheckResult("");
    try {
      const r = await fetch(`${API}/scheduled_emails.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ action: "check_scheduler" }),
      });
      const d = await r.json();
      setSchedCheckResult(d.message || d.data?.message || "Scheduler check completed.");
      fetchJobs();
      fetchOutbox();
    } catch {
      setSchedCheckResult("Error communicating with scheduler.");
    } finally {
      setCheckingSched(false);
    }
  };

  const panelStyle: React.CSSProperties = { padding: 28, height: "100%", overflowY: "auto", background: "#212121", color: "#ececec" };
  const cardStyle: React.CSSProperties = { background: "#171717", border: "1px solid rgba(255, 255, 255, 0.1)", borderRadius: 10, padding: 22, marginBottom: 22 };
  const inputStyle: React.CSSProperties = { background: "#212121", color: "#ececec", border: "1px solid rgba(255, 255, 255, 0.15)", borderRadius: 6, padding: "8px 12px", fontSize: 13, width: "100%", boxSizing: "border-box" as const };
  const labelStyle: React.CSSProperties = { fontSize: 11, color: "#8e8e8e", marginBottom: 5, textTransform: "uppercase", letterSpacing: "0.08em", fontWeight: 600 };

  return (
    <div style={panelStyle}>
      {/* Header */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, marginBottom: 24, flexWrap: "wrap" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div style={{ width: 44, height: 44, borderRadius: 10, background: "#2f2f2f", display: "flex", alignItems: "center", justifyContent: "center", border: "1px solid rgba(255, 255, 255, 0.15)" }}>
            <Mail size={22} color="#ececec" />
          </div>
          <div>
            <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700, color: "#ffffff" }}>Email Automation & Customizable Reports</h1>
            <p style={{ margin: 0, color: "#b4b4b4", fontSize: 13 }}>Generate tailored PDF/Word reports, email directly to officials, and run scheduled deliveries</p>
          </div>
        </div>

        <div style={{ display: "flex", gap: 8 }}>
          <button
            onClick={runSchedulerCheck}
            disabled={checkingSched}
            style={{ background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 6, padding: "8px 16px", cursor: "pointer", fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6, transition: "background 0.15s ease" }}
          >
            {checkingSched ? <RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> : <><RefreshCw size={13} color="#10a37f" /> Check Due Jobs Now</>}
          </button>
        </div>
      </div>

      {schedCheckResult && (
        <div style={{ marginBottom: 20, padding: "10px 16px", background: "rgba(16, 163, 127, 0.1)", border: "1px solid rgba(16, 163, 127, 0.3)", borderRadius: 8, fontSize: 13, color: "#10a37f" }}>
          {schedCheckResult}
        </div>
      )}

      {/* ── CARD 1: Customizable Report Generator & Mailer ── */}
      <div style={cardStyle}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16, flexWrap: "wrap", gap: 10 }}>
          <h3 style={{ margin: 0, fontSize: 16, fontWeight: 700, color: "#ffffff", display: "flex", alignItems: "center", gap: 8 }}>
            <span>📝</span> Customizable Report Generator & Mail Dispatcher
          </h3>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <span style={{ fontSize: 11, color: "#8e8e8e", textTransform: "uppercase" }}>Format:</span>
            <button
              onClick={() => setRptFormat("pdf")}
              style={{
                background: rptFormat === "pdf" ? "#ececec" : "#212121",
                color: rptFormat === "pdf" ? "#0d0d0d" : "#b4b4b4",
                border: "1px solid " + (rptFormat === "pdf" ? "#ececec" : "rgba(255, 255, 255, 0.15)"),
                borderRadius: 6, padding: "4px 12px", cursor: "pointer", fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 5, transition: "all 0.15s ease"
              }}
            >
              📄 PDF Document
            </button>
            <button
              onClick={() => setRptFormat("docx")}
              style={{
                background: rptFormat === "docx" ? "#ececec" : "#212121",
                color: rptFormat === "docx" ? "#0d0d0d" : "#b4b4b4",
                border: "1px solid " + (rptFormat === "docx" ? "#ececec" : "rgba(255, 255, 255, 0.15)"),
                borderRadius: 6, padding: "4px 12px", cursor: "pointer", fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 5, transition: "all 0.15s ease"
              }}
            >
              📝 Word (.docx)
            </button>
          </div>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr 1.5fr", gap: 16, marginBottom: 14 }}>
          <div>
            <div style={labelStyle}>Report Type</div>
            <select value={rptType} onChange={e => setRptType(e.target.value)} style={inputStyle}>
              {REPORT_TYPES.map(rt => <option key={rt.value} value={rt.value}>{rt.label}</option>)}
            </select>
          </div>
          <div>
            <div style={labelStyle}>Document Format</div>
            <select value={rptFormat} onChange={e => setRptFormat(e.target.value as "pdf" | "docx")} style={inputStyle}>
              <option value="pdf">📄 PDF Document (Dompdf A4)</option>
              <option value="docx">📝 Word Document (.docx - Customizable)</option>
            </select>
          </div>
          <div>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 4 }}>
              <div style={labelStyle}>Recipient Email ID(s) — comma separated</div>
              <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                <button
                  type="button"
                  onClick={() => setRptEmails("admin@barani.com")}
                  style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
                >
                  👤 Admin
                </button>
                <button
                  type="button"
                  onClick={() => setRptEmails("hr@company.com")}
                  style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
                >
                  🏢 HR
                </button>
                <button
                  type="button"
                  onClick={() => setRptEmails("finance@company.com")}
                  style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
                >
                  📊 Finance
                </button>
                <button
                  type="button"
                  onClick={() => setRptEmails(tempAccount?.email || "temp@ethereal.email")}
                  style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer", fontWeight: 600 }}
                >
                  📫 Temp Mail
                </button>
                <button
                  type="button"
                  onClick={() => setRptEmails(prev => prev ? `${prev}, admin@barani.com` : "admin@barani.com")}
                  style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#8e8e8e", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
                >
                  + CC Admin
                </button>
              </div>
            </div>
            <input
              value={rptEmails}
              onChange={e => setRptEmails(e.target.value)}
              placeholder="admin@barani.com, director@bhipl.com"
              style={inputStyle}
            />
          </div>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 14 }}>
          <div>
            <div style={labelStyle}>Custom Subject (optional)</div>
            <input
              value={rptCustomSubject}
              onChange={e => setRptCustomSubject(e.target.value)}
              placeholder={rptType === "payroll" ? "Payroll Report — Barani Hydraulics" : `${REPORT_LABELS[rptType] || rptType} Report — Barani Hydraulics`}
              style={inputStyle}
            />
          </div>
          <div>
            <div style={labelStyle}>Additional Notes / Issuer Remarks (optional)</div>
            <input
              value={rptNote}
              onChange={e => setRptNote(e.target.value)}
              placeholder="e.g., Approved for immediate month-end bank transfer."
              style={inputStyle}
            />
          </div>
        </div>

        {/* Live Editable Report Content Textarea */}
        <div style={{ marginBottom: 16 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8, flexWrap: "wrap", gap: 8 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
              <div style={{ ...labelStyle, marginBottom: 0 }}>
                ✉️ Email Content (Official Cover Letter):
              </div>
              <span style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", fontSize: 11, fontWeight: 500, padding: "2px 8px", borderRadius: 4 }}>
                📎 Attached: 1 {rptFormat.toUpperCase()} Document (Full Calculations & Tables)
              </span>
            </div>
            <button
              onClick={generateFormalReport}
              disabled={rptGenerating}
              style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#b4b4b4", borderRadius: 4, padding: "3px 10px", cursor: "pointer", fontSize: 11, fontWeight: 500 }}
            >
              🔄 Reset Cover Letter
            </button>
          </div>
          <textarea
            value={rptPreview}
            onChange={e => setRptPreview(e.target.value)}
            rows={11}
            style={{
              ...inputStyle,
              fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
              fontSize: 13,
              lineHeight: 1.6,
              background: "#212121",
              color: "#ececec",
              resize: "vertical",
              padding: "12px 16px"
            }}
          />
        </div>

        {/* Action Buttons */}
        <div style={{ display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap" }}>
          <button
            onClick={sendToAdminDirectly}
            disabled={rptSending}
            style={{ background: "#2f2f2f", color: "#ececec", border: "1px solid rgba(255, 255, 255, 0.15)", borderRadius: 6, padding: "10px 22px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 8, transition: "background 0.15s ease" }}
          >
            {rptSending ? <><RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> Mailing Admin…</> : <><Send size={14} /> 👤 Send to Admin (admin@barani.com)</>}
          </button>

          <button
            onClick={sendFormalReport}
            disabled={rptSending}
            style={{ background: "#ececec", color: "#0d0d0d", border: "none", borderRadius: 6, padding: "10px 22px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 8, transition: "opacity 0.15s ease" }}
          >
            {rptSending ? <><RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> Mailing…</> : <><Send size={14} /> Mail Report ({rptFormat.toUpperCase()})</>}
          </button>

          <button
            onClick={downloadReportDirectly}
            disabled={rptGenerating}
            style={{ background: "#2f2f2f", color: "#ececec", border: "1px solid rgba(255, 255, 255, 0.15)", borderRadius: 6, padding: "10px 20px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 8 }}
          >
            <Download size={14} /> Download {rptFormat.toUpperCase()} Directly
          </button>

          {rptDownloadUrl && (
            <button
              onClick={() => triggerFileDownload(rptDownloadUrl, rptFileName || `report.${rptFormat}`)}
              style={{ background: "rgba(16, 163, 127, 0.15)", border: "1px solid rgba(16, 163, 127, 0.4)", color: "#10a37f", borderRadius: 6, padding: "9px 16px", cursor: "pointer", fontSize: 12, display: "flex", alignItems: "center", gap: 6, fontWeight: 600 }}
            >
              <Download size={13} /> Download {rptFileName || "Report"}
            </button>
          )}
        </div>

        {rptMsg && (
          <div style={{
            marginTop: 14, padding: "10px 14px", borderRadius: 6, fontSize: 13,
            background: rptMsg.includes("✅") ? "rgba(16, 163, 127, 0.12)" : rptMsg.includes("ℹ️") ? "rgba(255, 255, 255, 0.08)" : "rgba(239, 68, 68, 0.12)",
            border: `1px solid ${rptMsg.includes("✅") ? "rgba(16, 163, 127, 0.3)" : rptMsg.includes("ℹ️") ? "rgba(255, 255, 255, 0.15)" : "rgba(239, 68, 68, 0.3)"}`,
            color: rptMsg.includes("✅") ? "#10a37f" : rptMsg.includes("ℹ️") ? "#ececec" : "#ef4444"
          }}>
            {rptMsg}
          </div>
        )}
      </div>


      {/* ── CARD 2: Create Scheduled Email Job ── */}
      <div style={cardStyle}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 18, flexWrap: "wrap", gap: 10 }}>
          <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600, color: "#ffffff", display: "flex", alignItems: "center", gap: 8 }}>
            <CalendarDays size={18} color="#ececec" /> Schedule Automated Email Job
          </h3>
          {/* Mode Selector */}
          <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
            <button
              type="button"
              onClick={() => setScheduleMode("everyday")}
              style={{
                background: scheduleMode === "everyday" ? "#ececec" : "#212121",
                color: scheduleMode === "everyday" ? "#0d0d0d" : "#b4b4b4",
                border: `1px solid ${scheduleMode === "everyday" ? "#ececec" : "rgba(255, 255, 255, 0.15)"}`,
                borderRadius: 6, padding: "5px 12px", cursor: "pointer", fontSize: 12, fontWeight: 600,
                display: "flex", alignItems: "center", gap: 6, transition: "all 0.15s ease"
              }}
            >
              🔄 Everyday (Daily Mode)
            </button>
            <button
              type="button"
              onClick={() => setScheduleMode("specific_dates")}
              style={{
                background: scheduleMode === "specific_dates" ? "#ececec" : "#212121",
                color: scheduleMode === "specific_dates" ? "#0d0d0d" : "#b4b4b4",
                border: `1px solid ${scheduleMode === "specific_dates" ? "#ececec" : "rgba(255, 255, 255, 0.15)"}`,
                borderRadius: 6, padding: "5px 12px", cursor: "pointer", fontSize: 12, fontWeight: 600,
                display: "flex", alignItems: "center", gap: 6, transition: "all 0.15s ease"
              }}
            >
              📆 Specific Calendar Dates
            </button>
          </div>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr 1fr 1.5fr", gap: 16, marginBottom: 16 }}>
          <div>
            <div style={labelStyle}>Report Type</div>
            <select value={form.report_type} onChange={e => setForm({ ...form, report_type: e.target.value })} style={inputStyle}>
              {REPORT_TYPES.map(rt => <option key={rt.value} value={rt.value}>{rt.label}</option>)}
            </select>
          </div>
          <div>
            <div style={labelStyle}>Attachment Format</div>
            <select value={form.report_format} onChange={e => setForm({ ...form, report_format: e.target.value })} style={inputStyle}>
              <option value="pdf">📄 PDF Document</option>
              <option value="docx">📝 Word Document (.docx)</option>
            </select>
          </div>
          <div>
            <div style={labelStyle}>Send Time (24h)</div>
            <input type="time" value={form.send_time} onChange={e => setForm({ ...form, send_time: e.target.value })} style={inputStyle} />
          </div>
          <div>
            <div style={labelStyle}>Subject (optional)</div>
            <input value={form.subject} onChange={e => setForm({ ...form, subject: e.target.value })} placeholder="Auto-generated if blank" style={inputStyle} />
          </div>
        </div>

        {/* Recipient Emails */}
        <div style={{ marginBottom: 16 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8, flexWrap: "wrap", gap: 6 }}>
            <div style={labelStyle}>📧 Recipient Email IDs</div>
            <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
              <button
                type="button"
                onClick={() => setEmailInputs(["admin@barani.com"])}
                style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
              >
                👤 Admin
              </button>
              <button
                type="button"
                onClick={() => setEmailInputs([tempAccount?.email || "temp@ethereal.email"])}
                style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer", fontWeight: 600 }}
              >
                📫 Temp Mail
              </button>
              <button
                type="button"
                onClick={() => setEmailInputs(prev => [...prev.filter(Boolean), "hr@company.com"])}
                style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
              >
                + HR
              </button>
              <button
                type="button"
                onClick={() => setEmailInputs(prev => [...prev.filter(Boolean), "finance@company.com"])}
                style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "2px 7px", fontSize: 10, cursor: "pointer" }}
              >
                + Finance
              </button>
              <button onClick={() => setEmailInputs([...emailInputs, ""])} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "3px 10px", cursor: "pointer", fontSize: 11 }}>+ Add Email</button>
            </div>
          </div>
          {emailInputs.map((email, i) => (
            <div key={i} style={{ display: "flex", gap: 8, marginBottom: 8 }}>
              <input
                type="email"
                value={email}
                onChange={e => { const a = [...emailInputs]; a[i] = e.target.value; setEmailInputs(a); }}
                placeholder={`official${i + 1}@bhipl.com`}
                style={{ ...inputStyle, flex: 1 }}
              />
              {emailInputs.length > 1 && (
                <button onClick={() => setEmailInputs(emailInputs.filter((_, j) => j !== i))} style={{ background: "rgba(239, 68, 68, 0.1)", border: "1px solid rgba(239, 68, 68, 0.25)", color: "#ef4444", borderRadius: 6, padding: "0 10px", cursor: "pointer" }}>
                  <X size={14} />
                </button>
              )}
            </div>
          ))}
        </div>

        {/* Schedule Mode: Everyday Banner or Specific Dates Picker */}
        {scheduleMode === "everyday" ? (
          <div style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.12)", borderRadius: 8, padding: "14px 18px", marginBottom: 16, display: "flex", alignItems: "center", gap: 12 }}>
            <Clock size={20} color="#10a37f" />
            <div>
              <div style={{ fontWeight: 600, fontSize: 13, color: "#ececec" }}>🔄 Everyday Automation Active</div>
              <div style={{ fontSize: 12, color: "#b4b4b4", marginTop: 2 }}>
                This report will be compiled automatically and dispatched in the background <strong>every single day at {form.send_time} (24h)</strong> with attached <strong>{form.report_format.toUpperCase()}</strong>.
              </div>
            </div>
          </div>
        ) : (
          <div style={{ marginBottom: 16 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
              <div style={labelStyle}>📆 Scheduled Dates</div>
              <button onClick={() => setDateInputs([...dateInputs, ""])} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 4, padding: "3px 10px", cursor: "pointer", fontSize: 11 }}>+ Add Date</button>
            </div>
            <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
              {dateInputs.map((d, i) => (
                <div key={i} style={{ display: "flex", gap: 6, alignItems: "center" }}>
                  <input
                    type="date"
                    value={d}
                    onChange={e => { const a = [...dateInputs]; a[i] = e.target.value; setDateInputs(a); }}
                    style={{ ...inputStyle, width: "auto" }}
                  />
                  {dateInputs.length > 1 && (
                    <button onClick={() => setDateInputs(dateInputs.filter((_, j) => j !== i))} style={{ background: "rgba(239, 68, 68, 0.1)", border: "1px solid rgba(239, 68, 68, 0.25)", color: "#ef4444", borderRadius: 6, padding: "4px 8px", cursor: "pointer" }}>
                      <X size={12} />
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Message */}
        <div style={{ marginBottom: 16 }}>
          <div style={labelStyle}>Message Body / Instructions (optional)</div>
          <textarea value={form.message_body} onChange={e => setForm({ ...form, message_body: e.target.value })} placeholder="Please review the attached formal report." rows={2} style={{ ...inputStyle, resize: "vertical" }} />
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <button
            onClick={saveJob}
            disabled={saving}
            style={{ background: "#ececec", color: "#0d0d0d", border: "none", borderRadius: 6, padding: "10px 24px", cursor: "pointer", fontWeight: 600, fontSize: 13, display: "flex", alignItems: "center", gap: 8, transition: "opacity 0.15s ease" }}
          >
            {saving ? <><RefreshCw size={14} style={{ animation: "spin 1s linear infinite" }} /> Scheduling…</> : <><CalendarDays size={14} /> Save Schedule Job {scheduleMode === "everyday" ? "(Everyday)" : ""}</>}
          </button>
          {formMsg && (
            <div style={{
              padding: "6px 12px", borderRadius: 6, fontSize: 13, fontWeight: 500,
              background: formMsg.includes("✅") ? "rgba(16, 163, 127, 0.12)" : "rgba(239, 68, 68, 0.12)",
              color: formMsg.includes("✅") ? "#10a37f" : "#ef4444",
              border: `1px solid ${formMsg.includes("✅") ? "rgba(16, 163, 127, 0.3)" : "rgba(239, 68, 68, 0.3)"}`
            }}>
              {formMsg}
            </div>
          )}
        </div>
      </div>

      {/* ── CARD 3: Existing Scheduled Jobs ── */}
      <div style={cardStyle}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16, flexWrap: "wrap", gap: 8 }}>
          <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600, color: "#ffffff" }}>⏰ Scheduled Email Queue ({jobs.length})</h3>
          <div style={{ display: "flex", gap: 8 }}>
            <button onClick={runSchedulerCheck} disabled={checkingSched} style={{ background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 6, padding: "6px 14px", cursor: "pointer", fontSize: 12, display: "flex", alignItems: "center", gap: 6, fontWeight: 600, transition: "background 0.15s ease" }}>
              <RefreshCw size={12} color="#10a37f" /> Check Due Jobs
            </button>
            <button onClick={fetchJobs} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#b4b4b4", borderRadius: 6, padding: "6px 12px", cursor: "pointer", fontSize: 12, display: "flex", alignItems: "center", gap: 6 }}>
              <RefreshCw size={12} /> Refresh
            </button>
          </div>
        </div>
        {loading ? (
          <div style={{ textAlign: "center", padding: 30, color: "#b4b4b4" }}><RefreshCw size={20} style={{ animation: "spin 1s linear infinite" }} /></div>
        ) : jobs.length === 0 ? (
          <div style={{ textAlign: "center", padding: 30, color: "#b4b4b4" }}>
            <Mail size={36} style={{ opacity: 0.3 }} />
            <div style={{ marginTop: 10 }}>No scheduled email jobs yet. Create one above!</div>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {jobs.map(job => {
              const isEveryday = job.scheduled_dates?.includes("everyday") || job.scheduled_dates?.includes("daily") || job.scheduled_dates?.includes("*");
              return (
                <div key={job.id} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.1)", borderRadius: 8, padding: "14px 18px", display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 10 }}>
                  <div style={{ flex: 1, minWidth: 200 }}>
                    <div style={{ fontWeight: 600, marginBottom: 4, display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                      <span style={{ color: "#ffffff" }}>{REPORT_TYPES.find(rt => rt.value === job.report_type)?.label || job.report_type}</span>
                      <span style={{ fontSize: 10, background: "#2f2f2f", color: "#ececec", border: "1px solid rgba(255, 255, 255, 0.15)", padding: "2px 7px", borderRadius: 4, textTransform: "uppercase", fontWeight: 600 }}>
                        {job.report_format || 'pdf'}
                      </span>
                      {isEveryday && (
                        <span style={{ fontSize: 10, background: "rgba(16, 163, 127, 0.15)", color: "#10a37f", border: "1px solid rgba(16, 163, 127, 0.3)", padding: "2px 8px", borderRadius: 10, fontWeight: 700 }}>
                          🔄 EVERYDAY
                        </span>
                      )}
                      {!job.is_active && <span style={{ fontSize: 10, background: "rgba(239, 68, 68, 0.15)", color: "#ef4444", padding: "1px 6px", borderRadius: 10 }}>PAUSED</span>}
                    </div>
                    <div style={{ fontSize: 11, color: "#8e8e8e", display: "flex", gap: 16, flexWrap: "wrap" }}>
                      <span>⏰ Time: {job.send_time}</span>
                      <span>📧 Recipients: {(job.recipient_emails || []).join(", ")}</span>
                      <span>{isEveryday ? "🔄 Frequency: Everyday (Daily)" : `📅 Dates: ${(job.scheduled_dates || []).join(", ")}`}</span>
                      {job.last_sent_at && <span style={{ color: "#10a37f" }}>✅ Last sent: {job.last_sent_at}</span>}
                    </div>
                  </div>
                  <div style={{ display: "flex", gap: 8 }}>
                    <button onClick={() => sendNow(job.id)} title="Send Now" style={{ background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 6, padding: "6px 14px", cursor: "pointer", fontSize: 12, display: "flex", alignItems: "center", gap: 5, fontWeight: 500, transition: "background 0.15s ease" }}>
                      <Send size={12} color="#10a37f" /> Send Now
                    </button>
                    <button onClick={() => deleteJob(job.id)} title="Delete" style={{ background: "rgba(239, 68, 68, 0.1)", border: "1px solid rgba(239, 68, 68, 0.25)", color: "#ef4444", borderRadius: 6, padding: "6px 10px", cursor: "pointer" }}>
                      <Trash2 size={12} />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>


      {/* ── CARD 4: Live Temp Mailbox, Admin Mailbox & Outbox Vault ── */}
      <div style={cardStyle}>
        {/* Live Temp Mailbox Control Header */}
        <div style={{
          background: "#212121",
          border: "1px solid rgba(255, 255, 255, 0.1)",
          borderRadius: 10, padding: "16px 20px", marginBottom: 20
        }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 12, marginBottom: 12 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span style={{
                fontSize: 10, fontWeight: 700, padding: "3px 8px", borderRadius: 20,
                background: "rgba(16, 163, 127, 0.15)", color: "#10a37f", border: "1px solid rgba(16, 163, 127, 0.3)", letterSpacing: "0.08em"
              }}>
                ⚡ LIVE TEMP MAIL ACTIVE
              </span>
              <span style={{ fontSize: 12, color: "#8e8e8e" }}>
                ● Real-time sync with Ethereal Public Webmail
              </span>
            </div>
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
              <button
                onClick={() => tempAccount?.web_url && window.open(tempAccount.web_url, "_blank")}
                style={{
                  background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)",
                  color: "#ececec", borderRadius: 6, padding: "5px 12px", cursor: "pointer",
                  fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6, transition: "background 0.15s ease"
                }}
              >
                🌐 Open in Ethereal Webmail
              </button>
              <button
                onClick={generateNewTempAccount}
                disabled={loadingTemp}
                style={{
                  background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)",
                  color: "#ececec", borderRadius: 6, padding: "5px 12px", cursor: "pointer",
                  fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6, transition: "background 0.15s ease"
                }}
              >
                🎲 New Temp Address
              </button>
            </div>
          </div>

          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 12 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, flex: 1, minWidth: 280 }}>
              <span style={{ fontSize: 12, color: "#8e8e8e", fontWeight: 600 }}>Your Temp Address:</span>
              <span style={{
                fontFamily: "'Courier New', monospace", fontSize: 13, fontWeight: 600,
                color: "#ececec", background: "#171717", padding: "5px 12px",
                borderRadius: 6, border: "1px solid rgba(255, 255, 255, 0.15)"
              }}>
                {tempAccount?.email || "Loading temp address..."}
              </span>
              <button
                onClick={copyTempEmailToClipboard}
                style={{
                  background: copiedEmail ? "rgba(16, 163, 127, 0.15)" : "#2f2f2f",
                  border: `1px solid ${copiedEmail ? "rgba(16, 163, 127, 0.3)" : "rgba(255, 255, 255, 0.15)"}`,
                  color: copiedEmail ? "#10a37f" : "#ececec",
                  borderRadius: 6, padding: "5px 10px", cursor: "pointer", fontSize: 12,
                  fontWeight: 600, display: "flex", alignItems: "center", gap: 4
                }}
              >
                {copiedEmail ? "✓ Copied!" : "📋 Copy"}
              </button>
            </div>

            <div style={{ display: "flex", gap: 8 }}>
              <button
                onClick={() => sendTestToTempMail("docx")}
                disabled={tempSending}
                style={{
                  background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)",
                  color: "#ececec", borderRadius: 6, padding: "6px 14px", cursor: "pointer",
                  fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6
                }}
              >
                {tempSending ? <RefreshCw size={12} style={{ animation: "spin 1s linear infinite" }} /> : <Send size={12} />}
                Send Test Report (Word .docx)
              </button>
              <button
                onClick={() => sendTestToTempMail("pdf")}
                disabled={tempSending}
                style={{
                  background: "#ececec", border: "none",
                  color: "#0d0d0d", borderRadius: 6, padding: "6px 14px", cursor: "pointer",
                  fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6
                }}
              >
                {tempSending ? <RefreshCw size={12} style={{ animation: "spin 1s linear infinite" }} /> : <Send size={12} />}
                Send Test Report (PDF)
              </button>
            </div>
          </div>

          {tempMsgAlert && (
            <div style={{
              marginTop: 10, padding: "6px 12px", borderRadius: 6, fontSize: 12, fontWeight: 500,
              background: tempMsgAlert.includes("✅") ? "rgba(16, 163, 127, 0.12)" : "rgba(239, 68, 68, 0.12)",
              color: tempMsgAlert.includes("✅") ? "#10a37f" : "#ef4444",
              border: `1px solid ${tempMsgAlert.includes("✅") ? "rgba(16, 163, 127, 0.3)" : "rgba(239, 68, 68, 0.3)"}`
            }}>
              {tempMsgAlert}
            </div>
          )}
        </div>

        {/* Tab Navigation */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16, flexWrap: "wrap", gap: 10 }}>
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <button
              onClick={() => setMailVaultTab("temp_mailbox")}
              style={{
                background: mailVaultTab === "temp_mailbox" ? "#2f2f2f" : "transparent",
                border: `1px solid ${mailVaultTab === "temp_mailbox" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                color: mailVaultTab === "temp_mailbox" ? "#ffffff" : "#8e8e8e",
                borderRadius: 6, padding: "6px 14px", cursor: "pointer", fontSize: 13, fontWeight: 600,
                display: "flex", alignItems: "center", gap: 6, transition: "all 0.15s ease"
              }}
            >
              📫 Live Temp Mailbox ({tempInbox.length})
            </button>
            <button
              onClick={() => setMailVaultTab("admin_mailbox")}
              style={{
                background: mailVaultTab === "admin_mailbox" ? "#2f2f2f" : "transparent",
                border: `1px solid ${mailVaultTab === "admin_mailbox" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                color: mailVaultTab === "admin_mailbox" ? "#ffffff" : "#8e8e8e",
                borderRadius: 6, padding: "6px 14px", cursor: "pointer", fontSize: 13, fontWeight: 600,
                display: "flex", alignItems: "center", gap: 6, transition: "all 0.15s ease"
              }}
            >
              📥 Admin Mailbox ({adminMailbox.length})
            </button>
            <button
              onClick={() => setMailVaultTab("outbox")}
              style={{
                background: mailVaultTab === "outbox" ? "#2f2f2f" : "transparent",
                border: `1px solid ${mailVaultTab === "outbox" ? "rgba(255, 255, 255, 0.15)" : "transparent"}`,
                color: mailVaultTab === "outbox" ? "#ffffff" : "#8e8e8e",
                borderRadius: 6, padding: "6px 14px", cursor: "pointer", fontSize: 13, fontWeight: 600,
                display: "flex", alignItems: "center", gap: 6, transition: "all 0.15s ease"
              }}
            >
              📬 Outbox Vault ({outbox.length})
            </button>
          </div>
          <button
            onClick={() => { fetchOutbox(); fetchTempInbox(); }}
            style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)", color: "#b4b4b4", borderRadius: 6, padding: "5px 12px", cursor: "pointer", fontSize: 12, display: "flex", alignItems: "center", gap: 6 }}
          >
            <RefreshCw size={12} /> Refresh Inboxes
          </button>
        </div>

        {/* Tab Content: Live Temp Mailbox */}
        {mailVaultTab === "temp_mailbox" && (
          loadingTemp && tempInbox.length === 0 ? (
            <div style={{ textAlign: "center", padding: 24, color: "#8e8e8e" }}><RefreshCw size={18} style={{ animation: "spin 1s linear infinite" }} /></div>
          ) : tempInbox.length === 0 ? (
            <div style={{ textAlign: "center", padding: 36, color: "#8e8e8e", fontSize: 13, border: "1px dashed rgba(255, 255, 255, 0.12)", borderRadius: 8 }}>
              <div style={{ fontSize: 28, marginBottom: 8 }}>📬</div>
              <div style={{ fontWeight: 600, fontSize: 14, color: "#ececec", marginBottom: 4 }}>
                Your Live Temporary Inbox is Active & Ready!
              </div>
              <div>
                Any reports sent to <strong>{tempAccount?.email}</strong> will arrive here in real time.
              </div>
              <button
                onClick={() => sendTestToTempMail("docx")}
                disabled={tempSending}
                style={{
                  marginTop: 14, background: "#ececec", border: "none",
                  color: "#0d0d0d", borderRadius: 6, padding: "8px 18px", cursor: "pointer",
                  fontSize: 13, fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 6
                }}
              >
                {tempSending ? <RefreshCw size={13} style={{ animation: "spin 1s linear infinite" }} /> : <Send size={13} />}
                Send Sample Report to Temp Mailbox Now
              </button>
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {tempInbox.map((mail, idx) => (
                <div key={idx} style={{
                  background: "#212121",
                  border: "1px solid rgba(255, 255, 255, 0.1)",
                  borderRadius: 8, padding: "14px 18px", display: "flex", justifyContent: "space-between",
                  alignItems: "center", flexWrap: "wrap", gap: 12
                }}>
                  <div style={{ flex: 1, minWidth: 240 }}>
                    <div style={{ fontWeight: 600, fontSize: 14, marginBottom: 4, display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                      <span style={{ color: "#ffffff" }}>{mail.subject}</span>
                      <span style={{
                        fontSize: 10, padding: "2px 8px", borderRadius: 10,
                        background: "rgba(16, 163, 127, 0.15)",
                        color: "#10a37f",
                        border: "1px solid rgba(16, 163, 127, 0.3)"
                      }}>
                        🟢 Received in Temp Inbox
                      </span>
                    </div>
                    <div style={{ fontSize: 11, color: "#8e8e8e", display: "flex", gap: 14, flexWrap: "wrap" }}>
                      <span>🕒 {mail.timestamp}</span>
                      <span>👤 From: {mail.from || "Barani Hydraulics"}</span>
                      <span>📧 To: {Array.isArray(mail.to) ? mail.to.join(", ") : (mail.to || tempAccount?.email)}</span>
                      {mail.attachments && mail.attachments.length > 0 && (
                        <span>📎 {mail.attachments.join(", ")}</span>
                      )}
                    </div>
                    {mail.body_preview && (
                      <div style={{ fontSize: 12, color: "#8e8e8e", marginTop: 6, fontStyle: "italic" }}>
                        "{mail.body_preview.substring(0, 140)}..."
                      </div>
                    )}
                  </div>

                  <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                    {mail.attachment_url && (
                      <button
                        onClick={() => triggerFileDownload(mail.attachment_url, mail.attachment_name || (mail.attachments && mail.attachments[0]) || "report")}
                        style={{
                          background: "#2f2f2f",
                          border: "1px solid rgba(255, 255, 255, 0.15)", color: "#ececec", borderRadius: 6, padding: "8px 16px", cursor: "pointer",
                          fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6
                        }}
                      >
                        <Download size={14} /> Download {mail.attachment_url.includes(".docx") ? "Word (.docx)" : "PDF"}
                      </button>
                    )}
                    {mail.webmail_url && (
                      <button
                        onClick={() => window.open(mail.webmail_url, "_blank")}
                        style={{
                          background: "#212121", border: "1px solid rgba(255, 255, 255, 0.15)",
                          color: "#ececec", borderRadius: 6, padding: "8px 12px", cursor: "pointer",
                          fontSize: 12, fontWeight: 500, display: "flex", alignItems: "center", gap: 4
                        }}
                      >
                        🌐 Webmail
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )
        )}

        {/* Tab Content: Admin Mailbox */}
        {mailVaultTab === "admin_mailbox" && (
          loadingOutbox ? (
            <div style={{ textAlign: "center", padding: 24, color: "#8e8e8e" }}><RefreshCw size={18} style={{ animation: "spin 1s linear infinite" }} /></div>
          ) : adminMailbox.length === 0 ? (
            <div style={{ textAlign: "center", padding: 24, color: "#8e8e8e", fontSize: 13 }}>
              No messages received in Admin Mailbox yet. Click "👤 Send to Admin" above to dispatch a customizable report directly to admin.
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {adminMailbox.map((mail, idx) => (
                <div key={idx} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.1)", borderRadius: 8, padding: "12px 16px", display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 10 }}>
                  <div style={{ flex: 1, minWidth: 220 }}>
                    <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 3, display: "flex", alignItems: "center", gap: 8 }}>
                      <span style={{ color: "#ffffff" }}>{mail.subject}</span>
                      <span style={{
                        fontSize: 10, padding: "1px 7px", borderRadius: 10,
                        background: "rgba(255, 255, 255, 0.08)",
                        color: "#ececec",
                        border: "1px solid rgba(255, 255, 255, 0.15)"
                      }}>
                        📥 Admin Inbox
                      </span>
                    </div>
                    <div style={{ fontSize: 11, color: "#8e8e8e", display: "flex", gap: 14, flexWrap: "wrap" }}>
                      <span>🕒 {mail.timestamp}</span>
                      <span>👤 From: {mail.from || "System Dispatcher"}</span>
                      <span>📧 To: {Array.isArray(mail.to) ? mail.to.join(", ") : (mail.to || "admin@barani.com")}</span>
                      {mail.attachments && mail.attachments.length > 0 && (
                        <span>📎 {mail.attachments.join(", ")}</span>
                      )}
                    </div>
                    {mail.body_preview && (
                      <div style={{ fontSize: 11, color: "#8e8e8e", marginTop: 4, fontStyle: "italic" }}>
                        "{mail.body_preview.substring(0, 120)}..."
                      </div>
                    )}
                  </div>

                  {mail.attachment_url && (
                    <button
                      onClick={() => triggerFileDownload(mail.attachment_url, mail.attachment_name || (mail.attachments && mail.attachments[0]) || "report")}
                      style={{
                        background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)",
                        color: "#ececec", borderRadius: 6, padding: "7px 14px", cursor: "pointer",
                        fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6
                      }}
                    >
                      <Download size={13} /> Download {mail.attachment_url.includes(".docx") ? "Word (.docx)" : "PDF"}
                    </button>
                  )}
                </div>
              ))}
            </div>
          )
        )}

        {/* Tab Content: Outbox Vault */}
        {mailVaultTab === "outbox" && (
          outbox.length === 0 ? (
            <div style={{ textAlign: "center", padding: 24, color: "#8e8e8e", fontSize: 13 }}>
              No sent reports in Outbox yet. When you generate and mail reports, their records and downloadable files appear here.
            </div>
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {outbox.map((mail, idx) => (
                <div key={idx} style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.1)", borderRadius: 8, padding: "12px 16px", display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 10 }}>
                  <div style={{ flex: 1, minWidth: 220 }}>
                    <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 3, display: "flex", alignItems: "center", gap: 8 }}>
                      <span style={{ color: "#ffffff" }}>{mail.subject}</span>
                      <span style={{
                        fontSize: 10, padding: "1px 7px", borderRadius: 10,
                        background: mail.delivered ? "rgba(16, 163, 127, 0.15)" : "rgba(255, 255, 255, 0.08)",
                        color: mail.delivered ? "#10a37f" : "#ececec",
                        border: `1px solid ${mail.delivered ? "rgba(16, 163, 127, 0.3)" : "rgba(255, 255, 255, 0.15)"}`
                      }}>
                        {mail.delivered ? "🟢 Delivered via SMTP" : "📁 Stored in Outbox"}
                      </span>
                    </div>
                    <div style={{ fontSize: 11, color: "#8e8e8e", display: "flex", gap: 14, flexWrap: "wrap" }}>
                      <span>🕒 {mail.timestamp}</span>
                      <span>📧 To: {(mail.recipients || []).join(", ")}</span>
                      {mail.attachment_name && <span>📎 Attachment: {mail.attachment_name}</span>}
                    </div>
                  </div>

                  {mail.attachment_url && (
                    <button
                      onClick={() => triggerFileDownload(mail.attachment_url, mail.attachment_name || "report")}
                      style={{
                        background: "#2f2f2f", border: "1px solid rgba(255, 255, 255, 0.15)",
                        color: "#ececec", borderRadius: 6, padding: "7px 14px", cursor: "pointer",
                        fontSize: 12, fontWeight: 600, display: "flex", alignItems: "center", gap: 6
                      }}
                    >
                      <Download size={13} /> Download {mail.attachment_name?.endsWith('.docx') ? 'Word (.docx)' : 'PDF'}
                    </button>
                  )}
                </div>
              ))}
            </div>
          )
        )}

        {/* ── Collapsible Help: Direct Delivery to Personal Gmail ── */}
        <details style={{ marginTop: 20, padding: "10px 14px", background: "#171717", borderRadius: 8, border: "1px solid rgba(255, 255, 255, 0.1)", fontSize: 12 }}>
          <summary style={{ cursor: "pointer", fontWeight: 600, color: "#8e8e8e" }}>
            💡 Want emails delivered directly to your personal @gmail.com? Click for 1-minute setup guide
          </summary>
          <div style={{ marginTop: 10, lineHeight: 1.6, color: "#b4b4b4" }}>
            <div>To deliver emails directly to personal Gmail addresses (e.g. <code>jananiprakash1527@gmail.com</code>):</div>
            <ol style={{ paddingLeft: 20, margin: "8px 0" }}>
              <li>Visit <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noreferrer" style={{ color: "#10a37f" }}>Google App Passwords</a> on your Google account.</li>
              <li>Create a new App Password named <strong>"Barani Reports"</strong> (Google gives you a free 16-character code like <code>abcd efgh ijkl mnop</code>).</li>
              <li>Open <code>.env</code> in this project folder and set:
                <pre style={{ background: "#212121", border: "1px solid rgba(255, 255, 255, 0.1)", padding: "8px 12px", borderRadius: 6, margin: "6px 0", color: "#10a37f", fontSize: 11 }}>
MAIL_HOST=smtp.gmail.com&#10;MAIL_PORT=587&#10;MAIL_ENCRYPTION=tls&#10;MAIL_USERNAME=your_email@gmail.com&#10;MAIL_PASSWORD=your_16_char_app_password
                </pre>
              </li>
            </ol>
            <div>That's it! Every scheduled and manual email will now land directly in your personal Gmail inbox!</div>
          </div>
        </details>
      </div>

    </div>
  );
}


