import { useState } from "react";
import { motion } from "framer-motion";
import { MapPin, Phone, Mail, Globe, Navigation, Building2, Clock, ExternalLink } from "lucide-react";

const LOCATION = {
  name: "Barani Hydraulics Pvt. Ltd.",
  address: "SIPCOT Industrial Area, Hosur, Tamil Nadu 635 109, India",
  phone: "+91-4344-XXX-XXX",
  email: "contact@baranihydraulics.com",
  website: "www.baranihydraulics.com",
  lat: 12.7264,
  lng: 77.8391,
  hours: "Mon–Sat: 08:00 – 18:00",
  established: "1994",
  area: "4.2 acres",
};

const NEARBY = [
  { name: "SIPCOT Phase II", dist: "0.4 km", type: "Industrial Zone" },
  { name: "Hosur TNEB Office", dist: "1.2 km", type: "Utility" },
  { name: "Hosur Railway Station", dist: "3.8 km", type: "Transport" },
  { name: "State Highway 17", dist: "0.6 km", type: "Road" },
];

export default function LocationView() {
  const [mapView, setMapView] = useState<"satellite" | "map">("satellite");

  return (
    <div style={{ height: "100%", display: "flex", flexDirection: "column", overflow: "hidden" }}>
      {/* Header */}
      <div
        style={{
          padding: "16px 24px",
          borderBottom: "1px solid var(--border)",
          background: "var(--surface)",
          display: "flex", alignItems: "center", justifyContent: "space-between",
          flexShrink: 0,
        }}
      >
        <div>
          <h2 style={{ fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 22, letterSpacing: "0.1em", color: "var(--text)", lineHeight: 1 }}>
            BARANI HYDRAULICS — LOCATION
          </h2>
          <p style={{ fontSize: 12, color: "var(--text-muted)", marginTop: 3 }}>
            SIPCOT Industrial Area, Hosur, Tamil Nadu
          </p>
        </div>
        {/* View toggle */}
        <div
          style={{
            display: "flex",
            background: "var(--card)", border: "1px solid var(--border)", borderRadius: 4, padding: 2,
          }}
        >
          {(["satellite", "map"] as const).map((v) => (
            <button
              key={v}
              onClick={() => setMapView(v)}
              style={{
                padding: "5px 14px", borderRadius: 3, border: "none", cursor: "pointer",
                fontFamily: "var(--font-mono)", fontSize: 9, letterSpacing: "0.1em", textTransform: "uppercase",
                background: mapView === v ? "var(--primary)" : "transparent",
                color: mapView === v ? "#fff" : "var(--text-dim)",
                transition: "background 0.15s",
              }}
            >
              {v}
            </button>
          ))}
        </div>
      </div>

      <div style={{ flex: 1, display: "flex", overflow: "hidden" }}>
        {/* Map */}
        <div style={{ flex: 1, position: "relative", overflow: "hidden" }}>
          {mapView === "satellite" ? (
            <>
              {/* Satellite view — use aerial factory image */}
              <img
                src="https://images.unsplash.com/photo-1717386255773-1e3037c81788?w=1200&h=800&fit=crop&auto=format"
                alt="Satellite view of Barani Hydraulics facility"
                style={{
                  width: "100%", height: "100%", objectFit: "cover",
                  filter: "brightness(0.5) saturate(0.4) hue-rotate(200deg)",
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
                  backgroundSize: "60px 60px",
                }}
              />
              {/* Coordinates overlay */}
              <div
                style={{
                  position: "absolute", bottom: 12, left: 12,
                  background: "rgba(6,12,24,0.85)",
                  border: "1px solid var(--border)", borderRadius: 3,
                  padding: "5px 10px",
                  fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-muted)",
                  letterSpacing: "0.06em",
                }}
              >
                {LOCATION.lat}°N &nbsp; {LOCATION.lng}°E &nbsp; | &nbsp; SATELLITE VIEW
              </div>
            </>
          ) : (
            <>
              {/* Map view */}
              <div
                style={{
                  width: "100%", height: "100%",
                  background: "var(--surface2)",
                  backgroundImage: `
                    linear-gradient(rgba(59,130,246,0.05) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(59,130,246,0.05) 1px, transparent 1px)
                  `,
                  backgroundSize: "48px 48px",
                  position: "relative",
                }}
              >
                {/* Simulated road network */}
                <svg style={{ position: "absolute", inset: 0, width: "100%", height: "100%" }} viewBox="0 0 800 600">
                  {/* Roads */}
                  <line x1="0" y1="300" x2="800" y2="300" stroke="rgba(120,160,200,0.2)" strokeWidth="12" />
                  <line x1="0" y1="300" x2="800" y2="300" stroke="rgba(120,160,200,0.08)" strokeWidth="2" strokeDasharray="20,10" />
                  <line x1="400" y1="0" x2="400" y2="600" stroke="rgba(120,160,200,0.15)" strokeWidth="8" />
                  <line x1="200" y1="0" x2="200" y2="600" stroke="rgba(120,160,200,0.1)" strokeWidth="4" />
                  <line x1="600" y1="0" x2="600" y2="600" stroke="rgba(120,160,200,0.1)" strokeWidth="4" />
                  <line x1="0" y1="180" x2="800" y2="180" stroke="rgba(120,160,200,0.1)" strokeWidth="4" />
                  <line x1="0" y1="440" x2="800" y2="440" stroke="rgba(120,160,200,0.1)" strokeWidth="4" />

                  {/* Factory block */}
                  <rect x="330" y="240" width="100" height="70" rx="3" fill="rgba(37,99,235,0.2)" stroke="rgba(59,130,246,0.5)" strokeWidth="1" />
                  <text x="380" y="272" fill="#60a5fa" fontSize="8" textAnchor="middle" fontFamily="JetBrains Mono, monospace" letterSpacing="0.5">BARANI</text>
                  <text x="380" y="283" fill="#60a5fa" fontSize="8" textAnchor="middle" fontFamily="JetBrains Mono, monospace" letterSpacing="0.5">HYDRAULICS</text>

                  {/* Neighbor blocks */}
                  {[[100, 200, 80, 50], [550, 160, 90, 50], [150, 380, 70, 55], [560, 380, 80, 50]].map(([x, y, w, h], i) => (
                    <rect key={i} x={x} y={y} width={w} height={h} rx="2" fill="rgba(59,130,246,0.06)" stroke="rgba(59,130,246,0.15)" strokeWidth="1" />
                  ))}
                </svg>

                <div
                  style={{
                    position: "absolute", bottom: 12, left: 12,
                    background: "rgba(6,12,24,0.85)",
                    border: "1px solid var(--border)", borderRadius: 3,
                    padding: "5px 10px",
                    fontFamily: "var(--font-mono)", fontSize: 9, color: "var(--text-muted)",
                    letterSpacing: "0.06em",
                  }}
                >
                  MAP VIEW &nbsp;|&nbsp; SIPCOT INDUSTRIAL AREA, HOSUR
                </div>
              </div>
            </>
          )}

          {/* Location marker */}
          <motion.div
            animate={{ y: [0, -5, 0] }}
            transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
            style={{
              position: "absolute", top: "42%", left: "50%",
              transform: "translate(-50%, -100%)",
              display: "flex", flexDirection: "column", alignItems: "center", gap: 0,
            }}
          >
            <div
              style={{
                background: "var(--primary)",
                border: "2px solid #60a5fa",
                borderRadius: "50% 50% 50% 0",
                transform: "rotate(-45deg)",
                width: 28, height: 28,
                boxShadow: "0 0 14px rgba(59,130,246,0.6)",
              }}
            />
            <div style={{ width: 3, height: 12, background: "rgba(59,130,246,0.5)" }} />
          </motion.div>

          {/* Pulse rings */}
          {[1, 2, 3].map((i) => (
            <motion.div
              key={i}
              style={{
                position: "absolute", top: "42%", left: "50%",
                transform: "translate(-50%, -50%)",
                border: "1px solid rgba(59,130,246,0.3)",
                borderRadius: "50%",
              }}
              animate={{ width: [20, 80 + i * 30], height: [20, 80 + i * 30], opacity: [0.8, 0] }}
              transition={{ duration: 2, repeat: Infinity, delay: i * 0.5 }}
            />
          ))}
        </div>

        {/* Info panel */}
        <div
          style={{
            width: 300, background: "var(--surface)",
            borderLeft: "1px solid var(--border)",
            display: "flex", flexDirection: "column",
            overflow: "auto", flexShrink: 0,
          }}
        >
          {/* Company card */}
          <div
            style={{
              padding: 18,
              background: "linear-gradient(135deg, rgba(37,99,235,0.1) 0%, transparent 100%)",
              borderBottom: "1px solid var(--border)",
            }}
          >
            <div style={{ display: "flex", gap: 10, alignItems: "flex-start", marginBottom: 12 }}>
              <div
                style={{
                  width: 40, height: 40, borderRadius: 4,
                  background: "linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%)",
                  border: "1px solid rgba(59,130,246,0.4)",
                  display: "flex", alignItems: "center", justifyContent: "center",
                  flexShrink: 0,
                }}
              >
                <Building2 size={18} style={{ color: "#93c5fd" }} />
              </div>
              <div>
                <div style={{ fontFamily: "var(--font-display)", fontWeight: 800, fontSize: 14, letterSpacing: "0.08em", color: "var(--text)", lineHeight: 1 }}>
                  BARANI HYDRAULICS
                </div>
                <div style={{ fontFamily: "var(--font-display)", fontSize: 10, color: "var(--steel)", letterSpacing: "0.1em", marginTop: 2 }}>
                  PVT. LTD.
                </div>
              </div>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {[
                { icon: MapPin, label: LOCATION.address },
                { icon: Phone, label: LOCATION.phone },
                { icon: Mail, label: LOCATION.email },
                { icon: Globe, label: LOCATION.website },
                { icon: Clock, label: LOCATION.hours },
              ].map(({ icon: Icon, label }, i) => (
                <div key={i} style={{ display: "flex", gap: 8, alignItems: "flex-start" }}>
                  <Icon size={12} style={{ color: "var(--accent)", marginTop: 1, flexShrink: 0 }} />
                  <span style={{ fontSize: 11, color: "var(--text-muted)", lineHeight: 1.4 }}>{label}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Stats */}
          <div style={{ padding: 16, borderBottom: "1px solid var(--border)" }}>
            <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.14em", marginBottom: 10 }}>FACILITY INFO</div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
              {[
                { label: "Established", value: LOCATION.established },
                { label: "Facility Area", value: LOCATION.area },
                { label: "Employees", value: "247" },
                { label: "Machines", value: "63 units" },
              ].map(({ label, value }) => (
                <div key={label} style={{ background: "var(--card)", border: "1px solid var(--border)", borderRadius: 4, padding: "9px 11px" }}>
                  <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.1em", textTransform: "uppercase", marginBottom: 3 }}>{label}</div>
                  <div style={{ fontSize: 13, fontWeight: 700, color: "var(--text)", fontFamily: "var(--font-display)" }}>{value}</div>
                </div>
              ))}
            </div>
          </div>

          {/* Nearby */}
          <div style={{ padding: 16, borderBottom: "1px solid var(--border)" }}>
            <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", letterSpacing: "0.14em", marginBottom: 10 }}>NEARBY LANDMARKS</div>
            <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
              {NEARBY.map((n) => (
                <div key={n.name} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "6px 10px", background: "var(--card)", border: "1px solid var(--border)", borderRadius: 3 }}>
                  <div>
                    <div style={{ fontSize: 11, color: "var(--text)", fontWeight: 500 }}>{n.name}</div>
                    <div style={{ fontFamily: "var(--font-mono)", fontSize: 8, color: "var(--text-dim)", marginTop: 1 }}>{n.type}</div>
                  </div>
                  <span style={{ fontFamily: "var(--font-mono)", fontSize: 10, color: "var(--accent)" }}>{n.dist}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Actions */}
          <div style={{ padding: 16, display: "flex", flexDirection: "column", gap: 8 }}>
            <button
              style={{
                display: "flex", alignItems: "center", justifyContent: "center", gap: 7,
                padding: "10px", background: "var(--primary)",
                border: "1px solid rgba(59,130,246,0.5)", borderRadius: 4,
                color: "#fff", cursor: "pointer", fontSize: 12, fontWeight: 600,
                fontFamily: "var(--font-display)", letterSpacing: "0.06em",
              }}
            >
              <Navigation size={13} /> GET DIRECTIONS
            </button>
            <button
              style={{
                display: "flex", alignItems: "center", justifyContent: "center", gap: 7,
                padding: "10px", background: "var(--card)",
                border: "1px solid var(--border)", borderRadius: 4,
                color: "var(--text-muted)", cursor: "pointer", fontSize: 12,
                fontFamily: "var(--font-body)",
              }}
            >
              <ExternalLink size={12} /> Open in Google Maps
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
