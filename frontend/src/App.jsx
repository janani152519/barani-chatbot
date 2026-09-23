import React, { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import EntranceScene from "./components/EntranceScene";
import LoginScreen from "./components/LoginScreen";
import ChatWindow from "./components/ChatWindow";
import { getAuthUser, logout } from "./services/api";

export default function App() {
  const [user, setUser] = useState(() => getAuthUser());
  const [state, setState] = useState("entrance");

  const handleEntranceDone = () => {
    setState("login");
  };

  const handleLogin = (authenticatedUser) => {
    setUser(authenticatedUser);
    setState("entering");
    setTimeout(() => setState("chat"), 1500);
  };

  const handleLogout = async () => {
    await logout();
    setUser(null);
    setState("login");
  };

  return (
    <div style={{ width: "100vw", height: "100vh", overflow: "hidden", background: state === "chat" ? "#212121" : "#171717", transition: "background 0.5s ease" }}>
      <AnimatePresence mode="wait">
        {state === "entrance" && (
          <motion.div
            key="entrance"
            style={{ position: "absolute", inset: 0 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.5 }}
          >
            <EntranceScene onComplete={handleEntranceDone} />
          </motion.div>
        )}

        {state === "login" && (
          <motion.div
            key="login"
            style={{ position: "absolute", inset: 0 }}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.5 }}
          >
            <LoginScreen onLogin={handleLogin} />
          </motion.div>
        )}

        {state === "entering" && (
          <motion.div
            key="entering"
            style={{
              position: "absolute", inset: 0, background: "#171717",
              display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 24
            }}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.4 }}
          >
            <img
              src="https://images.unsplash.com/photo-1717386255767-52643970d483?w=1400&h=900&fit=crop&auto=format"
              alt=""
              style={{
                position: "absolute", inset: 0, width: "100%", height: "100%",
                objectFit: "cover",
                filter: "brightness(0.15) saturate(0.4) hue-rotate(200deg)"
              }}
            />
            <div style={{ position: "absolute", inset: 0, background: "radial-gradient(ellipse 80% 80% at 50% 50%, rgba(6,12,24,0.5) 0%, rgba(4,8,16,0.92) 100%)" }} />

            <motion.div
              style={{ position: "relative", textAlign: "center" }}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
            >
              <div
                style={{
                  fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 36,
                  letterSpacing: "0.2em", color: "var(--text)", lineHeight: 1,
                  textShadow: "0 0 40px rgba(59,130,246,0.3)"
                }}
              >
                WELCOME
              </div>
              <div
                style={{
                  fontFamily: "var(--font-display)", fontWeight: 400, fontSize: 14,
                  letterSpacing: "0.3em", color: "var(--steel)", marginTop: 6
                }}
              >
                BARANI HYDRAULICS ENTERPRISE PORTAL
              </div>
            </motion.div>

            <motion.div
              style={{ position: "relative", display: "flex", flexDirection: "column", alignItems: "center", gap: 10 }}
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.6 }}
            >
              <div style={{ display: "flex", gap: 6 }}>
                {[0, 1, 2].map((i) => (
                  <motion.div
                    key={i}
                    style={{ width: 6, height: 6, borderRadius: "50%", background: "var(--primary-bright)" }}
                    animate={{ opacity: [0.2, 1, 0.2] }}
                    transition={{ duration: 1, repeat: Infinity, delay: i * 0.25 }}
                  />
                ))}
              </div>
              <p style={{ fontFamily: "var(--font-mono)", fontSize: 10, letterSpacing: "0.14em", color: "var(--text-dim)", textTransform: "uppercase" }}>
                Connecting to AI Database Assistant…
              </p>
            </motion.div>

            <motion.div
              style={{
                position: "absolute", left: 0, right: 0, height: 2,
                background: "linear-gradient(90deg, transparent 0%, rgba(59,130,246,0.6) 50%, transparent 100%)"
              }}
              animate={{ top: ["0%", "100%"] }}
              transition={{ duration: 2.5, ease: "linear", repeat: Infinity }}
            />
          </motion.div>
        )}

        {state === "chat" && (
          <motion.div
            key="chat"
            style={{ position: "absolute", inset: 0 }}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.6 }}
          >
            <ChatWindow onLogout={handleLogout} />
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
