import React, { useState } from "react";
import { Download, Mail, CheckCircle2, FileText, Loader2, Send } from "lucide-react";
import { triggerFileDownload, emailReport } from "../services/api";

export default function ReportCard({ report }) {
  const [downloading, setDownloading] = useState(false);
  const [emailing, setEmailing] = useState(false);
  const [emailGroup, setEmailGroup] = useState("HR");
  const [statusMessage, setStatusMessage] = useState(null);
  const [showEmailSelect, setShowEmailSelect] = useState(false);

  const formatExt = report?.format?.toUpperCase() || "PDF";
  const title = report?.title || "Company Report";
  const sizeKb = report?.file_size ? (report.file_size / 1024).toFixed(1) + " KB" : "";

  const handleDownload = async () => {
    if (!report?.id || downloading) return;
    setDownloading(true);
    try {
      await triggerFileDownload(report.id, report.file_name || `${title}.${report.format}`);
      setStatusMessage({ type: "success", text: "Download started!" });
    } catch (err) {
      setStatusMessage({ type: "error", text: "Download failed." });
    } finally {
      setDownloading(false);
      setTimeout(() => setStatusMessage(null), 4000);
    }
  };

  const handleEmailSend = async (groupToUse) => {
    const targetGroup = groupToUse || emailGroup;
    if (!report?.id || emailing) return;
    setEmailing(true);
    setShowEmailSelect(false);
    try {
      const res = await emailReport(report.id, targetGroup);
      if (res.success) {
        setStatusMessage({ type: "success", text: res.message || `Dispatched to ${targetGroup}!` });
      } else {
        setStatusMessage({ type: "error", text: res.message || "Email dispatch failed." });
      }
    } catch (err) {
      setStatusMessage({ type: "error", text: "Failed to send email." });
    } finally {
      setEmailing(false);
      setTimeout(() => setStatusMessage(null), 5000);
    }
  };

  return (
    <div
      style={{
        marginTop: 12,
        padding: "14px 16px",
        background: "#FFFFFF",
        border: "1.5px solid #2563EB",
        borderRadius: 10,
        display: "flex",
        flexDirection: "column",
        gap: 12,
        maxWidth: 440,
        boxShadow: "0 4px 16px rgba(37, 99, 235, 0.12)"
      }}
    >
      {/* Header Info */}
      <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
        <div
          style={{
            width: 36,
            height: 36,
            borderRadius: 6,
            background: "linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            flexShrink: 0,
            boxShadow: "0 2px 8px rgba(37, 99, 235, 0.35)"
          }}
        >
          <FileText size={18} style={{ color: "#FFFFFF" }} />
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontWeight: 700, fontSize: 13, color: "#0F172A", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
            {title}
          </div>
          <div style={{ fontSize: 11, color: "#64748B", marginTop: 2, display: "flex", alignItems: "center", gap: 8 }}>
            <span
              style={{
                background: "#DBEAFE",
                color: "#1D4ED8",
                padding: "1px 6px",
                borderRadius: 4,
                fontFamily: "var(--font-mono)",
                fontSize: 10,
                fontWeight: 700,
                border: "1px solid #93C5FD"
              }}
            >
              {formatExt}
            </span>
            {sizeKb && <span>{sizeKb}</span>}
          </div>
        </div>
      </div>

      {/* Status Feedback Toast */}
      {statusMessage && (
        <div
          style={{
            padding: "6px 10px",
            fontSize: 11,
            borderRadius: 6,
            background: statusMessage.type === "success" ? "#DCFCE7" : "#FEE2E2",
            border: `1px solid ${statusMessage.type === "success" ? "#86EFAC" : "#FCA5A5"}`,
            color: statusMessage.type === "success" ? "#15803D" : "#B91C1C",
            display: "flex",
            alignItems: "center",
            gap: 6
          }}
        >
          <CheckCircle2 size={12} />
          {statusMessage.text}
        </div>
      )}

      {/* Action Buttons */}
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
        {/* Download Button */}
        <button
          onClick={handleDownload}
          disabled={downloading}
          style={{
            flex: 1,
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: 6,
            padding: "8px 12px",
            background: "linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%)",
            border: "none",
            borderRadius: 6,
            color: "#ffffff",
            fontSize: 12,
            fontWeight: 700,
            cursor: downloading ? "default" : "pointer",
            boxShadow: "0 2px 8px rgba(37, 99, 235, 0.35)",
            transition: "opacity 0.2s"
          }}
        >
          {downloading ? (
            <>
              <Loader2 size={13} className="animate-spin" /> Downloading...
            </>
          ) : (
            <>
              <Download size={13} /> Download Report
            </>
          )}
        </button>

        {/* Email Toggle Button */}
        <button
          onClick={() => setShowEmailSelect(!showEmailSelect)}
          disabled={emailing}
          style={{
            display: "flex",
            alignItems: "center",
            gap: 6,
            padding: "8px 12px",
            background: "#EFF6FF",
            border: "1px solid #93C5FD",
            borderRadius: 6,
            color: "#1D4ED8",
            fontSize: 12,
            fontWeight: 700,
            cursor: emailing ? "default" : "pointer"
          }}
        >
          {emailing ? (
            <>
              <Loader2 size={13} className="animate-spin" /> Sending...
            </>
          ) : (
            <>
              <Mail size={13} style={{ color: "#2563EB" }} /> Email Report
            </>
          )}
        </button>
      </div>

      {/* Email Group Selection Dropdown */}
      {showEmailSelect && (
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 8,
            padding: "8px 10px",
            background: "#F8FAFC",
            borderRadius: 6,
            border: "1.5px solid #2563EB"
          }}
        >
          <span style={{ fontSize: 11, color: "#475569", fontWeight: 600 }}>Send to:</span>
          <select
            value={emailGroup}
            onChange={(e) => setEmailGroup(e.target.value)}
            style={{
              background: "#FFFFFF",
              color: "#0F172A",
              border: "1px solid #CBD5E1",
              borderRadius: 4,
              padding: "4px 8px",
              fontSize: 11,
              outline: "none"
            }}
          >
            <option value="HR">HR Department</option>
            <option value="Finance">Finance Team</option>
            <option value="Management">Management</option>
            <option value="Admin">Admin</option>
          </select>
          <button
            onClick={() => handleEmailSend(emailGroup)}
            style={{
              background: "linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%)",
              color: "#fff",
              border: "none",
              borderRadius: 4,
              padding: "4px 10px",
              fontSize: 11,
              fontWeight: 700,
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              gap: 4
            }}
          >
            <Send size={10} /> Send
          </button>
        </div>
      )}
    </div>
  );
}
