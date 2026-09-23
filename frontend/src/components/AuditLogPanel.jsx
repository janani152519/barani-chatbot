import React, { useState, useEffect } from "react";
import { Shield, RefreshCw, Search, Clock, User, CheckCircle2, FileText, Database } from "lucide-react";
import { getAuditLogs } from "../services/api";

export default function AuditLogPanel() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");

  const fetchLogs = async (query = search) => {
    setLoading(true);
    setError("");
    try {
      const data = await getAuditLogs(50, query);
      if (data.success) {
        setLogs(data.data?.logs || data.logs || []);
        setTotal(data.data?.total || data.total || 0);
      } else {
        setError(data.message || "Failed to load audit logs");
      }
    } catch (err) {
      console.error("Audit log error:", err);
      setError("Unable to connect to database audit trail.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, []);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    fetchLogs(search);
  };

  return (
    <div style={{ maxWidth: 1100, margin: "0 auto", padding: "20px 24px", color: "#ececec" }}>
      {/* Header */}
      <div style={{
        display: "flex", alignItems: "center", justifyContent: "space-between",
        flexWrap: "wrap", gap: 16, marginBottom: 20, paddingBottom: 16,
        borderBottom: "1px solid #343434"
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div style={{
            width: 40, height: 40, borderRadius: 10,
            background: "#2f2f2f", border: "1px solid #3c3c3c",
            display: "flex", alignItems: "center", justifyContent: "center"
          }}>
            <Shield size={20} color="#10a37f" />
          </div>
          <div>
            <h2 style={{ fontSize: 18, fontWeight: 700, color: "#ffffff", letterSpacing: "-0.01em" }}>
              Database Security & Audit Trail
            </h2>
            <p style={{ fontSize: 12, color: "#8e8e8e", marginTop: 2 }}>
              Live telemetry recorded in MySQL <code style={{ color: "#10a37f" }}>gri_db.audit_logs</code> (Total {total} Events)
            </p>
          </div>
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <form onSubmit={handleSearchSubmit} style={{ position: "relative" }}>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search operator, remarks, login..."
              style={{
                background: "#212121",
                border: "1px solid #383838",
                borderRadius: 8,
                padding: "7px 12px 7px 32px",
                color: "#ececec",
                fontSize: 12,
                outline: "none",
                width: 240
              }}
            />
            <Search size={14} style={{ position: "absolute", left: 10, top: 9, color: "#8e8e8e" }} />
          </form>

          <button
            onClick={() => fetchLogs()}
            disabled={loading}
            style={{
              padding: "7px 14px",
              background: "#2f2f2f",
              border: "1px solid #383838",
              borderRadius: 8,
              color: "#ececec",
              fontSize: 12,
              fontWeight: 500,
              cursor: loading ? "default" : "pointer",
              display: "flex",
              alignItems: "center",
              gap: 6
            }}
          >
            <RefreshCw size={13} className={loading ? "animate-spin" : ""} color="#10a37f" />
            <span>Refresh</span>
          </button>
        </div>
      </div>

      {/* Stats Summary Cards */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12, marginBottom: 20 }}>
        <div style={{ background: "#262626", border: "1px solid #343434", borderRadius: 10, padding: "14px 16px" }}>
          <div style={{ fontSize: 11, color: "#8e8e8e", textTransform: "uppercase", letterSpacing: "0.05em", fontWeight: 600 }}>
            Audit Enforcement
          </div>
          <div style={{ fontSize: 16, fontWeight: 700, color: "#10a37f", marginTop: 4, display: "flex", alignItems: "center", gap: 6 }}>
            <CheckCircle2 size={16} /> Active & Stored in DB
          </div>
          <div style={{ fontSize: 11, color: "#8e8e8e", marginTop: 2 }}>
            Every login & data retrieval logged
          </div>
        </div>

        <div style={{ background: "#262626", border: "1px solid #343434", borderRadius: 10, padding: "14px 16px" }}>
          <div style={{ fontSize: 11, color: "#8e8e8e", textTransform: "uppercase", letterSpacing: "0.05em", fontWeight: 600 }}>
            Active Database
          </div>
          <div style={{ fontSize: 16, fontWeight: 700, color: "#ffffff", marginTop: 4, display: "flex", alignItems: "center", gap: 6 }}>
            <Database size={16} color="#10a37f" /> gri_db (MySQL)
          </div>
          <div style={{ fontSize: 11, color: "#8e8e8e", marginTop: 2 }}>
            Table: <code style={{ color: "#10a37f" }}>audit_logs</code> (Longtext remarks)
          </div>
        </div>

        <div style={{ background: "#262626", border: "1px solid #343434", borderRadius: 10, padding: "14px 16px" }}>
          <div style={{ fontSize: 11, color: "#8e8e8e", textTransform: "uppercase", letterSpacing: "0.05em", fontWeight: 600 }}>
            Recent Retrieved Events
          </div>
          <div style={{ fontSize: 16, fontWeight: 700, color: "#ffffff", marginTop: 4 }}>
            {logs.length} Loaded (of {total})
          </div>
          <div style={{ fontSize: 11, color: "#8e8e8e", marginTop: 2 }}>
            Chronological audit ledger
          </div>
        </div>
      </div>

      {error && (
        <div style={{ padding: "10px 14px", background: "rgba(239,68,68,0.12)", border: "1px solid rgba(239,68,68,0.3)", borderRadius: 8, color: "#fca5a5", fontSize: 12, marginBottom: 16 }}>
          {error}
        </div>
      )}

      {/* Logs Table / List */}
      <div style={{ background: "#262626", border: "1px solid #343434", borderRadius: 12, overflow: "hidden" }}>
        <div style={{
          padding: "12px 18px", background: "#212121", borderBottom: "1px solid #343434",
          display: "grid", gridTemplateColumns: "160px 110px 140px 1fr", gap: 12,
          fontSize: 11, fontWeight: 700, color: "#8e8e8e", textTransform: "uppercase", letterSpacing: "0.05em"
        }}>
          <div>Timestamp</div>
          <div>Operator</div>
          <div>Action Type</div>
          <div>Audit Remarks & Retrieved Details</div>
        </div>

        <div style={{ maxHeight: 520, overflowY: "auto" }}>
          {loading && logs.length === 0 ? (
            <div style={{ padding: 40, textAlign: "center", color: "#8e8e8e", fontSize: 13 }}>
              Loading database audit records...
            </div>
          ) : logs.length === 0 ? (
            <div style={{ padding: 40, textAlign: "center", color: "#8e8e8e", fontSize: 13 }}>
              No audit records matching your search.
            </div>
          ) : (
            logs.map((log) => {
              const isAdmin = (log.operator_name || "").toLowerCase().includes("admin");
              const isLogin = (log.action_type || "").includes("login");
              return (
                <div
                  key={log.id}
                  style={{
                    padding: "12px 18px",
                    borderBottom: "1px solid #2d2d2d",
                    display: "grid",
                    gridTemplateColumns: "160px 110px 140px 1fr",
                    gap: 12,
                    alignItems: "center",
                    fontSize: 12,
                    background: isLogin ? "rgba(16, 163, 127, 0.03)" : "transparent",
                    transition: "background 0.15s ease"
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.background = "#2a2a2a")}
                  onMouseLeave={(e) => (e.currentTarget.style.background = isLogin ? "rgba(16, 163, 127, 0.03)" : "transparent")}
                >
                  <div style={{ color: "#8e8e8e", fontFamily: "monospace", fontSize: 11, display: "flex", alignItems: "center", gap: 5 }}>
                    <Clock size={12} color="#6b7280" />
                    <span>{log.timestamp || "CURRENT_TIMESTAMP"}</span>
                  </div>

                  <div>
                    <span style={{
                      display: "inline-flex", alignItems: "center", gap: 4,
                      padding: "2px 7px", borderRadius: 4, fontSize: 11, fontWeight: 600,
                      background: isAdmin ? "rgba(16, 163, 127, 0.15)" : "#212121",
                      border: `1px solid ${isAdmin ? "rgba(16, 163, 127, 0.4)" : "#383838"}`,
                      color: isAdmin ? "#10a37f" : "#d1d5db"
                    }}>
                      <User size={10} />
                      {log.operator_name || "System"}
                    </span>
                  </div>

                  <div>
                    <span style={{
                      display: "inline-block", padding: "2px 6px", borderRadius: 4,
                      fontSize: 10.5, fontWeight: 700, fontFamily: "monospace", textTransform: "uppercase",
                      background: isLogin ? "rgba(59, 130, 246, 0.15)" : "#212121",
                      border: `1px solid ${isLogin ? "rgba(59, 130, 246, 0.3)" : "#383838"}`,
                      color: isLogin ? "#93c5fd" : "#b4b4b4"
                    }}>
                      {log.action_type || "audit"}
                    </span>
                  </div>

                  <div style={{
                    color: log.remarks?.includes("admin logged in and these details were retrieved") ? "#ececec" : "#b4b4b4",
                    fontSize: 12, lineHeight: 1.45, wordBreak: "break-word"
                  }}>
                    {log.remarks?.includes("admin logged in and these details were retrieved") ? (
                      <>
                        <span style={{ color: "#10a37f", fontWeight: 700 }}>
                          admin logged in and these details were retrieved:
                        </span>
                        <span>
                          {log.remarks.replace("admin logged in and these details were retrieved:", "")}
                        </span>
                      </>
                    ) : (
                      log.remarks || "—"
                    )}
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}
