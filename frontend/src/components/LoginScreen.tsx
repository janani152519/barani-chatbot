import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Eye, EyeOff, Shield, AlertCircle } from "lucide-react";

interface Props {
  onLogin: (role: string) => void;
}

const DEMO_USERS = [
  { id: "admin@barani.com", pass: "admin123", role: "Admin", name: "Rajesh Kumar" },
  { id: "hr@barani.com", pass: "hr123", role: "HR", name: "Priya Sharma" },
  { id: "EMP105", pass: "emp123", role: "Employee", name: "Ravi Shankar" },
];

export default function LoginScreen({ onLogin }: Props) {
  const [employeeId, setEmployeeId] = useState("");
  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [remember, setRemember] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleLogin = async () => {
    setError("");
    if (!employeeId || !password) {
      setError("Please enter username and password.");
      return;
    }
    setLoading(true);

    try {
      const res = await fetch("http://127.0.0.1:8000/api/auth.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "login", username: employeeId, password: password })
      });
      const data = await res.json();

      if (data.success) {
        localStorage.setItem("api_token", data.token);
        localStorage.setItem("user", JSON.stringify(data.user));
        onLogin(data.user.role ? data.user.role.toUpperCase() : "ADMIN");
      } else {
        // Fallback for demo preset if backend not matching
        const match = DEMO_USERS.find(
          (u) => (u.id.toLowerCase() === employeeId.toLowerCase() || u.id === employeeId) && u.pass === password
        );
        if (match) {
          onLogin(match.role);
        } else {
          setError(data.message || "Invalid username or password. Try janani / password123 or admin / password123");
        }
      }
    } catch (e) {
      // Fallback for offline demo accounts
      const match = DEMO_USERS.find(
        (u) => (u.id.toLowerCase() === employeeId.toLowerCase() || u.id === employeeId) && u.pass === password
      );
      if (match) {
        onLogin(match.role);
      } else {
        setError("Connection error to backend. Try janani / password123 or admin / password123");
      }
    } finally {
      setLoading(false);
    }
  };

  const handleKey = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") handleLogin();
  };

  return (
    <div className="relative w-full h-full flex items-center justify-center overflow-hidden">
      {/* Background factory image */}
      <div className="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1717386255767-52643970d483?w=1600&h=900&fit=crop&auto=format"
          alt=""
          className="w-full h-full object-cover"
          style={{ filter: "brightness(0.18) saturate(0.5) hue-rotate(200deg)" }}
        />
        <div
          className="absolute inset-0"
          style={{
            background:
              "radial-gradient(ellipse 80% 80% at 50% 50%, rgba(6,15,30,0.6) 0%, rgba(4,8,16,0.95) 100%)",
          }}
        />
      </div>

      {/* Subtle grid overlay */}
      <div
        className="absolute inset-0 pointer-events-none"
        style={{
          backgroundImage:
            "linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px)",
          backgroundSize: "48px 48px",
        }}
      />

      {/* Login panel */}
      <motion.div
        initial={{ opacity: 0, y: 24, scale: 0.97 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.6, ease: "easeOut" }}
        className="glass-bright glow-blue relative"
        style={{
          width: "min(420px, 92vw)",
          borderRadius: 8,
          padding: "40px 36px 36px",
        }}
      >
        {/* Secure badge */}
        <div
          className="absolute top-4 right-4 flex items-center gap-1.5"
          style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--success)", letterSpacing: "0.12em" }}
        >
          <Shield size={10} />
          SECURE SESSION
        </div>

        {/* Logo area */}
        <div className="text-center mb-8">
          <div
            style={{
              width: 52,
              height: 52,
              borderRadius: 6,
              background: "linear-gradient(135deg, #1e3a5f 0%, #0f2040 100%)",
              border: "1px solid rgba(59,130,246,0.4)",
              margin: "0 auto 16px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            {/* BH logo mark */}
            <svg width="30" height="22" viewBox="0 0 30 22" fill="none">
              <rect x="0" y="0" width="12" height="22" rx="1.5" fill="#3b82f6" opacity="0.9" />
              <rect x="0" y="9" width="12" height="4" rx="1" fill="#93c5fd" />
              <path d="M16 0 L30 0 Q30 11 22 11 Q30 11 30 22 L16 22 Z" fill="#2563eb" opacity="0.9" />
            </svg>
          </div>

          <h1
            style={{
              fontFamily: "var(--font-display)",
              fontWeight: 800,
              fontSize: 26,
              letterSpacing: "0.18em",
              color: "var(--text)",
              lineHeight: 1,
            }}
          >
            BARANI HYDRAULICS
          </h1>
          <p
            style={{
              fontFamily: "var(--font-body)",
              fontSize: 12,
              color: "var(--text-muted)",
              marginTop: 6,
              letterSpacing: "0.04em",
            }}
          >
            Enterprise Digital Portal
          </p>
          <div
            style={{
              width: 60,
              height: 1,
              background: "linear-gradient(90deg, transparent, var(--primary), transparent)",
              margin: "12px auto 0",
            }}
          />
        </div>

        {/* Form */}
        <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
          <div>
            <label
              style={{ fontFamily: "var(--font-mono)", fontSize: 9, letterSpacing: "0.14em", color: "var(--text-dim)", textTransform: "uppercase", display: "block", marginBottom: 5 }}
            >
              Email / Employee ID
            </label>
            <input
              className="field"
              style={{ width: "100%", padding: "10px 12px", fontSize: 13 }}
              placeholder="admin@barani.com or EMP105"
              value={employeeId}
              onChange={(e) => setEmployeeId(e.target.value)}
              onKeyDown={handleKey}
              autoComplete="username"
            />
          </div>

          <div>
            <label
              style={{ fontFamily: "var(--font-mono)", fontSize: 9, letterSpacing: "0.14em", color: "var(--text-dim)", textTransform: "uppercase", display: "block", marginBottom: 5 }}
            >
              Password
            </label>
            <div style={{ position: "relative" }}>
              <input
                className="field"
                type={showPass ? "text" : "password"}
                style={{ width: "100%", padding: "10px 36px 10px 12px", fontSize: 13 }}
                placeholder="Enter password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                onKeyDown={handleKey}
                autoComplete="current-password"
              />
              <button
                type="button"
                onClick={() => setShowPass((s) => !s)}
                style={{
                  position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)",
                  background: "none", border: "none", cursor: "pointer", color: "var(--text-dim)",
                  display: "flex", alignItems: "center",
                }}
              >
                {showPass ? <EyeOff size={14} /> : <Eye size={14} />}
              </button>
            </div>
          </div>

          {/* Remember / forgot */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
            <label style={{ display: "flex", alignItems: "center", gap: 7, cursor: "pointer", fontSize: 12, color: "var(--text-muted)" }}>
              <input
                type="checkbox"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
                style={{ accentColor: "var(--primary-bright)", width: 13, height: 13 }}
              />
              Remember me
            </label>
            <button
              style={{ background: "none", border: "none", cursor: "pointer", fontSize: 12, color: "var(--accent)", letterSpacing: "0.01em" }}
            >
              Forgot password
            </button>
          </div>

          {/* Error */}
          <AnimatePresence>
            {error && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: "auto" }}
                exit={{ opacity: 0, height: 0 }}
                style={{
                  display: "flex", gap: 7, alignItems: "flex-start",
                  padding: "9px 11px",
                  background: "rgba(239,68,68,0.1)",
                  border: "1px solid rgba(239,68,68,0.3)",
                  borderRadius: 4,
                  fontSize: 12, color: "#fca5a5",
                }}
              >
                <AlertCircle size={13} style={{ marginTop: 1, flexShrink: 0 }} />
                {error}
              </motion.div>
            )}
          </AnimatePresence>

          {/* Login button */}
          <motion.button
            onClick={handleLogin}
            disabled={loading}
            whileHover={{ scale: 1.01 }}
            whileTap={{ scale: 0.99 }}
            style={{
              width: "100%",
              padding: "12px",
              background: loading
                ? "rgba(37,99,235,0.4)"
                : "linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%)",
              border: "1px solid rgba(59,130,246,0.5)",
              borderRadius: 4,
              color: "#fff",
              fontFamily: "var(--font-display)",
              fontWeight: 700,
              fontSize: 15,
              letterSpacing: "0.16em",
              cursor: loading ? "default" : "pointer",
              marginTop: 4,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: 10,
              transition: "background 0.2s",
            }}
          >
            {loading ? (
              <>
                <motion.div
                  animate={{ rotate: 360 }}
                  transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }}
                  style={{
                    width: 14, height: 14, border: "2px solid rgba(255,255,255,0.3)",
                    borderTopColor: "#fff", borderRadius: "50%",
                  }}
                />
                AUTHENTICATING…
              </>
            ) : (
              "ACCESS PORTAL"
            )}
          </motion.button>
        </div>

        {/* Footer */}
        <div
          className="text-center mt-5"
          style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-dim)", letterSpacing: "0.1em" }}
        >
          BARANI HYDRAULICS PVT. LTD. &nbsp;·&nbsp; v4.2.1 &nbsp;·&nbsp; CONFIDENTIAL
        </div>
      </motion.div>

      {/* Corner decorations */}
      {["top-4 left-4", "top-4 right-4", "bottom-4 left-4", "bottom-4 right-4"].map((pos, i) => (
        <div
          key={i}
          className={`absolute ${pos}`}
          style={{
            width: 20, height: 20,
            borderTop: i < 2 ? "1px solid rgba(59,130,246,0.25)" : "none",
            borderBottom: i >= 2 ? "1px solid rgba(59,130,246,0.25)" : "none",
            borderLeft: i % 2 === 0 ? "1px solid rgba(59,130,246,0.25)" : "none",
            borderRight: i % 2 === 1 ? "1px solid rgba(59,130,246,0.25)" : "none",
          }}
        />
      ))}
    </div>
  );
}
