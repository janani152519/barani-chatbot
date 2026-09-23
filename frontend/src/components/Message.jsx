import React, { useState } from "react";
import { motion } from "framer-motion";
import {
  Bot, User, Volume2, VolumeX, ShieldCheck, ShieldAlert,
  Copy, Check, FileSpreadsheet, FileText, Mail, Download,
  Sparkles, Terminal, CheckCircle2, ArrowRight
} from "lucide-react";
import ReportCard from "./ReportCard";
import ChartMessage, { hasChartableData } from "./ChartMessage";

// ─── Markdown Table & Inline Renderer ────────────────────────────────────────

function RichContent({ text }) {
  if (!text) return null;
  const lines = text.split("\n");
  const elements = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];

    // Markdown Table
    if (line.trim().startsWith("|") && line.trim().endsWith("|")) {
      const tableLines = [];
      while (i < lines.length && lines[i].trim().startsWith("|") && lines[i].trim().endsWith("|")) {
        tableLines.push(lines[i].trim());
        i++;
      }
      if (tableLines.length >= 2) {
        const headers = tableLines[0].split("|").map(s => s.trim()).filter(Boolean);
        const startIndex = (tableLines[1] && tableLines[1].includes("---")) ? 2 : 1;
        const rows = tableLines.slice(startIndex).map(tl => tl.split("|").map(s => s.trim()).filter(Boolean));
        elements.push(
          <div key={`table-${i}`} style={{ overflowX: "auto", margin: "12px 0", borderRadius: 8, border: "1px solid rgba(255, 255, 255, 0.1)" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12, fontFamily: "var(--font-mono)" }}>
              <thead>
                <tr style={{ background: "#171717" }}>
                  {headers.map((h, hi) => (
                    <th key={hi} style={{ padding: "9px 14px", textAlign: "left", color: "#ECECEC", fontWeight: 700, letterSpacing: "0.03em", fontSize: 11, borderBottom: "1px solid rgba(255, 255, 255, 0.1)" }}>
                      {renderInline(h)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, ri) => (
                  <tr key={ri} style={{ borderBottom: "1px solid rgba(59, 130, 246, 0.1)", background: ri % 2 === 0 ? "rgba(255,255,255,0.02)" : "rgba(255,255,255,0.04)" }}>
                    {r.map((c, ci) => (
                      <td key={ci} style={{ padding: "8px 14px", color: "#CBD5E1" }}>
                        {renderInline(c)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );
        continue;
      }
    }

    if (!line.trim()) {
      elements.push(<div key={i} style={{ height: 6 }} />);
      i++;
      continue;
    }

    if (line.startsWith("### ")) {
      elements.push(
        <div key={i} style={{ fontSize: 13, fontWeight: 700, color: "#93C5FD", marginTop: 10, marginBottom: 4, fontFamily: "var(--font-mono)" }}>
          {renderInline(line.replace("### ", ""))}
        </div>
      );
      i++;
      continue;
    }

    if (line.startsWith("## ")) {
      elements.push(
        <div key={i} style={{ fontSize: 14.5, fontWeight: 700, color: "#DBEAFE", marginTop: 12, marginBottom: 5 }}>
          {renderInline(line.replace("## ", ""))}
        </div>
      );
      i++;
      continue;
    }

    // Bullet points
    if (line.trim().startsWith("• ") || line.trim().startsWith("- ")) {
      elements.push(
        <div key={i} style={{ display: "flex", gap: 8, lineHeight: 1.65, marginBottom: 2, paddingLeft: 4 }}>
          <span style={{ color: "#10a37f", flexShrink: 0, marginTop: 1 }}>•</span>
          <span>{renderInline(line.trim().replace(/^[•\-] /, ""))}</span>
        </div>
      );
      i++;
      continue;
    }

    const rendered = renderInline(line);
    elements.push(
      <div key={i} style={{ lineHeight: 1.7, marginBottom: 2 }}>{rendered}</div>
    );
    i++;
  }

  return (
    <div className="msg-content" style={{ fontSize: 13.5, color: "#CBD5E1", lineHeight: 1.7 }}>
      {elements}
    </div>
  );
}

function renderInline(text) {
  if (!text) return "";
  const parts = [];
  let remaining = text;
  let key = 0;

  while (remaining.length > 0) {
    const boldMatch = remaining.match(/^([\s\S]*?)\*\*([^*]+)\*\*([\s\S]*)$/);
    if (boldMatch) {
      if (boldMatch[1]) parts.push(<span key={key++}>{boldMatch[1]}</span>);
      parts.push(<strong key={key++} style={{ color: "#E2E8F0", fontWeight: 700 }}>{boldMatch[2]}</strong>);
      remaining = boldMatch[3];
      continue;
    }

    const codeMatch = remaining.match(/^([\s\S]*?)`([^`]+)`([\s\S]*)$/);
    if (codeMatch) {
      if (codeMatch[1]) parts.push(<span key={key++}>{codeMatch[1]}</span>);
      parts.push(
        <code key={key++} style={{
          fontFamily: "var(--font-mono)", fontSize: "0.88em",
          padding: "2px 7px", borderRadius: 5,
          background: "rgba(59, 130, 246, 0.1)",
          color: "#93C5FD", border: "1px solid rgba(59, 130, 246, 0.2)", fontWeight: 600
        }}>
          {codeMatch[2]}
        </code>
      );
      remaining = codeMatch[3];
      continue;
    }

    const italicMatch = remaining.match(/^([\s\S]*?)\*([^*]+)\*([\s\S]*)$/);
    if (italicMatch) {
      if (italicMatch[1]) parts.push(<span key={key++}>{italicMatch[1]}</span>);
      parts.push(<em key={key++} style={{ color: "#94A3B8", fontStyle: "italic" }}>{italicMatch[2]}</em>);
      remaining = italicMatch[3];
      continue;
    }
    parts.push(<span key={key++}>{remaining}</span>);
    break;
  }
  return parts.length > 0 ? parts : text;
}

// ─── 4-Stage Autonomous Query Lifecycle Component (PDF Page 1) ───────────────

function AutonomousLifecycleBar({ stages }) {
  const defaultStages = [
    { stage: 1, title: "1. User Prompt", desc: "Natural English (Voice/Text)" },
    { stage: 2, title: "2. LLM & Schema Guard", desc: "Context Schema Injection" },
    { stage: 3, title: "3. Safe Execution", desc: "AST Read-Only Enforcement" },
    { stage: 4, title: "4. Synthesis & Plot", desc: "Natural Insights & Charts" }
  ];
  const items = stages || defaultStages;

  return (
    <div style={{
      margin: "12px 0 16px 0",
      padding: "10px 14px",
      borderRadius: 8,
      background: "linear-gradient(90deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%)",
      border: "1px solid rgba(59, 130, 246, 0.25)"
    }}>
      <div style={{
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: "0.08em",
        color: "#60A5FA",
        marginBottom: 8,
        display: "flex",
        alignItems: "center",
        gap: 6
      }}>
        <Sparkles size={11} color="#60A5FA" />
        <span>END-TO-END AUTONOMOUS QUERY LIFECYCLE</span>
      </div>
      <div style={{
        display: "grid",
        gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))",
        gap: 8
      }}>
        {items.map((st, idx) => (
          <div
            key={idx}
            style={{
              background: "rgba(255, 255, 255, 0.03)",
              border: "1px solid rgba(59, 130, 246, 0.2)",
              borderRadius: 6,
              padding: "6px 9px",
              display: "flex",
              flexDirection: "column",
              gap: 2
            }}
          >
            <div style={{ fontSize: 10.5, fontWeight: 700, color: "#38BDF8", display: "flex", alignItems: "center", gap: 4 }}>
              <CheckCircle2 size={10} color="#10B981" />
              <span>{st.title}</span>
            </div>
            <div style={{ fontSize: 9.5, color: "#94A3B8", lineHeight: 1.25 }}>
              {st.desc}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ─── Zero-Trust AST Query Validation & Injection Firewall Box (PDF Page 2) ───

function AstGuardrailBanner({ astValidation }) {
  if (!astValidation) return null;
  const isBlocked = astValidation.status === "BLOCKED";

  if (isBlocked) {
    return (
      <div style={{
        margin: "12px 0",
        padding: "12px 16px",
        borderRadius: 8,
        background: "rgba(239, 68, 68, 0.12)",
        border: "1px solid rgba(239, 68, 68, 0.5)",
        color: "#FCA5A5"
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8, fontWeight: 700, fontSize: 12, color: "#EF4444" }}>
          <ShieldAlert size={16} />
          <span>ZERO-TRUST AST GUARDRAIL FIREWALL: MUTATION BLOCKED</span>
        </div>
        <div style={{ fontSize: 11, marginTop: 4, color: "#FECACA" }}>
          <strong>Operation:</strong> {astValidation.operation_type} &bull; <strong>Action:</strong> {astValidation.system_action}
        </div>
        <div style={{ fontSize: 10, marginTop: 4, opacity: 0.8, fontFamily: "var(--font-mono)" }}>
          Audit Trail: Event recorded to `audit_logs` table. Database engine state remains unmodified.
        </div>
      </div>
    );
  }

  return (
    <div style={{
      margin: "10px 0",
      padding: "8px 14px",
      borderRadius: 6,
      background: "rgba(16, 185, 129, 0.08)",
      border: "1px solid rgba(16, 185, 129, 0.3)",
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      flexWrap: "wrap",
      gap: 8
    }}>
      <div style={{ display: "flex", alignItems: "center", gap: 6, color: "#10B981", fontSize: 11, fontWeight: 700 }}>
        <ShieldCheck size={14} />
        <span>VALIDATED STRICTLY READ-ONLY (AST Checked via sqlglot)</span>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 10, color: "#94A3B8", fontFamily: "var(--font-mono)" }}>
        {astValidation.execution_ms && (
          <span>Execution: <strong style={{ color: "#38BDF8" }}>{astValidation.execution_ms}ms</strong></span>
        )}
        <span>Bounded Timeout: <strong>5000ms</strong></span>
        <span>Row Ceiling: <strong>1000 rows</strong></span>
      </div>
    </div>
  );
}

// ─── Generated Executable SQL Viewer with Copy Button (PDF Page 1 & 2) ────────

function GeneratedSqlBox({ sql }) {
  const [copied, setCopied] = useState(false);
  if (!sql) return null;

  const handleCopy = () => {
    navigator.clipboard.writeText(sql);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div style={{
      margin: "12px 0",
      borderRadius: 8,
      overflow: "hidden",
      border: "1px solid rgba(59, 130, 246, 0.35)",
      background: "#080E1E"
    }}>
      <div style={{
        padding: "6px 12px",
        background: "rgba(30, 41, 59, 0.8)",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        borderBottom: "1px solid rgba(59, 130, 246, 0.2)"
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 10, fontWeight: 700, color: "#38BDF8", letterSpacing: "0.04em" }}>
          <Terminal size={12} />
          <span>GENERATED EXECUTABLE SQL (SQL SERVER T-SQL / ANSI) - VALIDATED STRICTLY READ-ONLY</span>
        </div>
        <button
          onClick={handleCopy}
          style={{
            background: "rgba(255, 255, 255, 0.08)",
            border: "1px solid rgba(255, 255, 255, 0.15)",
            borderRadius: 4,
            padding: "2px 8px",
            color: copied ? "#10B981" : "#94A3B8",
            fontSize: 10,
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            gap: 4
          }}
        >
          {copied ? <Check size={11} /> : <Copy size={11} />}
          <span>{copied ? "Copied" : "Copy SQL"}</span>
        </button>
      </div>
      <div style={{
        padding: "10px 14px",
        fontFamily: "var(--font-mono)",
        fontSize: 11.5,
        color: "#93C5FD",
        lineHeight: 1.5,
        overflowX: "auto",
        whiteSpace: "pre-wrap"
      }}>
        {sql}
      </div>
    </div>
  );
}

// ─── Automated Insight Synthesis Narrative Card with TTS Speech Readout ───────

function OperationalNarrativeCard({ narrative }) {
  const [speaking, setSpeaking] = useState(false);
  if (!narrative) return null;

  const handleSpeak = () => {
    if (!window.speechSynthesis) return;
    if (speaking) {
      window.speechSynthesis.cancel();
      setSpeaking(false);
      return;
    }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(narrative);
    utterance.rate = 0.95;
    utterance.onend = () => setSpeaking(false);
    utterance.onerror = () => setSpeaking(false);
    setSpeaking(true);
    window.speechSynthesis.speak(utterance);
  };

  return (
    <div style={{
      margin: "12px 0",
      padding: "12px 16px",
      borderRadius: 8,
      background: "linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 95, 70, 0.15) 100%)",
      border: "1px solid rgba(16, 185, 129, 0.35)",
      borderLeft: "4px solid #10B981"
    }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 6 }}>
        <div style={{ fontSize: 10.5, fontWeight: 700, color: "#10B981", letterSpacing: "0.06em", display: "flex", alignItems: "center", gap: 6 }}>
          <span>📊 AUTOMATED INSIGHT SYNTHESIS</span>
        </div>
        <button
          onClick={handleSpeak}
          title={speaking ? "Stop voice readout" : "Plant floor hands-free voice readout"}
          style={{
            background: speaking ? "rgba(239, 68, 68, 0.2)" : "rgba(16, 185, 129, 0.2)",
            border: `1px solid ${speaking ? "rgba(239, 68, 68, 0.4)" : "rgba(16, 185, 129, 0.4)"}`,
            borderRadius: 20,
            padding: "3px 10px",
            color: speaking ? "#EF4444" : "#10B981",
            fontSize: 10.5,
            fontWeight: 600,
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            gap: 5,
            transition: "all 0.2s"
          }}
        >
          {speaking ? <VolumeX size={12} /> : <Volume2 size={12} />}
          <span>{speaking ? "Stop Audio" : "Voice Readout"}</span>
        </button>
      </div>
      <div style={{ fontSize: 13, color: "#ECFDF5", fontStyle: "italic", lineHeight: 1.55 }}>
        "{narrative}"
      </div>
    </div>
  );
}

// ─── Tabular Records View with Multi-Format Automated Export (PDF Page 3) ────

function QueryRecordsTable({ records, columns, title, narrative }) {
  const [exporting, setExporting] = useState(false);
  const [exportMessage, setExportMessage] = useState(null);

  if (!records || records.length === 0) return null;
  const cols = columns || Object.keys(records[0]);

  const handleExport = async (format) => {
    setExporting(true);
    setExportMessage(null);

    let recipient = "";
    if (format === "email") {
      recipient = prompt("Enter recipient email address for SMTP dispatch:", "jananiprakash1527@gmail.com");
      if (!recipient) {
        setExporting(false);
        return;
      }
    }

    try {
      const res = await fetch("/api/export_query.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          format,
          title: title || "Shop Floor Telemetry Query",
          records,
          narrative,
          recipient_email: recipient
        })
      });
      const data = await res.json();
      if (data.success) {
        setExportMessage(data.message || `Export complete!`);
        if (data.download_url && format !== "email") {
          window.open(data.download_url, "_blank");
        }
      } else {
        setExportMessage(`⚠️ ${data.message || "Export failed."}`);
      }
    } catch (e) {
      setExportMessage(`⚠️ Connection error: ${e.message}`);
    } finally {
      setExporting(false);
    }
  };

  return (
    <div style={{
      marginTop: 14,
      borderRadius: 10,
      border: "1px solid rgba(59, 130, 246, 0.25)",
      background: "rgba(15, 23, 42, 0.6)",
      overflow: "hidden"
    }}>
      {/* Table Export Action Bar */}
      <div style={{
        padding: "8px 14px",
        background: "rgba(30, 41, 59, 0.7)",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        flexWrap: "wrap",
        gap: 8,
        borderBottom: "1px solid rgba(59, 130, 246, 0.15)"
      }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: "#E2E8F0" }}>
          Query Result Set ({records.length} records)
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
          <button
            onClick={() => handleExport("xlsx")}
            disabled={exporting}
            style={{
              padding: "4px 9px",
              borderRadius: 5,
              background: "rgba(16, 185, 129, 0.15)",
              border: "1px solid rgba(16, 185, 129, 0.4)",
              color: "#34D399",
              fontSize: 10.5,
              fontWeight: 600,
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              gap: 4
            }}
          >
            <FileSpreadsheet size={12} />
            <span>Excel (.xlsx)</span>
          </button>
          <button
            onClick={() => handleExport("pdf")}
            disabled={exporting}
            style={{
              padding: "4px 9px",
              borderRadius: 5,
              background: "rgba(59, 130, 246, 0.15)",
              border: "1px solid rgba(59, 130, 246, 0.4)",
              color: "#60A5FA",
              fontSize: 10.5,
              fontWeight: 600,
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              gap: 4
            }}
          >
            <FileText size={12} />
            <span>PDF Brief</span>
          </button>
          <button
            onClick={() => handleExport("email")}
            disabled={exporting}
            style={{
              padding: "4px 9px",
              borderRadius: 5,
              background: "rgba(168, 85, 247, 0.15)",
              border: "1px solid rgba(168, 85, 247, 0.4)",
              color: "#C084FC",
              fontSize: 10.5,
              fontWeight: 600,
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              gap: 4
            }}
          >
            <Mail size={12} />
            <span>Email Alert</span>
          </button>
        </div>
      </div>

      {exportMessage && (
        <div style={{ padding: "6px 14px", background: "rgba(59, 130, 246, 0.1)", fontSize: 11, color: "#38BDF8" }}>
          {exportMessage}
        </div>
      )}

      {/* Responsive Table */}
      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 11.5, fontFamily: "var(--font-mono)" }}>
          <thead>
            <tr style={{ background: "rgba(30, 41, 59, 0.5)" }}>
              {cols.map((col, cidx) => (
                <th key={cidx} style={{ padding: "8px 12px", textAlign: "left", color: "#94A3B8", fontSize: 10.5, textTransform: "uppercase" }}>
                  {col}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {records.slice(0, 10).map((row, ridx) => (
              <tr key={ridx} style={{ borderBottom: "1px solid rgba(255, 255, 255, 0.05)", background: ridx % 2 === 0 ? "transparent" : "rgba(255, 255, 255, 0.02)" }}>
                {cols.map((col, cidx) => (
                  <td key={cidx} style={{ padding: "7px 12px", color: "#E2E8F0" }}>
                    {row[col] !== undefined && row[col] !== null ? String(row[col]) : "-"}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── Main Message Component ──────────────────────────────────────────────────

export default function Message({ message }) {
  const isUser = message.role === "user";

  if (isUser) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.25 }}
        style={{ display: "flex", justifyContent: "flex-end", width: "100%", marginBottom: 4 }}
      >
        <div style={{ display: "flex", alignItems: "flex-end", gap: 8, maxWidth: "80%" }}>
          <div style={{
            padding: "10px 18px",
            borderRadius: "20px",
            background: "#2f2f2f",
            border: "1px solid rgba(255, 255, 255, 0.08)",
            color: "#ECECEC", fontSize: 14,
            lineHeight: 1.55, fontWeight: 400
          }}>
            <div style={{ marginBottom: 2, fontSize: 9.5, color: "#8E8E8E", fontFamily: "var(--font-mono)", fontWeight: 600, letterSpacing: "0.05em" }}>
              OPERATOR • {message.timestamp || "Just now"}
            </div>
            <div>{message.text}</div>
          </div>
          <div style={{
            width: 28, height: 28, borderRadius: "50%",
            background: "#383838",
            display: "flex", alignItems: "center", justifyContent: "center",
            flexShrink: 0
          }}>
            <User size={14} color="#ECECEC" />
          </div>
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
      style={{ display: "flex", justifyContent: "flex-start", width: "100%", marginBottom: 4 }}
    >
      <div style={{ display: "flex", alignItems: "flex-start", gap: 10, maxWidth: "94%", width: "100%" }}>
        {/* Bot Avatar — ChatGPT style green circle with bot icon */}
        <div style={{
          width: 28, height: 28, borderRadius: "50%", flexShrink: 0, marginTop: 4,
          background: "#10a37f",
          display: "flex", alignItems: "center", justifyContent: "center",
          overflow: "hidden"
        }}>
          <Bot size={15} color="#FFFFFF" />
        </div>

        {/* Message Bubble */}
        <div style={{
          flex: 1, padding: "16px 20px",
          borderRadius: "12px",
          background: "#212121",
          border: "1px solid rgba(255, 255, 255, 0.08)",
          boxShadow: "none"
        }}>
          {/* Header Row */}
          <div style={{
            display: "flex", alignItems: "center", justifyContent: "space-between",
            marginBottom: 10, paddingBottom: 8,
            borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
            fontFamily: "var(--font-mono)", fontSize: 10
          }}>
            <div style={{ display: "flex", alignItems: "center", gap: 6, color: "#ECECEC", fontWeight: 700 }}>
              <span style={{ color: "#10a37f" }}>BH SCADA AI</span>
              <span style={{ color: "#525252" }}>•</span>
              <span style={{ color: "#8E8E8E", fontWeight: 500 }}>Offline Engine</span>
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
              {message.timestamp && (
                <span style={{ color: "#64748B" }}>{message.timestamp}</span>
              )}
              <span style={{
                padding: "2px 7px", borderRadius: 5,
                background: "rgba(16, 185, 129, 0.12)",
                border: "1px solid rgba(16, 185, 129, 0.3)",
                color: "#10B981", fontWeight: 700, fontSize: 9, letterSpacing: "0.05em"
              }}>
                ✓ VERIFIED READ-ONLY
              </span>
            </div>
          </div>

          {/* 1. Only show security firewall alert if an operation was BLOCKED */}
          {message.ast_validation?.status === "BLOCKED" && (
            <AstGuardrailBanner astValidation={message.ast_validation} />
          )}

          {/* 2. Automated Insight Narrative with Voice Readout (if available) */}
          {message.operational_narrative && (
            <OperationalNarrativeCard narrative={message.operational_narrative} />
          )}

          {/* 3. Natural Answer Content */}
          <RichContent text={message.text} />

          {/* 4. Inline Chart & Pareto Bar Visualization — ONLY when explicitly requested by user */}
          {message.isChartRequested && message.visual && (
            <ChartMessage
              visual={message.visual}
              messageText={message.text}
              chartType={message.visual?.type || message.chartType || "bar"}
              chartLabel={message.visual?.title || message.chartLabel || message.intent || "Telemetry Analytics"}
            />
          )}

          {/* 7. Tabular Query Records with Multi-Format Export — ONLY when explicitly requested */}
          {message.isTableRequested && message.records && message.records.length > 0 && (
            <QueryRecordsTable
              records={message.records}
              columns={message.columns}
              title={message.visual?.title || message.chartLabel}
              narrative={message.operational_narrative}
            />
          )}

          {message.report && <ReportCard report={message.report} />}
        </div>
      </div>
    </motion.div>
  );
}
