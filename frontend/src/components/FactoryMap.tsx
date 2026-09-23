import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { X, Users, Cog, Activity, ChevronRight } from "lucide-react";

const ZONES = [
  {
    id: "production",
    label: "Production Area",
    x: "28%", y: "38%",
    color: "#3b82f6",
    machines: ["Hydraulic Press HP-500", "Cylinder Assembly Unit", "Pipe Bending Station"],
    status: "Operational",
    employees: 48,
    output: "328 units today",
    desc: "Primary hydraulic component manufacturing floor",
  },
  {
    id: "cnc",
    label: "CNC / Machine Area",
    x: "55%", y: "30%",
    color: "#10b981",
    machines: ["CNC Lathe CL-12", "Milling Machine MM-08", "Precision Borer PB-3"],
    status: "Operational",
    employees: 22,
    output: "142 components today",
    desc: "High-precision CNC machining division",
  },
  {
    id: "assembly",
    label: "Assembly Unit",
    x: "72%", y: "52%",
    color: "#8b5cf6",
    machines: ["Assembly Bench A1–A6", "Hydraulic Cylinder Test Rig", "Leak Test Station"],
    status: "Operational",
    employees: 31,
    output: "89 assemblies today",
    desc: "Final assembly and sub-assembly for hydraulic systems",
  },
  {
    id: "quality",
    label: "Quality Control",
    x: "44%", y: "58%",
    color: "#f59e0b",
    machines: ["CMM Machine", "Pressure Test Bench", "NDT Station"],
    status: "Active",
    employees: 14,
    output: "97% pass rate",
    desc: "Inspection, testing and quality assurance",
  },
  {
    id: "warehouse",
    label: "Warehouse",
    x: "18%", y: "62%",
    color: "#06b6d4",
    machines: ["Overhead Crane 5T", "Forklift Unit ×3", "Automated Racking"],
    status: "Operational",
    employees: 19,
    output: "₹4.2 Cr stock value",
    desc: "Raw materials and finished goods storage",
  },
  {
    id: "maintenance",
    label: "Maintenance",
    x: "62%", y: "72%",
    color: "#f97316",
    machines: ["Welding Station WS-4", "Grinder & Surface Station", "Calibration Lab"],
    status: "On Call",
    employees: 11,
    output: "3 jobs in progress",
    desc: "Facility and machine maintenance division",
  },
  {
    id: "admin",
    label: "Administration",
    x: "38%", y: "20%",
    color: "#64748b",
    machines: ["IT Server Room", "CCTV Control", "Access Control"],
    status: "Operational",
    employees: 34,
    output: "—",
    desc: "HR, Finance, IT and management offices",
  },
  {
    id: "dispatch",
    label: "Dispatch",
    x: "82%", y: "36%",
    color: "#ec4899",
    machines: ["Loading Bay ×4", "Weighbridge", "Documentation Counter"],
    status: "Active",
    employees: 8,
    output: "12 shipments today",
    desc: "Outbound logistics and dispatch operations",
  },
];

export default function FactoryMap() {
  const [selected, setSelected] = useState<(typeof ZONES)[0] | null>(null);

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", overflow: "hidden" }}>
      {/* Header */}
      <div
        style={{
          padding: "16px 24px",
          borderBottom: "1px solid var(--border)",
          display: "flex", alignItems: "center", justifyContent: "space-between",
          background: "var(--surface)", flexShrink: 0,
        }}
      >
        <div>
          <h2 style={{ fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 22, letterSpacing: "0.1em", color: "var(--text)", lineHeight: 1 }}>
            FACTORY MAP — INTERACTIVE
          </h2>
          <p style={{ fontSize: 12, color: "var(--text-muted)", marginTop: 3 }}>
            Barani Hydraulics Pvt. Ltd. &nbsp;·&nbsp; Click any zone for details
          </p>
        </div>
        <div style={{ display: "flex", gap: 16 }}>
          {[
            { label: "Operational", color: "#10b981" },
            { label: "Maintenance", color: "#f59e0b" },
            { label: "Offline", color: "#ef4444" },
          ].map((l) => (
            <div key={l.label} style={{ display: "flex", alignItems: "center", gap: 5 }}>
              <div style={{ width: 7, height: 7, borderRadius: "50%", background: l.color }} />
              <span style={{ fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-muted)", letterSpacing: "0.08em" }}>{l.label}</span>
            </div>
          ))}
        </div>
      </div>

      <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
        {/* Map canvas */}
        <div style={{ flex: 1, position: "relative", overflow: "hidden" }}>
          {/* Factory floor background */}
          <div
            style={{
              position: "absolute", inset: 0,
              background: "var(--surface2)",
              backgroundImage: `
                linear-gradient(rgba(59,130,246,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,0.04) 1px, transparent 1px)
              `,
              backgroundSize: "40px 40px",
            }}
          />

          {/* Factory boundary */}
          <div
            style={{
              position: "absolute",
              top: "10%", left: "8%", right: "8%", bottom: "10%",
              border: "1px solid rgba(59,130,246,0.2)",
              borderRadius: 8,
            }}
          >
            {/* Factory image as base */}
            <img
              src="https://images.unsplash.com/photo-1717386255767-52643970d483?w=1200&h=700&fit=crop&auto=format"
              alt="Factory floor"
              style={{
                width: "100%", height: "100%", objectFit: "cover",
                filter: "brightness(0.12) saturate(0.3) hue-rotate(210deg)",
                borderRadius: 7,
              }}
            />
            {/* Grid overlay */}
            <div
              style={{
                position: "absolute", inset: 0,
                backgroundImage: `
                  linear-gradient(rgba(59,130,246,0.06) 1px, transparent 1px),
                  linear-gradient(90deg, rgba(59,130,246,0.06) 1px, transparent 1px)
                `,
                backgroundSize: "80px 80px",
                borderRadius: 7,
              }}
            />
          </div>

          {/* Zone labels (background building silhouettes) */}
          {ZONES.map((zone) => (
            <div
              key={zone.id}
              style={{
                position: "absolute",
                left: zone.x, top: zone.y,
                transform: "translate(-50%, -50%)",
                width: 90, height: 60,
                background: `${zone.color}08`,
                border: `1px solid ${zone.color}20`,
                borderRadius: 3,
              }}
            />
          ))}

          {/* Hotspots */}
          {ZONES.map((zone) => (
            <button
              key={zone.id}
              onClick={() => setSelected(zone)}
              style={{
                position: "absolute",
                left: zone.x, top: zone.y,
                transform: "translate(-50%, -50%)",
                background: "none", border: "none", cursor: "pointer",
                display: "flex", flexDirection: "column", alignItems: "center", gap: 6,
              }}
            >
              {/* Pulsing dot */}
              <div style={{ position: "relative", width: 14, height: 14 }}>
                <div
                  className="map-hotspot"
                  style={{
                    background: zone.color,
                    border: `2px solid ${zone.color}60`,
                    position: "relative",
                  }}
                />
                <div
                  style={{
                    position: "absolute", inset: -6, borderRadius: "50%",
                    border: `1px solid ${zone.color}50`,
                    animation: "ripple 2s infinite",
                  }}
                />
              </div>
              {/* Label */}
              <div
                style={{
                  background: "rgba(6,12,24,0.85)",
                  border: `1px solid ${zone.color}40`,
                  borderRadius: 3, padding: "3px 7px", whiteSpace: "nowrap",
                  fontFamily: "var(--font-mono)", fontSize: 9,
                  letterSpacing: "0.08em", color: zone.color,
                  backdropFilter: "blur(8px)",
                }}
              >
                {zone.label}
              </div>
            </button>
          ))}

          {/* Compass */}
          <div
            style={{
              position: "absolute", bottom: 24, right: 24,
              width: 44, height: 44,
              background: "rgba(6,12,24,0.8)", border: "1px solid var(--border)",
              borderRadius: "50%", display: "flex", alignItems: "center",
              justifyContent: "center",
              fontFamily: "var(--font-mono)", fontSize: 10, color: "var(--text-dim)",
            }}
          >
            N ↑
          </div>
        </div>

        {/* Detail panel */}
        <AnimatePresence>
          {selected && (
            <motion.div
              initial={{ x: 300, opacity: 0 }}
              animate={{ x: 0, opacity: 1 }}
              exit={{ x: 300, opacity: 0 }}
              transition={{ duration: 0.25, ease: "easeOut" }}
              style={{
                width: 300,
                background: "var(--surface)",
                borderLeft: "1px solid var(--border)",
                display: "flex", flexDirection: "column",
                overflow: "hidden", flexShrink: 0,
              }}
            >
              {/* Panel header */}
              <div
                style={{
                  padding: "16px",
                  borderBottom: "1px solid var(--border)",
                  background: `linear-gradient(135deg, ${selected.color}12 0%, transparent 100%)`,
                  display: "flex", justifyContent: "space-between", alignItems: "flex-start",
                }}
              >
                <div>
                  <div
                    style={{
                      fontFamily: "var(--font-display)", fontWeight: 800,
                      fontSize: 16, letterSpacing: "0.08em", color: "var(--text)", lineHeight: 1,
                    }}
                  >
                    {selected.label.toUpperCase()}
                  </div>
                  <p style={{ fontSize: 11, color: "var(--text-muted)", marginTop: 4 }}>{selected.desc}</p>
                </div>
                <button
                  onClick={() => setSelected(null)}
                  style={{ background: "none", border: "none", cursor: "pointer", color: "var(--text-dim)", padding: 2 }}
                >
                  <X size={14} />
                </button>
              </div>

              <div style={{ flex: 1, overflow: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 14 }}>
                {/* Status */}
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
                  <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 4, padding: "10px 12px" }}>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.12em", marginBottom: 4 }}>STATUS</div>
                    <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
                      <span className="status-dot green" />
                      <span style={{ fontSize: 12, fontWeight: 600, color: "var(--success)" }}>{selected.status}</span>
                    </div>
                  </div>
                  <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 4, padding: "10px 12px" }}>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.12em", marginBottom: 4 }}>EMPLOYEES</div>
                    <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
                      <Users size={12} style={{ color: "var(--accent)" }} />
                      <span style={{ fontSize: 14, fontWeight: 700, color: "var(--text)", fontFamily: "var(--font-display)" }}>{selected.employees}</span>
                    </div>
                  </div>
                </div>

                {/* Output */}
                <div style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 4, padding: "10px 12px" }}>
                  <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.12em", marginBottom: 4 }}>TODAY'S OUTPUT</div>
                  <div style={{ display: "flex", alignItems: "center", gap: 5 }}>
                    <Activity size={12} style={{ color: selected.color }} />
                    <span style={{ fontSize: 13, fontWeight: 600, color: "var(--text)" }}>{selected.output}</span>
                  </div>
                </div>

                {/* Machines */}
                <div>
                  <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.12em", marginBottom: 8 }}>EQUIPMENT</div>
                  <div style={{ display: "flex", flexDirection: "column", gap: 5 }}>
                    {selected.machines.map((m, i) => (
                      <div
                        key={i}
                        style={{
                          display: "flex", alignItems: "center", gap: 8,
                          padding: "7px 10px",
                          background: "var(--card)", border: "1px solid var(--border)", borderRadius: 3,
                        }}
                      >
                        <Cog size={10} style={{ color: selected.color, flexShrink: 0 }} />
                        <span style={{ fontSize: 11, color: "var(--text-muted)" }}>{m}</span>
                        <ChevronRight size={9} style={{ marginLeft: "auto", color: "var(--text-dim)" }} />
                      </div>
                    ))}
                  </div>
                </div>

                {/* Color accent bar */}
                <div
                  style={{
                    height: 3, borderRadius: 2,
                    background: `linear-gradient(90deg, ${selected.color} 0%, transparent 100%)`,
                    marginTop: "auto",
                  }}
                />
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
