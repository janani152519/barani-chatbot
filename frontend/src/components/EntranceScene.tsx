import { useEffect, useRef, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";

interface Props {
  onComplete: () => void;
}

// Factory entrance cinematic — CSS/canvas parallax approach
export default function EntranceScene({ onComplete }: Props) {
  const [phase, setPhase] = useState<"approach" | "gate" | "enter" | "done">("approach");
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const t1 = setTimeout(() => setPhase("gate"), 2800);
    const t2 = setTimeout(() => setPhase("enter"), 5200);
    const t3 = setTimeout(() => setPhase("done"), 7800);
    const t4 = setTimeout(() => onComplete(), 8600);

    // progress bar
    const interval = setInterval(() => {
      setProgress((p) => Math.min(p + 1.2, 100));
    }, 90);

    return () => {
      clearTimeout(t1); clearTimeout(t2); clearTimeout(t3); clearTimeout(t4);
      clearInterval(interval);
    };
  }, [onComplete]);

  return (
    <div className="relative w-full h-full overflow-hidden entrance-bg">
      {/* Sky layer */}
      <motion.div
        className="absolute inset-0"
        style={{
          background: "radial-gradient(ellipse 80% 50% at 50% 0%, #1c1c1c 0%, #121212 100%)",
        }}
        animate={phase === "enter" ? { scale: 1.15, y: -40 } : { scale: 1, y: 0 }}
        transition={{ duration: 2.5, ease: "easeInOut" }}
      />

      {/* Distant factory buildings */}
      <motion.div
        className="absolute bottom-0 w-full"
        style={{ height: "55%" }}
        animate={
          phase === "approach" ? { scale: 1.05, y: 0 } :
          phase === "gate" ? { scale: 1.12, y: -8 } :
          phase === "enter" ? { scale: 1.4, y: -60 } :
          { scale: 1.5, y: -100 }
        }
        transition={{ duration: 2.5, ease: "easeInOut" }}
      >
        <img
          src="https://images.unsplash.com/photo-1717386255767-52643970d483?w=1600&h=900&fit=crop&auto=format"
          alt="Barani Hydraulics Factory"
          className="w-full h-full object-cover object-center"
          style={{ filter: "brightness(0.3) saturate(0.4)" }}
        />
        {/* Color grade overlay */}
        <div
          className="absolute inset-0"
          style={{
            background:
              "linear-gradient(180deg, rgba(6,12,24,0.6) 0%, transparent 30%, rgba(6,12,24,0.8) 90%)",
          }}
        />
      </motion.div>

      {/* Gate structure */}
      <motion.div
        className="absolute bottom-0 left-1/2"
        style={{ transform: "translateX(-50%)", width: "680px", height: "480px" }}
        animate={
          phase === "approach" ? { scale: 0.9, y: 30, opacity: 0.8 } :
          phase === "gate" ? { scale: 1, y: 0, opacity: 1 } :
          phase === "enter" ? { scale: 1.8, y: 60, opacity: 0.4 } :
          { scale: 2.5, y: 120, opacity: 0 }
        }
        transition={{ duration: 2.2, ease: "easeInOut" }}
      >
        {/* Gate posts */}
        {[0, 1].map((i) => (
          <div
            key={i}
            className="absolute bottom-0"
            style={{
              width: "34px",
              height: "280px",
              left: i === 0 ? "120px" : "526px",
              background:
                "linear-gradient(180deg, #1e3a5f 0%, #0f2040 50%, #081628 100%)",
              border: "1px solid rgba(59,130,246,0.3)",
              borderRadius: "2px 2px 0 0",
            }}
          >
            {/* Top accent light */}
            <div
              style={{
                position: "absolute",
                top: -6,
                left: "50%",
                transform: "translateX(-50%)",
                width: 10,
                height: 10,
                borderRadius: "50%",
                background: "#3b82f6",
                boxShadow: "0 0 12px 4px rgba(59,130,246,0.6)",
              }}
            />
            {/* Logo plate on left post */}
            {i === 0 && (
              <div
                style={{
                  position: "absolute",
                  top: 40,
                  left: -60,
                  width: 120,
                  height: 40,
                  background: "rgba(6,15,30,0.9)",
                  border: "1px solid rgba(59,130,246,0.4)",
                  borderRadius: 3,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <span
                  style={{
                    fontFamily: "var(--font-display)",
                    fontWeight: 700,
                    fontSize: 10,
                    letterSpacing: "0.1em",
                    color: "#60a5fa",
                    textAlign: "center",
                    lineHeight: 1.2,
                  }}
                >
                  BARANI<br />HYDRAULICS
                </span>
              </div>
            )}
          </div>
        ))}

        {/* Gate arch */}
        <div
          style={{
            position: "absolute",
            top: 0,
            left: "120px",
            right: "120px",
            height: "50px",
            background: "linear-gradient(180deg, #1e3a5f 0%, #0f2040 100%)",
            border: "1px solid rgba(59,130,246,0.3)",
            borderBottom: "none",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <span
            style={{
              fontFamily: "var(--font-display)",
              fontWeight: 800,
              fontSize: 13,
              letterSpacing: "0.25em",
              color: "#93c5fd",
              textTransform: "uppercase",
            }}
          >
            BARANI HYDRAULICS
          </span>
        </div>

        {/* Barrier gate */}
        <motion.div
          style={{
            position: "absolute",
            bottom: 0,
            left: "154px",
            height: "12px",
            transformOrigin: "left center",
          }}
          animate={phase === "approach" || phase === "gate" ? { width: "370px", rotate: 0 } : { width: "1px", rotate: -80 }}
          transition={{ duration: 1.2, ease: "easeInOut", delay: phase === "gate" ? 0.3 : 0 }}
        >
          <div
            style={{
              width: "100%",
              height: "12px",
              background:
                "repeating-linear-gradient(90deg, #e53e3e 0px, #e53e3e 20px, #f7fafc 20px, #f7fafc 40px)",
              borderRadius: 2,
            }}
          />
        </motion.div>

        {/* Road */}
        <div
          style={{
            position: "absolute",
            bottom: 0,
            left: "155px",
            width: "370px",
            height: "200px",
            background:
              "linear-gradient(180deg, #0f1e35 0%, #0a1525 100%)",
            borderLeft: "1px solid rgba(59,130,246,0.12)",
            borderRight: "1px solid rgba(59,130,246,0.12)",
          }}
        >
          {/* Road markings */}
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              style={{
                position: "absolute",
                left: "50%",
                transform: "translateX(-50%)",
                top: `${i * 55}px`,
                width: 6,
                height: 30,
                background: "rgba(255,255,255,0.15)",
                borderRadius: 2,
              }}
            />
          ))}
        </div>

        {/* Security cabin */}
        <div
          style={{
            position: "absolute",
            bottom: 0,
            right: "76px",
            width: "44px",
            height: "90px",
            background: "linear-gradient(180deg, #1a3050 0%, #0d1e32 100%)",
            border: "1px solid rgba(59,130,246,0.25)",
            borderRadius: "2px 2px 0 0",
          }}
        >
          <div
            style={{
              position: "absolute",
              top: 12,
              left: 6,
              right: 6,
              height: 20,
              background: "rgba(59,130,246,0.15)",
              border: "1px solid rgba(59,130,246,0.3)",
            }}
          />
        </div>
      </motion.div>

      {/* Ground / foreground */}
      <motion.div
        className="absolute bottom-0 w-full"
        style={{ height: "22%" }}
        animate={
          phase === "enter" ? { y: 80, opacity: 0 } :
          { y: 0, opacity: 1 }
        }
        transition={{ duration: 2, ease: "easeIn" }}
      >
        <div
          style={{
            width: "100%",
            height: "100%",
            background:
              "linear-gradient(180deg, #0a1525 0%, #060c18 100%)",
          }}
        />
      </motion.div>

      {/* Vignette */}
      <div
        className="absolute inset-0 pointer-events-none"
        style={{
          background:
            "radial-gradient(ellipse 90% 90% at 50% 50%, transparent 40%, rgba(4,8,16,0.8) 100%)",
        }}
      />

      {/* Top caption */}
      <AnimatePresence mode="wait">
        {phase === "approach" && (
          <motion.div
            key="approach"
            className="absolute top-10 left-1/2"
            style={{ transform: "translateX(-50%)" }}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
          >
            <p style={{ fontFamily: "var(--font-mono)", fontSize: 11, letterSpacing: "0.2em", color: "var(--text-muted)", textTransform: "uppercase" }}>
              Approaching facility…
            </p>
          </motion.div>
        )}
        {phase === "gate" && (
          <motion.div
            key="gate"
            className="absolute top-10 left-1/2"
            style={{ transform: "translateX(-50%)" }}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
          >
            <p style={{ fontFamily: "var(--font-mono)", fontSize: 11, letterSpacing: "0.2em", color: "#10b981", textTransform: "uppercase" }}>
              Gate access granted
            </p>
          </motion.div>
        )}
        {phase === "enter" && (
          <motion.div
            key="enter"
            className="absolute top-10 left-1/2"
            style={{ transform: "translateX(-50%)" }}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
          >
            <p style={{ fontFamily: "var(--font-mono)", fontSize: 11, letterSpacing: "0.2em", color: "var(--accent)", textTransform: "uppercase" }}>
              Entering Barani Hydraulics
            </p>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Center logo */}
      <motion.div
        className="absolute top-1/2 left-1/2 text-center"
        style={{ transform: "translate(-50%, -50%)", pointerEvents: "none" }}
        animate={
          phase === "enter" || phase === "done"
            ? { opacity: 0, scale: 0.9, y: -20 }
            : { opacity: 1, scale: 1, y: 0 }
        }
        transition={{ duration: 1 }}
      >
        <div
          style={{
            fontFamily: "var(--font-display)",
            fontWeight: 800,
            fontSize: 48,
            letterSpacing: "0.18em",
            color: "#e2e8f0",
            lineHeight: 0.95,
            textShadow: "0 0 40px rgba(59,130,246,0.3)",
          }}
        >
          BARANI
        </div>
        <div
          style={{
            fontFamily: "var(--font-display)",
            fontWeight: 400,
            fontSize: 21,
            letterSpacing: "0.35em",
            color: "var(--steel)",
            marginTop: 4,
          }}
        >
          HYDRAULICS
        </div>
        <div
          style={{
            width: 80,
            height: 1,
            background: "linear-gradient(90deg, transparent, var(--primary-bright), transparent)",
            margin: "12px auto",
          }}
        />
        <div
          style={{
            fontFamily: "var(--font-mono)",
            fontSize: 9,
            letterSpacing: "0.22em",
            color: "var(--text-dim)",
            textTransform: "uppercase",
          }}
        >
          Enterprise Portal
        </div>
      </motion.div>

      {/* Flash / wipe on done */}
      <AnimatePresence>
        {phase === "done" && (
          <motion.div
            className="absolute inset-0"
            style={{ background: "#060c18" }}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.7 }}
          />
        )}
      </AnimatePresence>

      {/* Progress bar */}
      <div
        style={{
          position: "absolute",
          bottom: 32,
          left: "50%",
          transform: "translateX(-50%)",
          width: 240,
          zIndex: 40,
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 6
        }}
      >
        <div
          style={{
            width: "100%",
            height: 4,
            background: "rgba(255, 255, 255, 0.15)",
            borderRadius: 2,
            overflow: "hidden"
          }}
        >
          <div
            style={{
              width: `${progress}%`,
              height: "100%",
              background: "#10a37f",
              boxShadow: "0 0 10px rgba(16, 163, 127, 0.8)",
              transition: "width 0.1s linear"
            }}
          />
        </div>
        <span
          style={{
            fontFamily: "var(--font-mono)",
            fontSize: 9,
            letterSpacing: "0.15em",
            color: "rgba(255, 255, 255, 0.5)",
            textTransform: "uppercase"
          }}
        >
          {phase === "approach" && "Scanning security clearance…"}
          {phase === "gate" && "Perimeter gate opening…"}
          {phase === "enter" && "Authorizing SCADA gateway…"}
          {phase === "done" && "Ready for login"}
        </span>
      </div>

      {/* Prominent Skip Intro Button */}
      <button
        type="button"
        onClick={onComplete}
        style={{
          position: "absolute",
          bottom: 24,
          right: 32,
          display: "flex",
          alignItems: "center",
          gap: 8,
          padding: "9px 18px",
          background: "rgba(33, 33, 33, 0.85)",
          border: "1px solid rgba(255, 255, 255, 0.25)",
          backdropFilter: "blur(12px)",
          borderRadius: 24,
          color: "#ffffff",
          fontFamily: "var(--font-mono)",
          fontSize: 11,
          fontWeight: 600,
          letterSpacing: "0.1em",
          cursor: "pointer",
          zIndex: 50,
          boxShadow: "0 4px 16px rgba(0,0,0,0.5)",
          transition: "all 0.2s ease",
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.background = "#10a37f";
          e.currentTarget.style.borderColor = "#10a37f";
          e.currentTarget.style.transform = "translateY(-1px)";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.background = "rgba(33, 33, 33, 0.85)";
          e.currentTarget.style.borderColor = "rgba(255, 255, 255, 0.25)";
          e.currentTarget.style.transform = "translateY(0)";
        }}
      >
        <span>SKIP INTRO</span>
        <span style={{ fontSize: 13 }}>⏭</span>
      </button>
    </div>
  );
}
