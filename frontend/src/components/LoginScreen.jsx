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
        height: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        background: "#171717",
        color: "#ececec"
      }}
    >
      <div
        style={{
          width: "min(440px, 92vw)",
          padding: "36px 32px",
          background: "#212121",
          border: "1px solid rgba(255, 255, 255, 0.1)",
          borderRadius: 16,
          boxShadow: "0 8px 32px rgba(0, 0, 0, 0.5)",
        }}
      >
        {/* Top Header */}
        <div style={{ textAlign: "center", marginBottom: 28 }}>
          <div
            style={{
              width: 52,
              height: 52,
              borderRadius: 14,
              background: "#10a37f",
              margin: "0 auto 16px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              boxShadow: "0 4px 16px rgba(0, 0, 0, 0.3)"
            }}
          >
            <Shield size={26} style={{ color: "#ffffff" }} />
          </div>

          <h2 style={{ fontSize: 20, fontWeight: 700, textAlign: "center", letterSpacing: "0.02em", color: "#ffffff" }}>
            GRI_DB MACHINE INTELLIGENCE
          </h2>
          <p style={{ fontSize: 13, textAlign: "center", color: "#b4b4b4", marginTop: 4 }}>
            SCADA & Factory Automation AI Chatbot Portal
          </p>
        </div>

        {/* Login Form */}
        <form onSubmit={handleFormSubmit} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
          <div>
            <label style={{ fontSize: 11, fontFamily: "monospace", color: "#b4b4b4", display: "block", marginBottom: 6 }}>
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
                  padding: "11px 12px 11px 36px",
                  background: "#2f2f2f",
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  borderRadius: 8,
                  color: "#ececec",
                  fontSize: 13,
                  outline: "none"
                }}
              />
              <User size={15} style={{ position: "absolute", left: 12, top: 13, color: "#8e8e8e" }} />
            </div>
          </div>

          <div>
            <label style={{ fontSize: 11, fontFamily: "monospace", color: "#b4b4b4", display: "block", marginBottom: 6 }}>
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
                  padding: "11px 12px 11px 36px",
                  background: "#2f2f2f",
                  border: "1px solid rgba(255, 255, 255, 0.15)",
                  borderRadius: 8,
                  color: "#ececec",
                  fontSize: 13,
                  outline: "none"
                }}
              />
              <Lock size={15} style={{ position: "absolute", left: 12, top: 13, color: "#8e8e8e" }} />
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
              padding: "12px",
              background: "#ffffff",
              border: "none",
              borderRadius: 8,
              color: "#0d0d0d",
              fontWeight: 700,
              fontSize: 13,
              letterSpacing: "0.04em",
              cursor: loading ? "default" : "pointer",
              marginTop: 4,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: 8,
              boxShadow: "0 2px 8px rgba(0, 0, 0, 0.25)"
            }}
          >
            {loading ? (
              <>
                <Loader2 size={16} className="animate-spin" /> AUTHENTICATING...
              </>
            ) : (
              "ACCESS CHATBOT PORTAL"
            )}
          </button>
        </form>

        {/* Demo Account Pills */}
        <div style={{ marginTop: 24, paddingTop: 18, borderTop: "1px solid rgba(255, 255, 255, 0.08)" }}>
          <div style={{ fontSize: 10, fontFamily: "monospace", color: "#8e8e8e", marginBottom: 8, textAlign: "center" }}>
            SELECT LIVE GRI_DB USER (CLICK TO POPULATE & LOGIN)
          </div>
          <div style={{ display: "flex", gap: 6, justifyContent: "center", flexWrap: "wrap" }}>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("admin", "admin123")}
              style={{
                padding: "5px 9px",
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: 6,
                color: "#ececec",
                fontSize: 11,
                cursor: "pointer"
              }}
            >
              admin (Admin)
            </button>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("ragavendra", "123")}
              style={{
                padding: "5px 9px",
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: 6,
                color: "#10a37f",
                fontSize: 11,
                cursor: "pointer"
              }}
            >
              ragavendra (Operator)
            </button>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("imran", "123")}
              style={{
                padding: "5px 9px",
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: 6,
                color: "#f59e0b",
                fontSize: 11,
                cursor: "pointer"
              }}
            >
              imran (Designer)
            </button>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("hussain", "123")}
              style={{
                padding: "5px 9px",
                background: "#2f2f2f",
                border: "1px solid rgba(255, 255, 255, 0.1)",
                borderRadius: 6,
                color: "#ececec",
                fontSize: 11,
                cursor: "pointer"
              }}
            >
              hussain (Operator)
            </button>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("main", "123")}
              style={{
                padding: "4px 8px",
                background: "#1e293b",
                border: "1px solid #334155",
                borderRadius: 4,
                color: "#f43f5e",
                fontSize: 10,
                cursor: "pointer"
              }}
            >
              main (Maintenance)
            </button>
            <button
              type="button"
              onClick={() => handleDemoAccountSelect("imz", "123")}
              style={{
                padding: "4px 8px",
                background: "#1e293b",
                border: "1px solid #334155",
                borderRadius: 4,
                color: "#38bdf8",
                fontSize: 10,
                cursor: "pointer"
              }}
            >
              imz (Worker)
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
