import React, { useState, useMemo } from "react";
import {
  BarChart, Bar, LineChart, Line, AreaChart, Area,
  PieChart, Pie, Cell, RadarChart, Radar, PolarGrid,
  PolarAngleAxis, PolarRadiusAxis, ResponsiveContainer,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend,
} from "recharts";

const PALETTE = [
  "#3B82F6", "#10B981", "#F59E0B", "#EC4899",
  "#8B5CF6", "#06B6D4", "#F97316", "#84CC16",
  "#A78BFA", "#14B8A6",
];

const TOOLTIP_STYLE = {
  backgroundColor: "#0b1329",
  border: "1px solid rgba(59,130,246,0.4)",
  borderRadius: 8,
  color: "#e2e8f0",
  fontSize: 12,
  fontFamily: "monospace",
  boxShadow: "0 8px 24px rgba(0,0,0,0.5)"
};

export function parseTableToChartData(text) {
  if (!text) return null;
  const lines = text.split("\n").map(l => l.trim()).filter(Boolean);
  const tableLines = lines.filter(l => l.startsWith("|") && l.endsWith("|"));
  if (tableLines.length < 2) return null;

  // Extract and clean headers
  const headers = tableLines[0].split("|").map(s => s.replace(/\*\*/g, "").trim()).filter(Boolean);
  const startIdx = tableLines[1]?.includes("---") ? 2 : 1;
  const rows = tableLines.slice(startIdx).map(l =>
    l.split("|").map(s => s.replace(/\*\*/g, "").trim()).filter(Boolean)
  );
  if (headers.length < 2 || rows.length === 0) return null;

  const parsed = rows.map(r => {
    const obj = {};
    headers.forEach((h, i) => {
      const val = (r[i] || "").replace(/[`*]/g, "").trim();
      // Remove currency symbols, commas, and percentage signs for numeric extraction
      const cleanNumStr = val.replace(/[₹$,% ]/g, "");
      const num = parseFloat(cleanNumStr);
      // Clean header name
      const cleanHeader = h.replace(/[`*]/g, "").trim();
      obj[cleanHeader] = (!isNaN(num) && cleanNumStr !== "") ? num : val;
    });
    return obj;
  });

  // Verify at least one numeric key exists
  const hasNum = parsed.some(row => Object.values(row).some(v => typeof v === "number"));
  return hasNum ? parsed : null;
}

export function detectChartType(query) {
  if (!query) return "bar";
  const q = query.toLowerCase();
  if (q.includes("polar") || q.includes("radar") || q.includes("spider")) return "radar";
  if (q.includes("pie") || q.includes("donut") || q.includes("distribution") || q.includes("share") || q.includes("breakdown")) return "pie";
  if (q.includes("area chart") || q.includes("area graph") || q.includes("cumulative")) return "area";
  if (q.includes("line chart") || q.includes("trend analysis") || q.includes("trend") ||
      q.includes("over time") || q.includes("time series") || q.includes("history")) return "line";
  return "bar";
}

export function hasChartableData(text) {
  const data = parseTableToChartData(text);
  return !!(data && data.length > 0);
}

function getNumericKeys(data) {
  if (!data || data.length === 0) return [];
  const first = data[0];
  return Object.keys(first).filter(k => typeof first[k] === "number");
}

function getLabelKey(data) {
  if (!data || data.length === 0) return "label";
  const first = data[0];
  return Object.keys(first).find(k => typeof first[k] !== "number") || Object.keys(first)[0];
}

function ParetoBarChart({ data, unit = "m" }) {
  return (
    <div style={{ width: "100%", height: 290 }}>
      <ResponsiveContainer>
        <BarChart data={data} margin={{ top: 28, right: 20, left: 10, bottom: 35 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(59,130,246,0.12)" />
          <XAxis dataKey="label" tick={{ fill: "#E2E8F0", fontSize: 12, fontWeight: 700 }} />
          <YAxis tick={{ fill: "#94A3B8", fontSize: 11 }} />
          <Tooltip
            contentStyle={TOOLTIP_STYLE}
            formatter={(val, name, item) => [
              `${val.toLocaleString()} ${unit} (${item.payload.percentage || ""})`,
              item.payload.label || "Value"
            ]}
          />
          <Bar
            dataKey="value"
            radius={[6, 6, 0, 0]}
            label={{
              position: "top",
              fill: "#F8FAFC",
              fontSize: 11,
              fontWeight: 700,
              formatter: (val) => `${val.toLocaleString()}${unit}`
            }}
          >
            {data.map((entry, index) => (
              <Cell
                key={`cell-${index}`}
                fill={entry.color || (index === 0 ? "#ef4444" : index === 1 ? "#3b82f6" : "#60a5fa")}
              />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

function InlineBarChart({ data }) {
  const labelKey = getLabelKey(data);
  const numKeys = getNumericKeys(data);
  const hasCellColors = data.some(d => d.color);

  return (
    <div style={{ width: "100%", height: 280 }}>
      <ResponsiveContainer>
        <BarChart data={data} margin={{ top: 10, right: 20, left: 10, bottom: 40 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(59,130,246,0.12)" />
          <XAxis dataKey={labelKey} tick={{ fill: "#94A3B8", fontSize: 11 }} angle={-25} textAnchor="end" height={55} />
          <YAxis tick={{ fill: "#94A3B8", fontSize: 11 }} />
          <Tooltip contentStyle={TOOLTIP_STYLE} />
          {numKeys.length > 1 && <Legend wrapperStyle={{ fontSize: 11, color: "#94A3B8", paddingTop: 8 }} />}
          {numKeys.map((k, i) => (
            <Bar key={k} dataKey={k} fill={PALETTE[i % PALETTE.length]} radius={[5, 5, 0, 0]}>
              {hasCellColors && data.map((entry, idx) => (
                <Cell key={`bar-cell-${idx}`} fill={entry.color || PALETTE[idx % PALETTE.length]} />
              ))}
            </Bar>
          ))}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

function InlineLineChart({ data }) {
  const labelKey = getLabelKey(data);
  const numKeys = getNumericKeys(data);
  return (
    <div style={{ width: "100%", height: 280 }}>
      <ResponsiveContainer>
        <LineChart data={data} margin={{ top: 10, right: 20, left: 10, bottom: 40 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(59,130,246,0.12)" />
          <XAxis dataKey={labelKey} tick={{ fill: "#94A3B8", fontSize: 11 }} angle={-25} textAnchor="end" height={55} />
          <YAxis tick={{ fill: "#94A3B8", fontSize: 11 }} />
          <Tooltip contentStyle={TOOLTIP_STYLE} />
          {numKeys.length > 1 && <Legend wrapperStyle={{ fontSize: 11, color: "#94A3B8", paddingTop: 8 }} />}
          {numKeys.map((k, i) => (
            <Line key={k} type="monotone" dataKey={k} stroke={PALETTE[i % PALETTE.length]} strokeWidth={3} dot={{ r: 4 }} activeDot={{ r: 6 }} />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

function InlineAreaChart({ data }) {
  const labelKey = getLabelKey(data);
  const numKeys = getNumericKeys(data);
  return (
    <div style={{ width: "100%", height: 280 }}>
      <ResponsiveContainer>
        <AreaChart data={data} margin={{ top: 10, right: 20, left: 10, bottom: 40 }}>
          <defs>
            {numKeys.map((k, i) => (
              <linearGradient key={k} id={`grad-${k}`} x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor={PALETTE[i % PALETTE.length]} stopOpacity={0.45} />
                <stop offset="95%" stopColor={PALETTE[i % PALETTE.length]} stopOpacity={0.02} />
              </linearGradient>
            ))}
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(59,130,246,0.12)" />
          <XAxis dataKey={labelKey} tick={{ fill: "#94A3B8", fontSize: 11 }} angle={-25} textAnchor="end" height={55} />
          <YAxis tick={{ fill: "#94A3B8", fontSize: 11 }} />
          <Tooltip contentStyle={TOOLTIP_STYLE} />
          {numKeys.length > 1 && <Legend wrapperStyle={{ fontSize: 11, color: "#94A3B8", paddingTop: 8 }} />}
          {numKeys.map((k, i) => (
            <Area key={k} type="monotone" dataKey={k} stroke={PALETTE[i % PALETTE.length]} strokeWidth={2.5} fill={`url(#grad-${k})`} />
          ))}
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

function InlinePieChart({ data }) {
  const labelKey = getLabelKey(data);
  const numKey = getNumericKeys(data)[0];
  if (!numKey) return null;
  const pieData = data.map(d => ({ name: String(d[labelKey]).slice(0, 24), value: d[numKey] }));
  return (
    <div style={{ width: "100%", height: 300 }}>
      <ResponsiveContainer>
        <PieChart>
          <Pie
            data={pieData}
            cx="50%" cy="48%"
            outerRadius={105}
            innerRadius={45}
            paddingAngle={3}
            dataKey="value"
            label={({ name, percent }) => `${name} (${(percent * 100).toFixed(1)}%)`}
            labelLine={{ stroke: "rgba(148,163,184,0.4)" }}
          >
            {pieData.map((_, i) => <Cell key={i} fill={PALETTE[i % PALETTE.length]} />)}
          </Pie>
          <Tooltip contentStyle={TOOLTIP_STYLE} />
          <Legend wrapperStyle={{ fontSize: 11, color: "#94A3B8" }} />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}

function InlineRadarChart({ data }) {
  const labelKey = getLabelKey(data);
  const numKeys = getNumericKeys(data);
  return (
    <div style={{ width: "100%", height: 300 }}>
      <ResponsiveContainer>
        <RadarChart data={data} cx="50%" cy="50%" outerRadius={105}>
          <PolarGrid stroke="rgba(59,130,246,0.25)" />
          <PolarAngleAxis dataKey={labelKey} tick={{ fill: "#94A3B8", fontSize: 10 }} />
          <PolarRadiusAxis tick={{ fill: "#475569", fontSize: 9 }} />
          <Tooltip contentStyle={TOOLTIP_STYLE} />
          {numKeys.length > 1 && <Legend wrapperStyle={{ fontSize: 11, color: "#94A3B8" }} />}
          {numKeys.map((k, i) => (
            <Radar key={k} name={k} dataKey={k} stroke={PALETTE[i % PALETTE.length]} fill={PALETTE[i % PALETTE.length]} fillOpacity={0.3} strokeWidth={2} />
          ))}
        </RadarChart>
      </ResponsiveContainer>
    </div>
  );
}

export default function ChartMessage({ messageText, chartType, chartLabel, visual }) {
  const isPareto = visual?.type === "pareto_bar";
  const initialType = isPareto ? "pareto_bar" : (chartType || detectChartType(chartLabel) || "bar");
  const [activeType, setActiveType] = useState(initialType);

  const tableData = useMemo(() => {
    if (visual?.data && visual.data.length > 0) return visual.data;
    return parseTableToChartData(messageText);
  }, [visual, messageText]);

  const syntheticData = useMemo(() => {
    if (tableData && tableData.length > 0) return null;
    const matches = [...(messageText || "").matchAll(/([A-Za-z][A-Za-z0-9\s\-\/]+?):\s*([\d,]+(?:\.\d+)?)/g)];
    if (matches.length >= 2) {
      return matches.slice(0, 12).map(m => ({
        label: m[1].trim().slice(0, 22),
        value: parseFloat(m[2].replace(/,/g, "")),
      }));
    }
    return null;
  }, [tableData, messageText]);

  const chartData = tableData || syntheticData;
  if (!chartData || chartData.length === 0) return null;

  // Compute KPI statistics
  const numKeys = getNumericKeys(chartData);
  const primaryKey = numKeys[0] || (chartData[0]?.value !== undefined ? 'value' : null);
  let stats = null;
  if (primaryKey) {
    const vals = chartData.map(d => typeof d[primaryKey] === 'number' ? d[primaryKey] : parseFloat(d[primaryKey])).filter(v => !isNaN(v));
    if (vals.length > 0) {
      const sum = vals.reduce((a, b) => a + b, 0);
      stats = {
        total: sum,
        avg: sum / vals.length,
        max: Math.max(...vals),
        min: Math.min(...vals),
        count: vals.length
      };
    }
  }

  const chartButtons = [
    ...(isPareto ? [{ id: "pareto_bar", label: "📊 Pareto", desc: "Pareto Loss Ranking" }] : []),
    { id: "bar", label: "📊 Bar", desc: "Bar Comparison" },
    { id: "line", label: "📈 Line", desc: "Trend Curve" },
    { id: "pie", label: "🥧 Pie", desc: "Distribution Ratio" },
    { id: "area", label: "📉 Area", desc: "Volume Gradient" },
    { id: "radar", label: "🕸️ Radar", desc: "Polar Axis Balance" }
  ];

  return (
    <div style={{
      marginTop: 16, padding: "18px 20px", borderRadius: 12,
      background: "linear-gradient(180deg, rgba(13, 22, 45, 0.95) 0%, rgba(9, 15, 30, 0.98) 100%)",
      border: "1px solid rgba(59, 130, 246, 0.35)",
      boxShadow: "0 8px 32px rgba(0, 0, 0, 0.45)"
    }}>
      {/* ── Visual Telemetry Header (PDF Spec) ── */}
      {visual?.title && (
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12, paddingBottom: 8, borderBottom: "1px solid rgba(59,130,246,0.2)" }}>
          <div style={{ fontSize: 13.5, fontWeight: 700, color: "#F8FAFC", letterSpacing: "0.02em" }}>
            {visual.title}
          </div>
          {visual.source && (
            <div style={{ fontSize: 10.5, color: "#94A3B8", fontFamily: "var(--font-mono)" }}>
              Telemetry Source: <span style={{ color: "#38BDF8", fontWeight: 700 }}>{visual.source}</span>
            </div>
          )}
        </div>
      )}

      {/* ── Toolbar: Dynamic Chart Switcher ── */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 14, flexWrap: "wrap", gap: 10 }}>
        <div style={{ display: "flex", gap: 6, alignItems: "center", flexWrap: "wrap" }}>
          {chartButtons.map(btn => (
            <button
              key={btn.id}
              onClick={() => setActiveType(btn.id)}
              style={{
                background: activeType === btn.id ? "linear-gradient(135deg, #2563EB, #1D4ED8)" : "rgba(255,255,255,0.06)",
                border: `1px solid ${activeType === btn.id ? "#60A5FA" : "rgba(255,255,255,0.1)"}`,
                color: activeType === btn.id ? "#FFFFFF" : "#94A3B8",
                borderRadius: 6, padding: "5px 11px", fontSize: 11, fontWeight: 600, cursor: "pointer",
                boxShadow: activeType === btn.id ? "0 2px 10px rgba(37, 99, 235, 0.45)" : "none",
                transition: "all 0.15s ease"
              }}
              title={btn.desc}
            >
              {btn.label}
            </button>
          ))}
        </div>
        <span style={{
          fontSize: 9.5, color: "#60A5FA", background: "rgba(59,130,246,0.15)",
          border: "1px solid rgba(59,130,246,0.3)", borderRadius: 4, padding: "2px 8px",
          fontWeight: 700, letterSpacing: "0.06em", fontFamily: "var(--font-mono)"
        }}>
          ⚡ INDUSTRIAL TELEMETRY BI ENGINE
        </span>
      </div>

      {/* ── KPI Analytics Summary ── */}
      {stats && (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(110px, 1fr))", gap: 8, marginBottom: 14 }}>
          <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 8, padding: "8px 12px" }}>
            <div style={{ fontSize: 9.5, color: "#64748B", textTransform: "uppercase", letterSpacing: "0.05em" }}>Total Sum</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: "#93C5FD" }}>{stats.total.toLocaleString(undefined, { maximumFractionDigits: 1 })}</div>
          </div>
          <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 8, padding: "8px 12px" }}>
            <div style={{ fontSize: 9.5, color: "#64748B", textTransform: "uppercase", letterSpacing: "0.05em" }}>Average</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: "#34D399" }}>{stats.avg.toLocaleString(undefined, { maximumFractionDigits: 1 })}</div>
          </div>
          <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 8, padding: "8px 12px" }}>
            <div style={{ fontSize: 9.5, color: "#64748B", textTransform: "uppercase", letterSpacing: "0.05em" }}>Peak Value</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: "#F59E0B" }}>{stats.max.toLocaleString(undefined, { maximumFractionDigits: 1 })}</div>
          </div>
          <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 8, padding: "8px 12px" }}>
            <div style={{ fontSize: 9.5, color: "#64748B", textTransform: "uppercase", letterSpacing: "0.05em" }}>Dataset</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: "#C084FC" }}>{stats.count} records</div>
          </div>
        </div>
      )}

      {/* ── Active Chart Component ── */}
      {activeType === "pareto_bar" && <ParetoBarChart data={chartData} unit={visual?.unit || "m"} />}
      {activeType === "bar"        && <InlineBarChart data={chartData} />}
      {activeType === "line"       && <InlineLineChart data={chartData} />}
      {activeType === "area"       && <InlineAreaChart data={chartData} />}
      {activeType === "pie"        && <InlinePieChart data={chartData} />}
      {activeType === "radar"      && <InlineRadarChart data={chartData} />}

      <div style={{ marginTop: 10, display: "flex", justifyContent: "space-between", fontSize: 10, color: "#475569", fontFamily: "var(--font-mono)" }}>
        <span>Active View: <strong style={{ color: "#94A3B8", textTransform: "uppercase" }}>{activeType}</strong></span>
        <span>{chartData.length} live records &bull; gri_db database</span>
      </div>
    </div>
  );
}
