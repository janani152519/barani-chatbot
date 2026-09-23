import React, { useState, useRef, useEffect } from "react";
import { Send, Sparkles, Mic, MicOff, Zap, Layers, RefreshCw } from "lucide-react";

const CATEGORIES = [
  { id: "all", label: "🌟 All Benchmarks" },
  { id: "production", label: "⚙️ Production" },
  { id: "maintenance", label: "🔧 Maintenance" },
  { id: "quality", label: "🧪 Quality" },
  { id: "executive", label: "💼 Executive" },
  { id: "digest", label: "📋 Shift Digest" }
];

const PROMPT_CHIPS = [
  // Benchmark Pareto query
  { text: "Which machine had the highest downtime last month?", cat: "maintenance", icon: "📊", highlight: true },
  // Multi-Turn pair
  { text: "Show ML-06 production output this month.", cat: "production", icon: "⚙️" },
  { text: "Now compare it with last month.", cat: "production", icon: "🔄" },
  // Production
  { text: "What was yesterday's actual production across Line 3?", cat: "production", icon: "🏭" },
  { text: "Which press machine exceeded the hourly quota?", cat: "production", icon: "🚀" },
  { text: "Compare Line A and Line B overall capacity utilization.", cat: "production", icon: "⚖️" },
  // Maintenance
  { text: "Which CNC units broke down most frequently in Q3?", cat: "maintenance", icon: "🔧" },
  { text: "Calculate Mean Time to Repair (MTTR) by asset.", cat: "maintenance", icon: "⏱️" },
  { text: "Show 30-day lubrication schedule non-compliance.", cat: "maintenance", icon: "⚠️" },
  // Quality
  { text: "Which part family suffered the highest scrap rate?", cat: "quality", icon: "🧪" },
  { text: "Show defect rate trend after shift change at 14:00.", cat: "quality", icon: "📈" },
  { text: "List batch numbers with tolerance variance > 1.5%.", cat: "quality", icon: "📐" },
  // Executive
  { text: "Generate executive summary of daily factory OEE.", cat: "executive", icon: "💼" },
  { text: "What were the top 3 production loss drivers this week?", cat: "executive", icon: "📉" },
  { text: "Display kilowatt-hour electrical cost per finished ton.", cat: "executive", icon: "⚡" },
  // Shift Digest
  { text: "Generate shift digest", cat: "digest", icon: "📋" }
];

export default function ChatInput({ onSendMessage, disabled }) {
  const [text, setText] = useState("");
  const [isFocused, setIsFocused] = useState(false);
  const [activeCategory, setActiveCategory] = useState("all");
  const [isListening, setIsListening] = useState(false);
  const [voiceSupported, setVoiceSupported] = useState(false);

  const textareaRef = useRef(null);
  const recognitionRef = useRef(null);

  // Auto-grow textarea
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
      textareaRef.current.style.height = Math.min(textareaRef.current.scrollHeight, 120) + "px";
    }
  }, [text]);

  // Setup Web Speech API speech recognition
  useEffect(() => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      setVoiceSupported(true);
      const recog = new SpeechRecognition();
      recog.continuous = false;
      recog.interimResults = true;
      recog.lang = "en-US";

      recog.onresult = (event) => {
        const transcript = Array.from(event.results)
          .map(r => r[0].transcript)
          .join("");
        setText(transcript);
      };

      recog.onend = () => {
        setIsListening(false);
      };

      recog.onerror = (e) => {
        console.error("Speech Recognition Error:", e.error);
        setIsListening(false);
      };

      recognitionRef.current = recog;
    }
  }, []);

  const toggleVoice = () => {
    if (!voiceSupported || disabled) return;
    if (isListening) {
      recognitionRef.current?.stop();
      setIsListening(false);
    } else {
      try {
        recognitionRef.current?.start();
        setIsListening(true);
      } catch (err) {
        console.error("Voice start failed:", err);
      }
    }
  };

  const handleSubmit = (e) => {
    e?.preventDefault();
    if (!text.trim() || disabled) return;
    onSendMessage(text.trim());
    setText("");
    if (textareaRef.current) textareaRef.current.style.height = "auto";
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  };

  const handleSuggestionClick = (query) => {
    if (disabled) return;
    onSendMessage(query);
  };

  const filteredChips = activeCategory === "all"
    ? PROMPT_CHIPS
    : PROMPT_CHIPS.filter(c => c.cat === activeCategory);

  const canSend = text.trim() && !disabled;

  return (
    <div
      style={{
        padding: "12px 20px 16px",
        background: "rgba(7, 15, 28, 0.98)",
        borderTop: "1px solid rgba(0, 212, 255, 0.15)",
        display: "flex",
        flexDirection: "column",
        gap: 10,
        backdropFilter: "blur(20px)",
      }}
    >
      {/* Category selector row */}
      <div style={{ display: "flex", alignItems: "center", gap: 6, overflowX: "auto", scrollbarWidth: "none" }}>
        {CATEGORIES.map(cat => (
          <button
            key={cat.id}
            onClick={() => setActiveCategory(cat.id)}
            style={{
              padding: "4px 10px",
              borderRadius: 16,
              fontSize: 10.5,
              fontWeight: 600,
              cursor: "pointer",
              whiteSpace: "nowrap",
              background: activeCategory === cat.id ? "rgba(59, 130, 246, 0.25)" : "rgba(255, 255, 255, 0.04)",
              border: `1px solid ${activeCategory === cat.id ? "#38BDF8" : "rgba(255, 255, 255, 0.08)"}`,
              color: activeCategory === cat.id ? "#38BDF8" : "#94A3B8",
              transition: "all 0.15s ease"
            }}
          >
            {cat.label}
          </button>
        ))}
      </div>

      {/* Suggestion prompt chips */}
      <div
        style={{
          display: "flex",
          gap: 7,
          overflowX: "auto",
          paddingBottom: 2,
          scrollbarWidth: "none",
        }}
      >
        {filteredChips.map((s, idx) => (
          <button
            key={idx}
            onClick={() => handleSuggestionClick(s.text)}
            disabled={disabled}
            title={s.text}
            style={{
              whiteSpace: "nowrap",
              padding: "5px 12px",
              background: s.highlight ? "rgba(239, 68, 68, 0.12)" : "rgba(13, 26, 46, 0.9)",
              border: `1px solid ${s.highlight ? "rgba(239, 68, 68, 0.4)" : "rgba(0, 212, 255, 0.2)"}`,
              borderRadius: 20,
              color: s.highlight ? "#FCA5A5" : "#CBD5E1",
              fontSize: 11,
              cursor: disabled ? "default" : "pointer",
              transition: "all 0.2s",
              display: "flex",
              alignItems: "center",
              gap: 5,
              fontFamily: "Inter, sans-serif",
            }}
            onMouseOver={(e) => {
              if (!disabled) {
                e.currentTarget.style.borderColor = s.highlight ? "#EF4444" : "#00d4ff";
                e.currentTarget.style.color = "#FFFFFF";
                e.currentTarget.style.background = s.highlight ? "rgba(239, 68, 68, 0.2)" : "rgba(0, 212, 255, 0.1)";
                e.currentTarget.style.boxShadow = "0 0 12px rgba(0,212,255,0.2)";
              }
            }}
            onMouseOut={(e) => {
              e.currentTarget.style.borderColor = s.highlight ? "rgba(239, 68, 68, 0.4)" : "rgba(0, 212, 255, 0.2)";
              e.currentTarget.style.color = s.highlight ? "#FCA5A5" : "#CBD5E1";
              e.currentTarget.style.background = s.highlight ? "rgba(239, 68, 68, 0.12)" : "rgba(13, 26, 46, 0.9)";
              e.currentTarget.style.boxShadow = "none";
            }}
          >
            <span style={{ fontSize: 12 }}>{s.icon}</span>
            <span>{s.text.length > 36 ? s.text.slice(0, 36) + "…" : s.text}</span>
          </button>
        ))}
      </div>

      {/* Input row */}
      <form onSubmit={handleSubmit} style={{ display: "flex", gap: 8, alignItems: "flex-end" }}>
        {/* Voice Microphone Button (Hands-free Plant Floor Interface) */}
        {voiceSupported && (
          <button
            type="button"
            onClick={toggleVoice}
            disabled={disabled}
            title={isListening ? "Listening... Click to stop" : "Voice Input (Plant Floor Hands-Free Microphone)"}
            style={{
              width: 44,
              height: 44,
              borderRadius: 12,
              background: isListening ? "rgba(239, 68, 68, 0.25)" : "rgba(15, 23, 42, 0.8)",
              border: `1px solid ${isListening ? "#EF4444" : "rgba(59, 130, 246, 0.3)"}`,
              color: isListening ? "#EF4444" : "#38BDF8",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              cursor: disabled ? "default" : "pointer",
              boxShadow: isListening ? "0 0 16px rgba(239, 68, 68, 0.5)" : "none",
              animation: isListening ? "pulse 1.5s infinite" : "none",
              transition: "all 0.2s"
            }}
          >
            {isListening ? <MicOff size={18} /> : <Mic size={18} />}
          </button>
        )}

        {/* Glowing input wrapper */}
        <div
          style={{
            flex: 1,
            position: "relative",
            borderRadius: 12,
            transition: "all 0.3s",
            boxShadow: isFocused
              ? "0 0 0 2px rgba(0,212,255,0.25), 0 0 30px rgba(0,212,255,0.1)"
              : "none",
          }}
        >
          <textarea
            ref={textareaRef}
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={handleKeyDown}
            onFocus={() => setIsFocused(true)}
            onBlur={() => setIsFocused(false)}
            placeholder={isListening ? "Listening to plant floor voice input..." : "Ask natural language question or select telemetry benchmark... (Enter to send)"}
            rows={1}
            disabled={disabled}
            style={{
              width: "100%",
              padding: "12px 48px 12px 16px",
              background: isFocused ? "rgba(5, 15, 35, 0.98)" : "rgba(9, 18, 32, 0.95)",
              border: `1px solid ${isFocused ? "rgba(0,212,255,0.4)" : "rgba(0,212,255,0.15)"}`,
              borderRadius: 12,
              color: "#e8f0fe",
              fontSize: 13.5,
              outline: "none",
              resize: "none",
              lineHeight: 1.5,
              fontFamily: "Inter, sans-serif",
              transition: "all 0.25s",
              overflowY: "hidden",
            }}
          />

          {/* Sparkle icon inside input */}
          <Zap
            size={14}
            style={{
              position: "absolute",
              right: 14,
              top: "50%",
              transform: "translateY(-50%)",
              color: isFocused ? "#00d4ff" : "#334155",
              transition: "color 0.25s",
            }}
          />
        </div>

        {/* Send button */}
        <button
          type="submit"
          disabled={!canSend}
          className="send-btn"
          title="Send query to AI SQL Data Analyst Agent"
        >
          <Send
            size={16}
            style={{
              color: canSend ? "#ffffff" : "#475569",
              transition: "color 0.2s",
            }}
          />
        </button>
      </form>

      {/* Footer hint */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", opacity: 0.6 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
          <Sparkles size={10} style={{ color: "#00d4ff" }} />
          <span style={{ fontSize: 10, fontFamily: "JetBrains Mono, monospace", color: "#64748B", letterSpacing: "0.06em" }}>
            AI SQL DATA ANALYST AGENT &bull; AST READ-ONLY ENFORCED &bull; {disabled ? "PROCESSING QUERY…" : "READY"}
          </span>
          {disabled && (
            <span style={{ display: "flex", gap: 4, marginLeft: 4 }}>
              {[0,1,2].map(i => (
                <span key={i} className="typing-dot" style={{ width: 4, height: 4, animationDelay: `${i * 0.2}s` }} />
              ))}
            </span>
          )}
        </div>
        <div style={{ fontSize: 9.5, color: "#64748B", fontFamily: "var(--font-mono)" }}>
          Target: Shop Floor & Operational Analytics v1.2
        </div>
      </div>
    </div>
  );
}
