import React, { useState } from "react";
import { Shield, Lock, User, AlertCircle, Loader2 } from "lucide-react";
import { login } from "../services/api";

export default function LoginScreen({ onLogin }) {
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("admin123");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleFormSubmit = async (e) => {
    e?.preventDefault();
    setError("");

    if (!username.trim()) {
      setError("Please enter username.");
      return;
    }

    setLoading(true);

    try {
      const data = await login(username.trim(), password.trim() || "any");
      if (data.success) {
        onLogin(data.user);
      } else {
        setError(data.message || "Invalid username or password.");
      }
    } catch (err) {
      console.error("Login API error:", err);
      setError("Unable to connect to PHP backend server at http://127.0.0.1:8000.");
    } finally {
      setLoading(false);
    }
  };

  const handleDemoAccountSelect = (demoUser, demoPass) => {
    setUsername(demoUser);
    setPassword(demoPass);
  };

  return (
    <div
      style={{
        width: "100vw",
        minHeight: "100vh",
        height: "100%",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        background: "#171717",
        color: "#ececec",
        overflowY: "auto",
        padding: "24px 12px"
      }}
    >
      <div
        style={{
          width: "min(440px, 94vw)",
          padding: "clamp(24px, 5vw, 36px) clamp(16px, 5vw, 32px)",
          background: "#212121",
          border: "1px solid rgba(255, 255, 255, 0.1)",
          borderRadius: 16,
          boxShadow: "0 8px 32px rgba(0, 0, 0, 0.5)",
          margin: "auto"
        }}
      >
        {/* Top Header */}
        <div style={{ textAlign: "center", marginBottom: 26 }}>
          <div
            style={{
              width: 48,
              height: 48,
              borderRadius: 12,
              background: "#10a37f",
              margin: "0 auto 14px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontWeight: 800,
              fontSize: 18,
              color: "#ffffff",
              letterSpacing: "0.05em",
              boxShadow: "0 2px 8px rgba(0, 0, 0, 0.4)"
            }}
          >
            BH
          </div>

          <h2 style={{ fontSize: 18, fontWeight: 700, textAlign: "center", letterSpacing: "-0.01em", color: "#ffffff" }}>
            Barani Hydraulics (India) Pvt. Ltd.
          </h2>
          <p style={{ fontSize: 12, textAlign: "center", color: "#8e8e8e", marginTop: 4 }}>
            AI SCADA & Industrial Intelligence • Audit Logging Active
          </p>
        </div>

        {/* 1-Click Quick Admin Login */}
        <button
          type="button"
          onClick={() => {
            setUsername("admin");
            setPassword("admin123");
            setTimeout(() => {
              const fakeEvent = { preventDefault: () => {} };
              handleFormSubmit(fakeEvent);
            }, 50);
          }}
          style={{
            width: "100%",
            padding: "10px 14px",
            background: "#2f2f2f",
            border: "1px solid #3c3c3c",
            borderRadius: 8,
            color: "#ececec",
            fontSize: 12,
            fontWeight: 600,
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: 8,
            marginBottom: 16,
            transition: "all 0.15s ease"
          }}
          onMouseEnter={(e) => (e.currentTarget.style.background = "#383838")}
          onMouseLeave={(e) => (e.currentTarget.style.background = "#2f2f2f")}
        >
          <span style={{ color: "#10a37f" }}>⚡</span> Quick Sign In as Admin (Full Access)
        </button>

        <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
          <div style={{ flex: 1, height: 1, background: "rgba(255, 255, 255, 0.1)" }} />
          <span style={{ fontSize: 10, color: "#8e8e8e", textTransform: "uppercase", letterSpacing: "0.08em" }}>or enter credentials</span>
          <div style={{ flex: 1, height: 1, background: "rgba(255, 255, 255, 0.1)" }} />
        </div>

        {/* Login Form */}
        <form onSubmit={handleFormSubmit} style={{ display: "flex", flexDirection: "column", gap: 14 }}>
          <div>
            <label style={{ fontSize: 11, fontFamily: "Inter, sans-serif", color: "#b4b4b4", display: "block", marginBottom: 6, fontWeight: 500 }}>
              USERNAME / OPERATOR ID
            </label>
            <div style={{ position: "relative" }}>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="admin, imran, ragavendra, hussain..."
                disabled={loading}
                style={{
                  width: "100%",
                  padding: "10px 12px 10px 34px",
                  background: "#212121",
                  border: "1px solid #383838",
                  borderRadius: 8,
                  color: "#ececec",
                  fontSize: 13,
                  outline: "none"
                }}
              />
              <User size={15} style={{ position: "absolute", left: 10, top: 12, color: "#8e8e8e" }} />
            </div>
          </div>

          <div>
            <label style={{ fontSize: 11, fontFamily: "Inter, sans-serif", color: "#b4b4b4", display: "block", marginBottom: 6, fontWeight: 500 }}>
              PASSWORD
            </label>
            <div style={{ position: "relative" }}>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                disabled={loading}
                style={{
                  width: "100%",
                  padding: "10px 12px 10px 34px",
                  background: "#212121",
                  border: "1px solid #383838",
                  borderRadius: 8,
                  color: "#ececec",
                  fontSize: 13,
                  outline: "none"
                }}
              />
              <Lock size={15} style={{ position: "absolute", left: 10, top: 12, color: "#8e8e8e" }} />
            </div>
          </div>

          {error && (
            <div
              style={{
                padding: "8px 12px",
                borderRadius: 6,
                background: "rgba(239, 68, 68, 0.12)",
                border: "1px solid rgba(239, 68, 68, 0.3)",
                color: "#fca5a5",
                fontSize: 12,
                display: "flex",
                alignItems: "center",
                gap: 8
              }}
            >
              <AlertCircle size={14} style={{ flexShrink: 0 }} />
              <span>{error}</span>
            </div>
          )}

          <button
            type="submit"
            disabled={loading}
            style={{
              width: "100%",
              padding: "11px",
              background: "#ffffff",
              border: "none",
              borderRadius: 8,
              color: "#0d0d0d",
              fontWeight: 700,
              fontSize: 13,
              cursor: loading ? "default" : "pointer",
              marginTop: 4,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: 8,
              boxShadow: "0 1px 3px rgba(0, 0, 0, 0.3)",
              transition: "background 0.15s ease"
            }}
            onMouseEnter={(e) => (e.currentTarget.style.background = "#e3e3e3")}
            onMouseLeave={(e) => (e.currentTarget.style.background = "#ffffff")}
          >
            {loading ? (
              <>
                <Loader2 size={16} className="animate-spin" /> AUTHENTICATING...
              </>
            ) : (
              "Sign In"
            )}
          </button>
        </form>

        {/* Demo Accounts List */}
        <div style={{ marginTop: 20, paddingTop: 14, borderTop: "1px solid rgba(255, 255, 255, 0.08)" }}>
          <div style={{ fontSize: 10, color: "#8e8e8e", marginBottom: 8, textAlign: "center", textTransform: "uppercase", letterSpacing: "0.05em" }}>
            Available System Accounts
          </div>
          <div style={{ display: "flex", gap: 6, justifyContent: "center", flexWrap: "wrap" }}>
            {[
              { u: "admin", p: "admin123", role: "Admin" },
              { u: "ragavendra", p: "123", role: "Operator" },
              { u: "imran", p: "123", role: "Designer" },
              { u: "hussain", p: "123", role: "Operator" },
              { u: "main", p: "123", role: "Maintenance" },
              { u: "imz", p: "123", role: "Worker" },
            ].map((acc) => (
              <button
                key={acc.u}
                type="button"
                onClick={() => handleDemoAccountSelect(acc.u, acc.p)}
                style={{
                  padding: "4px 8px",
                  background: username === acc.u ? "#383838" : "#262626",
                  border: username === acc.u ? "1px solid #10a37f" : "1px solid #343434",
                  borderRadius: 6,
                  color: username === acc.u ? "#ffffff" : "#b4b4b4",
                  fontSize: 11,
                  cursor: "pointer"
                }}
              >
                {acc.u} <span style={{ color: "#8e8e8e", fontSize: 9 }}>({acc.role})</span>
              </button>
            ))}
          </div>
          <div style={{ marginTop: 12, textAlign: "center", fontSize: 10, color: "#6b7280" }}>
            🔒 Audit Trail: Logins & data retrieval logged to MySQL <code style={{ color: "#9ca3af" }}>gri_db.audit_logs</code>
          </div>
        </div>
      </div>
    </div>
  );
}
