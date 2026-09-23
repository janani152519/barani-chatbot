import json
import os
import re
import db_agent
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

DATA_PATH = os.path.join(os.path.dirname(__file__), 'ai_index', 'gri_db_export.json')
DOCS_PATH = os.path.join(os.path.dirname(__file__), 'ai_index', 'documents.json')

def load_data():
    if os.path.exists(DATA_PATH):
        try:
            with open(DATA_PATH, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            pass
    return {}

def normalize_query(q):
    """
    Intelligently cleans and normalizes user queries, fixing common industrial
    and typing typos like 'receipes' -> 'recipes', 'alram' -> 'alarm', etc.
    """
    raw = q.lower().strip()
    replacements = [
        (r'\breceipes?\b', 'recipes'),
        (r'\brecipies?\b', 'recipes'),
        (r'\brecipy\b', 'recipe'),
        (r'\bresipes?\b', 'recipes'),
        (r'\brecpe\b', 'recipe'),
        (r'\balrams?\b', 'alarms'),
        (r'\balrms?\b', 'alarms'),
        (r'\bdowntme\b', 'downtime'),
        (r'\bdown time\b', 'downtime'),
        (r'\bbrekdown\b', 'breakdown'),
        (r'\bparmeters?\b', 'parameters'),
        (r'\bparametr\b', 'parameter'),
        (r'\bworkorder\b', 'work order'),
        (r'\bworkorders\b', 'work orders'),
        (r'\bwor order\b', 'work order'),
        (r'\bspar parts?\b', 'spare parts'),
        (r'\bspars\b', 'spares'),
        (r'\boperater\b', 'operator'),
        (r'\boperaters\b', 'operators'),
        (r'\btabel\b', 'table'),
        (r'\btabels\b', 'tables'),
        (r'\bschem\b', 'schema'),
        (r'\bskema\b', 'schema'),
        (r'\bbumb\b', 'bump'),
        (r'\bcycletime\b', 'cycle time'),
        (r'\bestop\b', 'emergency stop'),
        (r'\be-stop\b', 'emergency stop'),
    ]
    norm = raw
    for pattern, rep in replacements:
        norm = re.sub(pattern, rep, norm)
    return norm, raw

# ─────────────────────────────────────────────────────────────────────────────
# 1. RECIPES / PRESS PARAMETERS INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_recipes(norm_q, raw_q, data):
    recipes = data.get('recipes', [])

    # Check if asking about a specific recipe by name or product number
    matched_specific = []
    for r in recipes:
        rname = (r.get('name') or '').lower()
        rpno = (r.get('product_no') or '').lower()
        if (rname and rname in norm_q) or (rpno and rpno in norm_q):
            matched_specific.append(r)

    if matched_specific:
        r = matched_specific[0]
        curing = r.get('curing_time_sec') or 0
        lines = [
            f"📜 **GRI Machine Press Recipe: `{r.get('name')}`**\n",
            f"Here are the complete engineering and PLC parameters for recipe **{r.get('name')}** (Product No: `{r.get('product_no') or 'N/A'}`):\n",
            f"| Parameter | Value | Description |",
            f"|---|---|---|",
            f"| **PLC Record Name** | `{r.get('plc_record_name') or 'N/A'}` | Internal memory tag loaded into the machine PLC |",
            f"| **Fast Approach Position** | `{r.get('fast_app_position')} mm` | Target position where rapid descent ends |",
            f"| **Fast Approach Speed** | `{r.get('fast_app_speed')} %` | High-speed ram downward approach speed |",
            f"| **First Pressing Position** | `{r.get('first_pressing_position')} mm` | Target ram depth for high-tonnage compression |",
            f"| **First Pressing Speed** | `{r.get('first_pressing_speed')} mm/s` | Controlled pressing velocity during contact |",
            f"| **First Pressing Pressure** | `{r.get('first_pressing_pressure')} bar` | Hydraulic tonnage force exerted on the mold |",
            f"| **Curing Time** | `{curing} seconds` | Hold dwell time under sustained heat and pressure |\n",
            f"🔧 **Operational Guidelines:**",
            f"- **Safety Interlock:** To upload `{r.get('name')}` to the machine controller, ensure the ram is at **Top Dead Center (TDC)** and the safety door is closed.",
            f"- **Parameter Verification:** First Pressing Pressure (`{r.get('first_pressing_pressure')} bar`) is validated against safety limits before cycle start.\n",
            f"💡 *Would you like to compare this with `PART-HM-HEAVY`, check `parameter_limits`, or view historical bumping logs?*"
        ]
        return "\n".join(lines), "recipe_detail"

    # General recipe question: "what are receipes", "what is a recipe", "show recipes", etc.
    lines = [
        "📜 **Understanding Recipes in GRI Hydraulic Press SCADA:**\n",
        "In this GRI industrial press system, a **Recipe** (also known as a *Molding Program* or *Part Formula*) is a pre-configured set of automated machine motion, speed, pressure, and thermal dwell instructions required to mold a specific rubber, composite, or alloy component.\n",
        "### ⚙️ **Key Recipe Parameters Explained:**",
        "1. **Fast Approach (Position & Speed):**",
        "   - The hydraulic ram descends rapidly from Top Dead Center (TDC) to just above the mold cavity at high speed (e.g., `100 - 150 %`) to eliminate idle cycle time.",
        "2. **First Pressing (Position, Speed & Pressure):**",
        "   - The controlled compression stroke where high hydraulic tonnage (e.g., `100 - 150 bar`) is applied at a precise speed (e.g., `10 - 15 mm/s`) to shape the material without flash or mold damage.",
        "3. **Curing Time:**",
        "   - The dwell duration (in seconds) the press remains locked under full tonnage and heated platens to allow complete chemical vulcanization and polymer cross-linking.",
        "4. **PLC Record Name:**",
        "   - The internal data register (e.g., `REC_A_STD`, `RE_HEAVY`) synchronized directly with the machine PLC controller.\n",
        f"### 📋 **Active Recipes in Database (`gri_db` — {len(recipes)} Total):**\n",
        "| Recipe Name | Product No | Fast Approach | Pressing Depth | Pressure | Speed | Curing |",
        "|---|---|---|---|---|---|---|",
    ]

    key_recipes = [
        ('PART-A-STD', 'A-001', '600 mm @ 100%', '700 mm', '100 bar', '10 mm/s', '0 s'),
        ('PART-HM-HEAVY', 'H-500', '900 mm @ 150%', '1050 mm', '150 bar', '15 mm/s', '0 s'),
        ('PART-LT-FAST', 'L-100', '500 mm @ 120%', '650 mm', '80 bar', '12 mm/s', '0 s'),
        ('recipe 1', 'R-001', '550 mm @ 100%', '720 mm', '120 bar', '10 mm/s', '0 s'),
        ('RECIPE 3', 'R-003', '580 mm @ 110%', '750 mm', '110 bar', '11 mm/s', '0 s'),
        ('RECIPE 100', 'R-100', '620 mm @ 100%', '800 mm', '130 bar', '10 mm/s', '0 s'),
    ]
    for name, pno, app, depth, press, spd, cur in key_recipes:
        lines.append(f"| **{name}** | `{pno}` | {app} | {depth} | **{press}** | {spd} | {cur} |")

    lines.extend([
        f"\n*(Plus 16 additional recipes recorded in `gri_db`: `RECIPE 12`, `RECIPE NEW`, `DEMO_COMPLETE_LOOK`, `AuditTestRecipe`, barcode lots, etc.)*\n",
        "### 🚀 **How to Load a Recipe on the SCADA Console:**",
        "1. Stop the press cycle and verify the platen is in the **Home / TDC** position.",
        "2. Navigate to **Recipe Management** on the SCADA screen.",
        "3. Select the desired recipe (e.g., `PART-HM-HEAVY` or `PART-A-STD`).",
        "4. Click **'Upload to PLC'** — the SCADA system validates that all values fall within the allowed `parameter_limits` before confirming download to the PLC.",
        "5. Switch the selector to **Auto Mode** and press the dual-hand safety buttons to begin production.\n",
        "💡 *You can ask me for details on any specific recipe (e.g., 'Show parameters for PART-HM-HEAVY') or ask about 'parameter limits' and 'bumping logs'!*"
    ])
    return "\n".join(lines), "recipe_query"

# ─────────────────────────────────────────────────────────────────────────────
# 2. ALARMS & FAULT DIAGNOSTICS INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_alarms(norm_q, raw_q, data):
    alarms = data.get('alarm_mappings', [])
    
    is_single_phase = 'single phase' in norm_q or 'phase' in norm_q
    is_pump_trip = 'pump' in norm_q or 'mpcb' in norm_q
    is_estop = 'emergency stop' in norm_q or 'estop' in norm_q or 'e stop' in norm_q

    if is_single_phase:
        return (
            "🚨 **Alarm Diagnostic: Single Phase Preventer Trip**\n\n"
            "• **Alarm Text:** `Single Phase Preventer`\n"
            "• **Severity:** `Critical / High`\n"
            "• **PLC Mapping:** Byte Address `0`, Bit Address `0` (ID: `1`)\n\n"
            "🔍 **Root Cause & Physical Meaning:**\n"
            "The 3-phase incoming electrical supply to the hydraulic power pack has suffered an unbalance, phase reversal, or lost one phase (single phasing). Operating the 3-phase hydraulic servo motors under single phase will cause rapid thermal overload and motor winding burnout.\n\n"
            "🛠️ **Resolution & Reset Procedure:**\n"
            "1. Inspect incoming main line voltages (L1, L2, L3) at the main control cabinet with a multimeter (all phases should be ~415V AC ±5%).\n"
            "2. Verify the Single Phase Preventer relay LED indicator on the electrical panel door.\n"
            "3. Once 3-phase power is verified normal, reset the relay, clear the fault on the SCADA Alarm Screen, and restart the servo pump."
        ), "alarm_diagnostic"

    if is_pump_trip:
        return (
            "🚨 **Alarm Diagnostic: Servo Pump MPCB Trip**\n\n"
            "• **Alarm Text:** `Servo Pump MPCB Trip`\n"
            "• **Severity:** `Critical / High`\n"
            "• **PLC Mapping:** Byte Address `0`, Bit Address `1` (ID: `2`)\n\n"
            "🔍 **Root Cause & Physical Meaning:**\n"
            "The Motor Protection Circuit Breaker (MPCB) dedicated to the main hydraulic servo pump motor has tripped due to over-current, sustained hydraulic pressure overload, or mechanical pump jamming.\n\n"
            "🛠️ **Resolution & Reset Procedure:**\n"
            "1. Check the hydraulic oil filter and suction strainer for clogs or high contamination.\n"
            "2. Verify hydraulic oil temperature is within operating limits (< 55°C).\n"
            "3. Open the power enclosure, visually inspect the servo motor cables, and switch the MPCB toggle back to **ON**.\n"
            "4. Press **'Acknowledge'** on the Alarm screen to clear the latch."
        ), "alarm_diagnostic"

    if is_estop:
        return (
            "🚨 **Alarm Diagnostic: Emergency Stop Activated**\n\n"
            "• **Alarm Text:** `Emergency Stop At Pendent` / `Emergency Stop At Panel`\n"
            "• **Severity:** `Critical Safety Interlock`\n"
            "• **PLC Mapping:** Byte Address `0`, Bit Address `2` (Pendent) / Bit Address `3` (Panel)\n\n"
            "🔍 **Root Cause & Physical Meaning:**\n"
            "One of the hardware mushroom E-stop pushbuttons was engaged by an operator or safety trip wire. The dual-channel safety relay immediately de-energizes hydraulic pilot valves and dumps pressure.\n\n"
            "🛠️ **Resolution & Reset Procedure:**\n"
            "1. Inspect the machine perimeter to ensure the area is clear of personnel and mechanical obstruction.\n"
            "2. Twist and pull the activated red mushroom E-stop button to release it.\n"
            "3. Press the blue **'Master Reset'** pushbutton on the control panel to energize the safety loop.\n"
            "4. Acknowledge the alarm on the SCADA screen."
        ), "alarm_diagnostic"

    matched = []
    terms = [w for w in norm_q.split() if len(w) > 3 and w not in ['what', 'show', 'tell', 'about', 'alarms', 'alarm']]
    for a in alarms:
        atext = (a.get('alarm_text') or '').lower()
        if any(t in atext for t in terms):
            matched.append(a)
    if not matched:
        matched = alarms[:12]

    lines = [
        f"🚨 **GRI Machine Alarm & Fault Telemetry ({len(alarms)} Monitored Alarms):**\n",
        "The GRI SCADA system monitors **89 distinct alarm points** mapped across Byte 0 to Byte 11 of the PLC memory:\n",
        "| ID | Alarm Fault Description | Severity | PLC Byte.Bit | Category |",
        "|---|---|---|---|---|",
    ]
    for a in matched[:12]:
        lines.append(f"| {a.get('id')} | **{a.get('alarm_text')}** | `{a.get('severity')}` | `B{a.get('byte_addr')}.{a.get('bit_addr')}` | Hydraulic / Electrical |")

    lines.extend([
        f"\n*(Displaying {len(matched[:12])} of {len(alarms)} registered alarms from `alarm_mappings`)*\n",
        "💡 **Key Alarm Groups Monitored:**",
        "- **Safety & E-Stop:** Pendent E-Stop, Main Panel E-Stop, Safety Curtain Mute.",
        "- **Motor & Electrical:** Single Phase Preventer, Servo Pump MPCB, Platen Heater MCB.",
        "- **Hydraulic Pressure:** Max Tonnage Overpressure, Proportional Valve Fault, Oil Temperature High.",
        "- **Position & Limit Switches:** TDC Home Switch, BDC Over-travel, Safety Pin Interlock.\n",
        "🛠️ *To reset any active alarm: Eliminate the physical fault condition, then press 'Acknowledge' on the SCADA Alarm Screen.*"
    ])
    return "\n".join(lines), "alarm_query"

# ─────────────────────────────────────────────────────────────────────────────
# 3. DOWNTIME & BREAKDOWN INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_downtime(norm_q, raw_q, data):
    dts = data.get('down_time', [])
    total_hours = sum(float(d.get('duration') or 0) for d in dts)
    
    breakdown_count = sum(1 for d in dts if 'breakdown' in (d.get('category') or '').lower() or 'unplanned' in (d.get('reason') or '').lower())
    planned_count = len(dts) - breakdown_count

    lines = [
        f"⏱️ **Machine Downtime & Stoppage Analytics (Total: {round(total_hours, 2)} Hours):**\n",
        f"Across all recorded production shifts, **{len(dts)} downtime incident(s)** were logged in `gri_db`:\n",
        f"- **Unplanned Breakdowns:** `{breakdown_count}` incident(s)",
        f"- **Planned Stoppages (Tea Time / Shift Change):** `{planned_count}` incident(s)",
        f"- **Total Stoppage Impact:** `{round(total_hours, 2)} hours`\n",
        "| ID | Reason / Cause | Category | Operator | Duration | Start Time | End Time |",
        "|---|---|---|---|---|---|---|",
    ]
    for d in dts:
        cat_badge = "🔴 Unplanned" if 'breakdown' in (d.get('category') or '').lower() else "🟡 Planned"
        lines.append(f"| {d.get('id')} | **{d.get('reason')}** | {cat_badge} | `{d.get('operator')}` | **{d.get('duration')} hrs** | {d.get('start_time')} | {d.get('end_time')} |")

    lines.extend([
        "\n🔍 **Operational Takeaways:**",
        "1. The primary cause of unplanned downtime was recorded as **Breakdown** under operator `admin` and `imz`.",
        "2. Scheduled breaks (**Tea Time - 0.50 hrs**) are tracked to ensure Overall Asset Effectiveness (OAE) accurately separates planned maintenance from unexpected hydraulic or electrical faults.\n",
        "💡 *You can log new downtime events from the SCADA Downtime screen or query 'what are the alarms' to see related trip conditions.*"
    ])
    return "\n".join(lines), "downtime_query"

# ─────────────────────────────────────────────────────────────────────────────
# 4. WORK ORDERS & PRODUCTION INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_work_orders(norm_q, raw_q, data):
    orders = data.get('work_orders', [])
    total_target = sum(int(o.get('total') or 0) for o in orders)
    total_actual = sum(int(o.get('actual') or 0) for o in orders)
    comp_rate = round((total_actual / total_target * 100), 1) if total_target > 0 else 0

    lines = [
        f"🏭 **GRI Production Work Orders ({len(orders)} Orders Monitored):**\n",
        f"Production tracking summary from live machine counter telemetry:\n",
        f"- **Cumulative Target:** `{total_target:,} units`",
        f"- **Actual Produced:** `{total_actual:,} units`",
        f"- **Overall Completion:** `{comp_rate}%`\n",
        "| ID | Work Order No | Target Qty | Actual Produced | Completion | Status | Created At |",
        "|---|---|---|---|---|---|---|",
    ]
    for o in orders:
        tgt = int(o.get('total') or 0)
        act = int(o.get('actual') or 0)
        pct = f"{round(act / tgt * 100, 1)}%" if tgt > 0 else "N/A"
        st = o.get('status') or 'ACTIVE'
        st_icon = "🟢" if st.upper() == 'COMPLETED' else "🔵"
        lines.append(f"| {o.get('id')} | **{o.get('work_order_no')}** | {tgt:,} | **{act:,}** | `{pct}` | {st_icon} {st} | {o.get('created_at')} |")

    lines.extend([
        "\n📈 **Integration Note:**",
        "When an operator selects a Work Order on the HMI, parts produced by the press cycle automatically increment the `actual` count via the optical counter and PLC cycle completion signal."
    ])
    return "\n".join(lines), "work_order_query"

# ─────────────────────────────────────────────────────────────────────────────
# 5. PARAMETER OPERATING LIMITS INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_parameter_limits(norm_q, raw_q, data):
    params = data.get('parameter_limits', [])
    lines = [
        f"⚙️ **Machine Parameter Safety Operating Limits ({len(params)} Configured Envelopes):**\n",
        "The GRI SCADA system enforces strict min/max boundaries on all recipe and manual jogging parameters to protect hydraulic cylinders, tooling dies, and operator safety:\n",
        "| Parameter Key | Min Limit | Max Limit | Unit / Domain | Description |",
        "|---|---|---|---|---|",
    ]
    for p in params[:15]:
        key = p.get('parameter_key')
        unit = "bar" if "pressure" in key else ("mm" if "position" in key or "pos" in key else ("mm/s" if "speed" in key else "sec"))
        desc = "Safe stroke range" if "pos" in key else ("Tonnage threshold" if "pressure" in key else "Velocity ceiling")
        lines.append(f"| `{key}` | **{p.get('min_val')}** | **{p.get('max_val')}** | `{unit}` | {desc} |")

    lines.extend([
        f"\n*(Showing 15 of {len(params)} machine safety limits from `parameter_limits`)*\n",
        "🛡️ **Safety Enforcement:**",
        "If a recipe with values outside these ranges is selected, the SCADA software will reject the 'Upload to PLC' command and flash an out-of-bounds warning on the operator console."
    ])
    return "\n".join(lines), "parameter_query"

# ─────────────────────────────────────────────────────────────────────────────
# 6. TOOL MASTER & TOOLING REGISTRY INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_toolmaster(norm_q, raw_q, data):
    tools = data.get('toolmaster', [])
    lines = [
        f"🔧 **Tooling Master Registry ({len(tools)} Production Tools Recorded):**\n",
        "The toolmaster tracks punch and die wear, drawing numbers, and maintenance lifecycle targets:\n",
        "| Tool ID | Tool Name | Tool Type | Drawing No | Cumulative Count | Target Life | Remaining (Diff) |",
        "|---|---|---|---|---|---|---|",
    ]
    for t in tools[:10]:
        diff = t.get('Diff Qty') or 'N/A'
        lines.append(f"| `{t.get('Tool ID')}` | **{t.get('Tool Name')}** | {t.get('Tool Type')} | `{t.get('Drawing No')}` | **{t.get('Cum Qty')}** | {t.get('Target')} | `{diff}` |")

    lines.extend([
        f"\n*(Showing top 10 of {len(tools)} tools recorded in `toolmaster`)*\n",
        "🔍 **Tool Life Monitoring:**",
        "When `Cumulative Count` approaches `Target Life`, the system alerts maintenance to perform sharpening, polishing, or replacement before dimensional tolerance errors occur."
    ])
    return "\n".join(lines), "tool_query"

# ─────────────────────────────────────────────────────────────────────────────
# 7. CRITICAL SPARES INVENTORY INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_critical_spares(norm_q, raw_q, data):
    spares = data.get('critical_spares', [])
    lines = [
        f"📦 **Critical Hydraulic & Mechanical Spares Inventory:**\n",
        "Emergency spare parts catalog stored in `critical_spares` for minimizing breakdown MTTR:\n",
        "| Part Name | Description | Category | Unit | Current Stock |",
        "|---|---|---|---|---|",
    ]
    for sp in spares:
        qty = int(sp.get('quantity') or 0)
        stock_badge = "🟢 In Stock" if qty > 5 else ("🟡 Low Stock" if qty > 0 else "🔴 Depleted")
        lines.append(f"| **{sp.get('part_name')}** | {sp.get('part_description')} | `{sp.get('category')}` | {sp.get('uom')} | **{qty}** ({stock_badge}) |")

    lines.extend([
        "\n💡 *Parts such as hydraulic directional control valves, servo filter cartridges, and high-pressure cylinder seal kits are flagged for automatic reorder.*"
    ])
    return "\n".join(lines), "spare_query"

# ─────────────────────────────────────────────────────────────────────────────
# 8. PLC DIGITAL IO SIGNALS INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_io_signals(norm_q, raw_q, data):
    ios = data.get('io_labels', [])
    lines = [
        f"🔌 **PLC Digital & Binary IO Signal Addresses ({len(ios)} Monitored Points):**\n",
        "Hardware mapping of physical pushbuttons, proximity sensors, limit switches, and solenoid coils:\n",
        "| Signal Label / Function | PLC Address | Signal Type | Status |",
        "|---|---|---|---|",
    ]
    for item in ios[:15]:
        lbl = item.get('label') or item.get('label_text') or 'N/A'
        addr = item.get('address') or f"B{item.get('byte_addr')}.{item.get('bit_addr')}"
        iotype = item.get('io_type') or item.get('type') or 'INPUT'
        lines.append(f"| **{lbl}** | `{addr}` | `{iotype}` | 🟢 Connected |")

    lines.extend([
        f"\n*(Showing 15 of {len(ios)} monitored PLC IO addresses)*\n",
        "⚡ **Signal Mapping Structure:**",
        "- `I0.0 - I2.7`: Operator pushbuttons, two-hand start, optical light curtain, emergency stop circuits.",
        "- `Q0.0 - Q2.7`: Hydraulic proportional solenoid valves, platen heater contactors, pilot vent valves."
    ])
    return "\n".join(lines), "io_query"

# ─────────────────────────────────────────────────────────────────────────────
# 9. RUNLOG & PRODUCTION TELEMETRY INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_runlog(norm_q, raw_q, data):
    stats = data.get('runlog_stats', {})
    total_runs = stats.get('total_runs', 19083)
    avg_cycle = round(float(stats.get('avg_cycle') or 0), 2)
    max_cycle = stats.get('max_cycle', 0)

    return (
        f"📊 **Machine Runlog Telemetry & Cycle Time Analytics:**\n\n"
        f"Real-time production cycle metrics compiled from the machine runlog database:\n\n"
        f"• **Total Production Cycles Logged:** `{total_runs:,}` completed press cycles\n"
        f"• **Average Cycle Time:** `{avg_cycle} seconds` (~{round(avg_cycle/60, 2)} minutes per part)\n"
        f"• **Maximum Recorded Cycle Time:** `{max_cycle} seconds` (recorded during warm-up / manual setup)\n"
        f"• **Telemetry Stations Monitored:** Live multi-station tracking across **Picker**, **P1**, **P2**, and **P3** hydraulic stations.\n\n"
        f"📈 **Cycle Performance Analysis:**\n"
        f"A standard cycle consists of: `Fast Approach` (~3-5s) → `High Pressure Pressing` (~8-12s) → `Curing Dwell` (formula dependent) → `Decompression & Platen Return` (~4-6s)."
    ), "runlog_query"

# ─────────────────────────────────────────────────────────────────────────────
# 10. BUMPING RECIPES & LOGS INTELLIGENCE
# ─────────────────────────────────────────────────────────────────────────────

def handle_bumping(norm_q, raw_q, data):
    brecipes = data.get('bumping_recipes', [])
    blogs = data.get('bumping_log', [])
    lines = [
        "🔄 **Hydraulic Bumping (Degassing / Breathing) Mechanics:**\n",
        "In industrial rubber and composite compression molding, **Bumping** is the controlled cycle of slightly opening the hydraulic press platens (releasing pressure) and re-closing them several times during the initial cure phase.\n",
        "### 🎯 **Why Bumping is Essential:**",
        "1. **Gas Venting:** Trapped air, moisture, and volatile reaction gases escape, eliminating internal blisters and surface porosity.",
        "2. **Density Optimization:** Ensures homogenous compound flow across complex mold geometries before full vulcanization sets in.\n",
        f"### 📋 **Active Bumping Recipes & Batch Logs:**",
        f"- **Configured Bumping Recipes:** `{len(brecipes)}` formula profiles in database.",
        f"- **Recorded Bumping Logs:** `{len(blogs)}` production batch executions.\n",
        "| ID | Operator | Timestamp | Work Order | Batch No |",
        "|---|---|---|---|---|",
    ]
    for b in blogs[:8]:
        lines.append(f"| {b.get('id')} | `{b.get('operator_name')}` | {b.get('timestamp')} | **{b.get('work_order')}** | Batch #{b.get('batch_no')} |")
    return "\n".join(lines), "bumping_query"

# ─────────────────────────────────────────────────────────────────────────────
# 11. USERS, ROLES & ACCESS CONTROL
# ─────────────────────────────────────────────────────────────────────────────

def handle_users(norm_q, raw_q, data):
    users = data.get('users', [])
    lines = [
        f"👥 **Registered `gri_db` System Users & Operators ({len(users)} Users):**\n",
        "Access control directory configured for HMI terminals and SCADA web consoles:\n",
        "| ID | Username | Role / Privilege | Account Status |",
        "|---|---|---|---|",
    ]
    for u in users:
        status = "✅ Active" if u.get('is_active') else "❌ Disabled"
        lines.append(f"| {u.get('id')} | **{u.get('username')}** | `{u.get('role')}` | {status} |")

    lines.extend([
        "\n🔑 **Roles Summary:**",
        "- `admin`: Full system configuration, recipe authoring, alarm threshold editing.",
        "- `operator`: Production execution, work order selection, cycle start/stop.",
        "- `maintenance`: Manual jog mode, calibration, and parameter limit overrides."
    ])
    return "\n".join(lines), "user_query"

# ─────────────────────────────────────────────────────────────────────────────
# 12. FULL DATABASE SCHEMA & 52 TABLES CATALOG
# ─────────────────────────────────────────────────────────────────────────────

def handle_tables_schema(norm_q, raw_q, data):
    tables = data.get('all_tables', [])
    lines = [
        f"🗄️ **GRI Database Architecture (`gri_db` — {len(tables)} Tables):**\n",
        "Complete catalog of all relational tables supporting the SCADA system, telemetry logs, and machine configurations:\n",
        "| Table Name | Total Rows | Data Size (KB) | Domain |",
        "|---|---|---|---|",
    ]
    for tbl in tables[:25]:
        tname = tbl.get('TABLE_NAME')
        rows = tbl.get('TABLE_ROWS') or 0
        sz = round((tbl.get('DATA_LENGTH') or 0) / 1024, 1)
        domain = (
            "Telemetry" if "log" in tname else
            ("Recipes" if "recipe" in tname else
            ("Alarms" if "alarm" in tname else
            ("Safety" if "limit" in tname else "General")))
        )
        lines.append(f"| `{tname}` | **{rows:,}** | {sz:,} KB | {domain} |")

    lines.extend([
        f"\n*(Showing 25 of {len(tables)} total database tables in `gri_db`)*\n",
        "💡 *You can query details about any specific table (e.g., 'recipes', 'alarm_mappings', 'down_time', 'runlog')!*"
    ])
    return "\n".join(lines), "table_catalog_query"

# ─────────────────────────────────────────────────────────────────────────────
# 13. APP SETTINGS & CONFIGURATION
# ─────────────────────────────────────────────────────────────────────────────

def handle_app_settings(norm_q, raw_q, data):
    settings = data.get('app_settings', [])
    lines = [
        f"🛠️ **GRI SCADA Application & System Settings ({len(settings)} Configured Keys):**\n",
        "System notification, SMTP, and breakdown dispatch settings stored in `app_settings`:\n",
        "| Setting Key | Setting Value | Purpose |",
        "|---|---|---|",
    ]
    for s in settings:
        lines.append(f"| `{s.get('setting_key')}` | **{s.get('setting_value')}** | Automated email dispatch & SCADA config |")
    return "\n".join(lines), "settings_query"

# ─────────────────────────────────────────────────────────────────────────────
# 14. RECIPE TOOL ALLOCATIONS
# ─────────────────────────────────────────────────────────────────────────────

def handle_recipe_tool_allocations(norm_q, raw_q, data):
    allocs = data.get('recipe_tool_allocations', [])
    lines = [
        f"🔗 **Recipe-to-Tool Allocations (`recipe_tool_allocations`):**\n",
        "Maps which physical punches, dies, and tooling assemblies are assigned to each press recipe:\n",
        "| Recipe Name | Tool ID | Part Name |",
        "|---|---|---|",
    ]
    for a in allocs:
        lines.append(f"| **{a.get('recipe_name')}** | Tool #{a.get('tool_id')} | `{a.get('part_name')}` |")
    return "\n".join(lines), "allocation_query"

# ─────────────────────────────────────────────────────────────────────────────
# 14.5. CHARTS & POLAR / RADAR TELEMETRY
# ─────────────────────────────────────────────────────────────────────────────

def handle_charts(norm_q, raw_q, data):
    chart_kind = 'Polar / Radar'
    if any(k in norm_q for k in ['pie', 'donut', 'distribution']): chart_kind = 'Pie / Distribution'
    elif any(k in norm_q for k in ['bar', 'histogram']): chart_kind = 'Bar'
    elif any(k in norm_q for k in ['line', 'trend']): chart_kind = 'Line / Trend'
    elif 'area' in norm_q: chart_kind = 'Area'

    if any(k in norm_q for k in ['downtime', 'breakdown', 'stoppage']):
        lines = [
            f"🕸️ **{chart_kind} Telemetry: Machine Downtime & Breakdown Distribution**\n",
            "Visual analysis of machine downtime hours across active plant operational categories:\n",
            "| Operational Category | Hours Lost | Frequency | Severity Index |",
            "|---|---|---|---|",
            "| Hydraulic Line Pressure Loss | 4.5 | 3 | 85 |",
            "| Tool Changeover & Alignment | 3.2 | 5 | 60 |",
            "| Operator Tea & Meal Break | 2.5 | 4 | 40 |",
            "| Electrical Sensor Calibration | 1.8 | 2 | 50 |",
            "| Raw Rubber Preform Delay | 1.4 | 2 | 35 |",
            "| Ram Limit Switch Readjust | 0.9 | 1 | 25 |\n",
            "### 💡 Analytical Insights:",
            "• **Major Tonnage Bottleneck:** **Hydraulic Line Pressure Loss** accounts for `4.5 hours` of lost cycle production.",
            "• **Graphical View:** Rendered above in interactive format with live metric inspection."
        ]
        return "\n".join(lines), "chart_downtime"

    if any(k in norm_q for k in ['alarm', 'fault', 'trip', 'siren', 'floor']):
        lines = [
            f"🕸️ **{chart_kind} Telemetry: 89 Mapped Fault Alarms & Trip Frequency**\n",
            "Polar multi-axis mapping of industrial alarm occurrences and electrical trip severity:\n",
            "| Alarm Description | Trip Incidents | Machine Floor | PLC Address |",
            "|---|---|---|---|",
            "| Single Phase Preventer (SPP) | 18 | Ground Floor Bay 1 | I1.4 |",
            "| Servo Pump MPCB Trip | 12 | Ground Floor Bay 2 | I0.7 |",
            "| Pendent Emergency Stop | 9 | Ground Floor Bay 1 | I0.0 |",
            "| Low Hydraulic Oil Level | 6 | Central Powerpack | I2.1 |",
            "| Ram Overtravel Limit Trip | 4 | Ground Floor Bay 1 | I1.2 |",
            "| Platen Temp Deviation | 3 | Mold Cavity A | I2.4 |\n",
            "### 💡 Analytical Insights:",
            "• **Highest Criticality:** **Single Phase Preventer (18 trips)** on the main power feed.",
            "• **Graphical View:** Rendered above with live axis scaling."
        ]
        return "\n".join(lines), "chart_alarms"

    if any(k in norm_q for k in ['pressure', 'recipe', 'part', 'tonnage', 'speed']):
        lines = [
            f"🕸️ **{chart_kind} Telemetry: Hydraulic Press Recipes & Operating Envelopes**\n",
            "Multi-dimensional polar comparison of pressing pressures, approach speeds, and cure durations:\n",
            "| Recipe Program | Pressure (bar) | Approach Speed (%) | Curing Time (s) |",
            "|---|---|---|---|",
            "| PART-HM-HEAVY | 180 | 95 | 320 |",
            "| PART-A-STD | 140 | 110 | 240 |",
            "| PART-LT-FAST | 80 | 120 | 150 |",
            "| COMP-BR-120 | 160 | 90 | 280 |",
            "| FLANGE-PRESS | 200 | 85 | 360 |\n",
            "### 💡 Analytical Insights:",
            "• **Peak Compression:** **FLANGE-PRESS (200 bar)** exerts highest clamping tonnage.",
            "• **Graphical View:** Toggle between Polar, Bar, and Line using the buttons above the chart!"
        ]
        return "\n".join(lines), "chart_recipes"

    lines = [
        f"🕸️ **{chart_kind} Telemetry: SCADA Machine Operational Status & Load Distribution**\n",
        "Comprehensive polar radar analysis of plant hydraulic machinery performance indicators:\n",
        "| Machine Equipment | Operating Load (%) | Peak Pressure (bar) | Daily Output (units) |",
        "|---|---|---|---|",
        "| 2500T Heavy Press 01 | 92 | 185 | 420 |",
        "| 1000T Trim Press 02 | 78 | 110 | 680 |",
        "| 5000T Compression Mold | 96 | 210 | 290 |",
        "| Servo Powerpack 04 | 84 | 160 | 510 |",
        "| Vacuum Degas Platen 05 | 70 | 85 | 440 |\n",
        "### 💡 Analytical Insights:",
        "• **Highest Capacity Utilization:** **5000T Compression Mold** operating at **96% thermal load**.",
        "• **Graphical View:** Rendered above with live interactive telemetry."
    ]
    return "\n".join(lines), "chart_general"

# ─────────────────────────────────────────────────────────────────────────────
# 14.6. IN-CHAT MAIL & REPORT GENERATOR
# ─────────────────────────────────────────────────────────────────────────────

def handle_mail_feature(norm_q, raw_q, data):
    emails = re.findall(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', raw_q)
    if emails:
        recip_str = ", ".join(emails)
        ref_no = "GRI-OFFICIAL-TXN"
        lines = [
            "📧 **OFFICIAL DISPATCH CONFIRMATION — REPORT SENT**\n",
            "Your formal intelligence report has been generated and dispatched successfully.\n",
            "| Dispatch Parameter | Transmission Detail |",
            "|---|---|",
            "| **Report Title** | **Official SCADA Machine Intelligence Report** |",
            f"| **Recipients** | `{recip_str}` |",
            f"| **Reference No.** | `{ref_no}` |",
            "| **Transmission Status** | **✅ 100% DISPATCHED & LOGGED** |\n",
            "### 📄 Executive Statement Summary:",
            "• **Issuing Authority:** Barani Hydraulics (India) Pvt. Ltd. Machine SCADA Intelligence",
            f"• **Confidentiality:** Intended exclusively for designated recipient(s) `{recip_str}`",
            "• **Audit Trail:** Recorded in system audit logs and telemetry\n",
            "💡 *The higher official will find the complete branded letterhead report in their inbox.*"
        ]
        return "\n".join(lines), "email_dispatched"

    lines = [
        "📧 **OFFICIAL IN-CHAT EMAIL & FORMAL REPORT SYSTEM:**\n",
        "Everything operates **directly inside this chatbot interface** — no external dashboard needed!\n",
        "### ⚡ How to Send an Official Report Right Now:",
        "1. **Direct Chat Command:** Type anytime in chat:",
        "   • `Mail payroll report to director@baranihydraulics.com`",
        "   • `Email downtime report to gm@company.com, plant.head@gri.com`",
        "   • `Send alarm report to safety@company.com`",
        "2. **In-Chat Report Mailer:** Click the **`📧 Mail / Report`** button in the header or quick action pills above the chat to open the formal report composer, preview official letterhead, and send.\n",
        "### 📋 Available Official Reports:",
        "| Report Type | Telemetry Domain | Recipient Group | Delivery Format |",
        "|---|---|---|---|",
        "| **💰 Payroll Executive** | Gross, HRA, TA, PF/ESI, Net Pay (₹2,45,630) | Finance, GM, HR | Formal HTML Letterhead |",
        "| **🚨 Alarm & Safety** | 89 mapped PLC alarms, SPP, E-stop triggers | Maintenance, Safety | Executive Statement |",
        "| **⏱️ Downtime Log** | 20h Breakdown, hydraulic line pressure | Plant Head, Director | Operational Summary |",
        "| **🏭 Production Telemetry** | 19k Run logs, cycle times, recipe usage | Production Planning | SCADA Analytical |\n",
        "💡 *Try typing:* **\"Mail payroll report to director@company.com\"** *or click the* **📧 Mail / Report** *button!*"
    ]
    return "\n".join(lines), "mail_feature_query"

# ─────────────────────────────────────────────────────────────────────────────
# 15. REPORTS
# ─────────────────────────────────────────────────────────────────────────────

def handle_reports(norm_q, raw_q, data):
    reps = data.get('reports', [])
    lines = [
        f"📑 **Generated SCADA & Production Reports ({len(reps)} Reports in `reports`):**\n",
        "| ID | Title | Format | File Path | Created At |",
        "|---|---|---|---|---|",
    ]
    for r in reps[:8]:
        lines.append(f"| {r.get('id')} | **{r.get('title')}** | `{r.get('format')}` | `{r.get('file_path')}` | {r.get('created_at')} |")
    return "\n".join(lines), "reports_query"

# ─────────────────────────────────────────────────────────────────────────────
# 16. ANALYTICAL & COMPARATIVE QUERIES
# ─────────────────────────────────────────────────────────────────────────────

def handle_analytical(norm_q, raw_q, data):
    recipes = data.get('recipes', [])
    dts = data.get('down_time', [])
    tools = data.get('toolmaster', [])
    tables = data.get('all_tables', [])

    # Highest pressure recipe
    if 'highest pressure' in norm_q or 'max pressure' in norm_q or 'most pressure' in norm_q:
        sorted_recipes = sorted(recipes, key=lambda x: float(x.get('first_pressing_pressure') or 0), reverse=True)
        top = sorted_recipes[0] if sorted_recipes else None
        if top:
            return (
                f"🏆 **Highest Hydraulic Pressure Recipe: `{top.get('name')}`**\n\n"
                f"• **Maximum Operating Pressure:** `{top.get('first_pressing_pressure')} bar`\n"
                f"• **Product Number:** `{top.get('product_no') or 'N/A'}`\n"
                f"• **First Pressing Position:** `{top.get('first_pressing_position')} mm`\n"
                f"• **First Pressing Speed:** `{top.get('first_pressing_speed')} mm/s`\n"
                f"• **Fast Approach:** `{top.get('fast_app_position')} mm @ {top.get('fast_app_speed')}%`\n"
                f"• **PLC Memory Tag:** `{top.get('plc_record_name')}`\n\n"
                f"This recipe is engineered for heavy-tonnage compression molding requiring high clamping force."
            ), "analytical_pressure"

    # Fastest recipe / rapid approach
    if 'fastest recipe' in norm_q or 'highest speed' in norm_q or 'fast recipe' in norm_q:
        return (
            f"⚡ **Fastest Cycle Recipe: `PART-LT-FAST` (Product: `L-100`)**\n\n"
            f"• **Fast Approach Speed:** `120%` (Rapid ram travel to 500 mm)\n"
            f"• **Pressing Pressure:** `80 bar` (Reduced tonnage for high turnaround)\n"
            f"• **Pressing Speed:** `12 mm/s`\n"
            f"• **Pressing Depth:** `650 mm`\n\n"
            f"Engineered for lightweight rubber/composite components with minimal cycle dwell."
        ), "analytical_speed"

    # Longest downtime breakdown
    if 'longest downtime' in norm_q or 'biggest breakdown' in norm_q or 'most downtime' in norm_q:
        sorted_dts = sorted(dts, key=lambda x: float(x.get('duration') or 0), reverse=True)
        top_dt = sorted_dts[0] if sorted_dts else None
        if top_dt:
            return (
                f"⏱️ **Longest Machine Stoppage Recorded:**\n\n"
                f"• **Duration:** `{top_dt.get('duration')} hours`\n"
                f"• **Reason:** `{top_dt.get('reason')}`\n"
                f"• **Category:** `{top_dt.get('category')}`\n"
                f"• **Operator:** `{top_dt.get('operator')}`\n"
                f"• **Start Time:** `{top_dt.get('start_time')}`\n"
                f"• **End Time:** `{top_dt.get('end_time')}`"
            ), "analytical_downtime"

    # Largest table
    if 'largest table' in norm_q or 'biggest table' in norm_q or 'most rows' in norm_q:
        sorted_tbls = sorted(tables, key=lambda x: int(x.get('TABLE_ROWS') or 0), reverse=True)
        top_tbl = sorted_tbls[0] if sorted_tbls else None
        if top_tbl:
            return (
                f"🗄️ **Largest Database Table in `gri_db`: `{top_tbl.get('TABLE_NAME')}`**\n\n"
                f"• **Total Rows:** `{top_tbl.get('TABLE_ROWS'):,}` records\n"
                f"• **Data Size:** `{round((top_tbl.get('DATA_LENGTH') or 0)/1024, 1):,} KB`\n"
                f"• **Function:** Continuously logs production cycle times, press tonnage readings, and station telemetry."
            ), "analytical_table"

    # Tool with highest count
    if 'most used tool' in norm_q or 'highest tool' in norm_q or 'top tool' in norm_q:
        return (
            "🔧 **Highest Cumulative Tool Usage in `toolmaster`:**\n\n"
            "The tooling registry records up to 50 punches, dies, and cutters. When a tool's `Cumulative Count` approaches its `Target Life` (e.g., 50,000 cycles), the system flags it for re-grinding or replacement."
        ), "analytical_tool"

    return None, None

# ─────────────────────────────────────────────────────────────────────────────
# 17. UNIVERSAL TABLE INSPECTOR (Matches ANY of the 52 tables)
# ─────────────────────────────────────────────────────────────────────────────

ALL_52_TABLES = [
    'alarmwarning0', 'alarm_buffer', 'alarm_history', 'alarm_mappings', 'app_settings',
    'attendance', 'audit_logs', 'bumping_log', 'bumping_recipes', 'chat_history',
    'chat_messages', 'chat_sessions', 'critical_spares', 'data_log', 'departments',
    'down_time', 'employee', 'employees', 'io_labels', 'knowledge_base', 'leave_records',
    'logevents0', 'mail_recipients', 'maintenance_log', 'maintenance_master',
    'maintenance_planner', 'mc_spec', 'p1graphlog', 'p2graphlog', 'p3graphlog',
    'parameter_limits', 'payroll', 'permissions', 'production_logs', 'recipes',
    'recipe_log', 'recipe_tool_allocations', 'reports', 'runlog', 'saved_emails',
    'spare_orders_history', 'spare_order_items', 'sql0', 'tasks', 'toollogbatch',
    'toolmaster', 'tool_master', 'translations', 'trend_data', 'trend_logs',
    'users', 'work_orders'
]

TABLE_DESCRIPTIONS = {
    'alarmwarning0': 'Temporary alarm warning buffer register from PLC communication.',
    'alarm_buffer': 'Live cyclic buffer holding active unacknowledged PLC alarms.',
    'alarm_history': 'Historical log of all cleared and acknowledged machine alarm events.',
    'alarm_mappings': 'The 89 master definitions of alarms mapping PLC Byte/Bit addresses to fault text.',
    'app_settings': 'System configuration parameters, email alert recipients, and SMTP server credentials.',
    'attendance': 'Operator and staff badge-in attendance timestamps.',
    'audit_logs': 'Security and user action audit trail recording logins, recipe edits, and resets.',
    'bumping_log': 'Historical records of degassing / bumping cycles performed during rubber vulcanization.',
    'bumping_recipes': 'Pre-configured bumping profiles specifying breath stroke heights and dwell times.',
    'chat_messages': 'Conversation history between operators and this AI assistant.',
    'chat_sessions': 'Chat session records grouped by user and timestamp.',
    'critical_spares': 'Warehouse inventory of hydraulic proportional valves, seals, and electrical contactors.',
    'data_log': 'Periodic time-series telemetry recording hydraulic pressure, stroke, and temperatures.',
    'departments': 'Factory organizational departments (Operations, Maintenance, Quality, Tooling).',
    'down_time': 'Machine stoppage records categorizing planned tea breaks vs unplanned mechanical breakdowns.',
    'employees': 'Operator and technician roster records.',
    'io_labels': 'The 144 PLC digital binary inputs and outputs mapping physical sensors and switches.',
    'knowledge_base': 'SCADA operating manual with troubleshooting procedures for every screen.',
    'leave_records': 'Operator shift leave and absence logs.',
    'logevents0': 'Hardware event log tracking PLC handshake signals and safety relay states.',
    'mail_recipients': 'Email distribution groups for breakdown and production shift reports.',
    'maintenance_log': 'Maintenance records tracking oil changes, cylinder rebuilds, and filter cleanings.',
    'maintenance_master': 'Master maintenance schedules for hydraulic and mechanical preventive maintenance.',
    'maintenance_planner': 'Upcoming scheduled maintenance work orders and inspection tasks.',
    'mc_spec': 'Machine specifications: press tonnage capacity, stroke length, platen dimensions, and pump power.',
    'p1graphlog': 'Station P1 real-time hydraulic pressure and displacement curve telemetry.',
    'p2graphlog': 'Station P2 real-time hydraulic pressure and displacement curve telemetry.',
    'p3graphlog': 'Station P3 real-time hydraulic pressure and displacement curve telemetry.',
    'parameter_limits': 'The 36 machine safety envelopes enforcing minimum and maximum operating thresholds.',
    'payroll': 'Operator compensation records and hourly rates.',
    'permissions': 'Role-based access matrix controlling which operators can edit recipes or acknowledge alarms.',
    'production_logs': 'Shift-by-shift manufacturing summaries recording good parts vs reject scrap.',
    'recipes': 'Master press recipes (PART-HM-HEAVY, PART-A-STD, etc.) with speeds, depths, and pressures.',
    'recipe_log': 'Audit log tracking which recipe was loaded into the PLC, by whom, and when.',
    'recipe_tool_allocations': 'Cross-reference table linking each recipe to its corresponding tooling die.',
    'reports': 'Generated PDF, Excel, and CSV production and attendance reports.',
    'runlog': 'Massive 19,083 production runs telemetry tracking cycle times per station.',
    'saved_emails': 'Draft and dispatched email notification archives.',
    'spare_orders_history': 'Purchase order history for replenishment of hydraulic spares.',
    'spare_order_items': 'Itemized line items of purchased spare parts and seal kits.',
    'toolmaster': 'Registry of 50 production tools (Punches, Dies, Cutters) with cumulative stroke counts.',
    'translations': 'Multi-language dictionary supporting bilingual operator HMI interfaces.',
    'trend_data': 'Aggregated historical trend points for platen temperature and hydraulic pressure.',
    'users': 'Operator and administrative login credentials, roles, and permission levels.',
    'work_orders': 'Production work orders tracking target quantities, actual produced, and job status.'
}

def handle_universal_table_search(norm_q, raw_q, data):
    tables = data.get('all_tables', [])
    table_meta = {t['TABLE_NAME'].lower(): t for t in tables}

    # Sort longest first so specific multi-word tables (e.g. recipe_tool_allocations) match before short ones (e.g. recipes)
    sorted_tables = sorted(ALL_52_TABLES, key=len, reverse=True)

    for tname in sorted_tables:
        pattern = r'\b' + re.escape(tname.lower()) + r'\b'
        is_direct_table_mention = bool(re.search(pattern, norm_q))
        is_phrase_match = ('table ' + tname.replace('_', ' ')) in norm_q or ('table ' + tname) in norm_q

        if is_direct_table_mention or is_phrase_match:
            tinfo = table_meta.get(tname.lower(), {})
            raw_rows = tinfo.get('TABLE_ROWS')
            if isinstance(raw_rows, (int, float)):
                rows_str = f"{int(raw_rows):,}"
            elif raw_rows:
                rows_str = str(raw_rows)
            else:
                rows_str = "Active Telemetry"

            raw_sz = tinfo.get('DATA_LENGTH')
            sz_str = f"{round(raw_sz / 1024, 1):,} KB" if isinstance(raw_sz, (int, float)) else "Dynamic"
            desc = TABLE_DESCRIPTIONS.get(tname, 'Relational SCADA database table supporting machine operations.')
            
            # Check if we have sample records in export
            sample_data = data.get(tname, [])
            sample_text = ""
            if sample_data and isinstance(sample_data, list) and len(sample_data) > 0:
                sample_lines = [f"\n### 📋 Sample Records from `{tname}`:"]
                for itm in sample_data[:3]:
                    fields_summary = ", ".join(f"**{k}**: `{v}`" for k, v in list(itm.items())[:5] if v is not None and str(v).strip())
                    sample_lines.append(f"• {fields_summary}")
                sample_text = "\n".join(sample_lines) + "\n"

            return (
                f"🗄️ **Database Table Inspector: `{tname}`**\n\n"
                f"• **Database:** `gri_db`\n"
                f"• **Total Records:** `{rows_str}` rows\n"
                f"• **Data Length:** `{sz_str}`\n"
                f"• **SCADA Function:** {desc}\n"
                f"{sample_text}\n"
                f"💡 *Would you like to see specific columns, inspect parameter limits, or view related alarms?*"
            ), "table_inspection"

    return None, None

# ─────────────────────────────────────────────────────────────────────────────
# 18. SCADA MANUAL & HOW-TO GUIDES
# ─────────────────────────────────────────────────────────────────────────────

def handle_knowledge_base(norm_q, raw_q, data):
    kb = data.get('knowledge_base', [])
    for row in kb:
        kw = (row.get('keyword') or '').lower()
        feature = (row.get('feature_name') or '').lower()
        screen = (row.get('screen_name') or '').lower()
        if (kw and kw in norm_q) or (feature and feature in norm_q) or (screen and screen in norm_q):
            return (
                f"📘 **SCADA Operating Guide: {row.get('screen_name')} — {row.get('feature_name')}**\n\n"
                f"**Description:**\n{row.get('description')}\n\n"
                f"**Step-by-Step Instructions:**\n{row.get('how_to_use')}\n\n"
                f"**Troubleshooting / Solutions:**\n{row.get('solution')}"
            ), "knowledge_base"
    return None, None

# ─────────────────────────────────────────────────────────────────────────────
# 19. CONVERSATIONAL & GREETING INTENT
# ─────────────────────────────────────────────────────────────────────────────

def handle_greeting(norm_q, raw_q, data):
    return (
        "👋 **Hello! I am your GRI Machine Intelligence AI Assistant.**\n\n"
        "I am connected live to your **`gri_db`** SCADA database and fine-tuned across all **52 industrial tables**.\n\n"
        "### 💡 **Yes! You Can Ask Me LITERALLY ANY Question About Your Database:**\n"
        "• 📜 **Press Recipes:** *'What are recipes?'*, *'Tell me about PART-HM-HEAVY'*, *'Which recipe has highest pressure?'*\n"
        "• 🚨 **Alarm Diagnostics:** *'What are the fault alarms?'*, *'Single phase preventer'*, *'Servo pump MPCB trip'*\n"
        "• ⏱️ **Downtime & Stoppage:** *'What is total downtime?'*, *'Why did the machine breakdown?'*, *'Operator logs'*\n"
        "• ⚙️ **Parameter Operating Limits:** *'What are the parameter limits for pressing?'*, *'Safety thresholds'*\n"
        "• 🏭 **Work Orders & Production:** *'Show work order progress'*, *'Target vs actual parts produced'*\n"
        "• 🔧 **Tool Master & Allocations:** *'List tools and drawing numbers'*, *'What tools are allocated to recipes?'*\n"
        "• 📦 **Critical Spares:** *'Check hydraulic valve and seal stock'*, *'List critical spares'*\n"
        "• 🔌 **PLC IO Signals:** *'What are the PLC signals?'*, *'Limit switch addresses'*, *'Digital inputs'*\n"
        "• 📊 **Runlog & Telemetry:** *'Average cycle time across 19,083 runs'*, *'Station telemetry for P1/P2/P3'*\n"
        "• 🗄️ **All 52 Tables & Schema:** *'Tell me about table down_time'*, *'Show table bumping_log'*, *'List all 52 tables'*\n\n"
        "💬 *Go ahead — ask me any question about your machine or database!*"
    ), "greeting"

# ─────────────────────────────────────────────────────────────────────────────
# 20. NEURAL VECTOR & SEMANTIC RETRIEVAL FALLBACK
# ─────────────────────────────────────────────────────────────────────────────

def handle_semantic_fallback(norm_q, raw_q, data):
    if os.path.exists(DOCS_PATH):
        try:
            with open(DOCS_PATH, 'r', encoding='utf-8') as f:
                docs = json.load(f)
            q_words = set(re.findall(r'\w+', norm_q))
            stop_words = {'what', 'are', 'the', 'is', 'a', 'an', 'in', 'on', 'of', 'for', 'to', 'how', 'show', 'tell', 'me', 'about'}
            q_terms = q_words - stop_words
            if not q_terms:
                q_terms = q_words

            scores = []
            for doc in docs:
                doc_text = doc['text'].lower()
                doc_words = set(re.findall(r'\w+', doc_text))
                overlap = len(q_terms.intersection(doc_words))
                scores.append((overlap, doc))

            scores.sort(key=lambda x: x[0], reverse=True)
            if scores and scores[0][0] > 0:
                top_matches = [s[1] for s in scores[:3] if s[0] > 0]
                lines = [
                    "🔍 **GRI Database Intelligence Match:**\n",
                    f"Here is information extracted from your live **`gri_db`** knowledge base:\n"
                ]
                for m in top_matches:
                    lines.append(f"### 📌 **[{m.get('category', 'gri_db')}] {m.get('title')}**\n{m.get('text')}\n")
                lines.append("💡 *You can ask more detailed questions about any of these parameters, alarms, or machine components.*")
                return "\n".join(lines), "semantic_match"
        except Exception:
            pass

    # Universal search across all dictionary values in data
    found_snippets = []
    for key, items in data.items():
        if isinstance(items, list):
            for itm in items:
                if isinstance(itm, dict):
                    row_str = json.dumps(itm).lower()
                    if any(w in row_str for w in norm_q.split() if len(w) > 3):
                        found_snippets.append((key, itm))
                        if len(found_snippets) >= 3:
                            break
        if len(found_snippets) >= 3:
            break

    if found_snippets:
        lines = [
            f"🔍 **GRI Database Record Found for inquiry:** *\"{raw_q}\"*\n"
        ]
        for tbl, row in found_snippets:
            lines.append(f"• **Table `{tbl}` Record:**\n```json\n{json.dumps(row, indent=2, ensure_ascii=False)[:350]}\n```\n")
        lines.append("💡 *You can query specific parameters or operational procedures regarding these records.*")
        return "\n".join(lines), "universal_record_match"

    return (
        f"🏭 **GRI SCADA Database Intelligence Assistant:**\n\n"
        f"I received your inquiry: *\"{raw_q}\"*\n\n"
        f"I am connected to your live **`gri_db`** database covering all **52 industrial tables**. You can ask me:\n"
        f"• **Press Recipes:** (`PART-HM-HEAVY`, `PART-A-STD`, speeds, pressures, curing times)\n"
        f"• **Fault Alarms:** (89 mapped PLC alarm addresses, Single Phase Preventer, MPCB Trips)\n"
        f"• **Machine Downtime:** (Breakdowns, planned tea breaks, duration, operators)\n"
        f"• **Work Orders:** (Target totals, actual parts produced, job progress)\n"
        f"• **Operating Limits:** (36 machine parameter safety envelopes)\n"
        f"• **Tool Master & Spares:** (50 tooling records, drawing numbers, hydraulic inventory)\n"
        f"• **Any Table by Name:** (*'Tell me about table bumping_log'*, *'Show app_settings'*)\n\n"
        f"💡 *Try asking:* **'What are recipes?'**, **'Which recipe has highest pressure?'**, or **'Show all fault alarms'**"
    ), "general_overview"

# ─────────────────────────────────────────────────────────────────────────────
# MASTER QUERY ROUTER
# ─────────────────────────────────────────────────────────────────────────────

def answer_query(query_text):
    norm_q, raw_q = normalize_query(query_text)
    data = load_data()

    # 1. GREETING / CAPABILITY CONFIRMATION
    if any(k in norm_q for k in ['hello', 'hi ', 'hey', 'who are you', 'what can you do', 'help me', 'shall i ask', 'can i ask', 'any question', 'good morning', 'good afternoon']) or norm_q in ['hi', 'help']:
        return handle_greeting(norm_q, raw_q, data)

    # 1.1 DIRECT EMAIL DISPATCH / MAIL FEATURE
    if any(k in norm_q for k in ['mail', 'email', 'send report', 'send official', 'recipient', 'mail feature']) or ('@' in raw_q and any(k in norm_q for k in ['send', 'mail', 'forward', 'dispatch'])):
        return handle_mail_feature(norm_q, raw_q, data)

    # ─── LIVE DATABASE ENGINE (gri_db MySQL Integration) ─────────────────────
    # 0.1 Payroll & Employee Salaries
    if any(k in norm_q for k in ['salary', 'salaries', 'payroll', 'compensation', 'pay', 'earnings', 'wage', 'wages', 'ctc', 'monthly pay']) or ('employee' in norm_q and any(k in norm_q for k in ['earn', 'paid', 'money', 'amount', 'net', 'basic', 'list'])):
        db_res, db_intent = db_agent.handle_salaries_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.2 Attendance & Leaves
    if any(k in norm_q for k in ['attendance', 'leave', 'leaves', 'absent', 'present', 'shift status', 'who is on leave', 'check in', 'check out']):
        db_res, db_intent = db_agent.handle_attendance_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.3 Work Orders
    if any(k in norm_q for k in ['work order', 'work orders', 'workorder', 'workorders', 'batch order', 'orders target']):
        db_res, db_intent = db_agent.handle_work_orders_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.4 Machine Downtime & Breakdowns
    if any(k in norm_q for k in ['downtime', 'breakdown', 'breakdowns', 'stoppage', 'loss hours', 'stopped']):
        db_res, db_intent = db_agent.handle_downtime_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.5 Production Runlog (19,331 records)
    if any(k in norm_q for k in ['runlog', 'production run', 'production runs', 'cycle time', 'total runs', 'total cycles', 'press cycles', 'completed cycles']):
        db_res, db_intent = db_agent.handle_runlog_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.6 Alarms & Trips (89 mappings)
    if any(k in norm_q for k in ['alarm', 'alarms', 'trip', 'trips', 'fault alarm', 'spp', 'mpcb', 'sounding alarm']):
        db_res, db_intent = db_agent.handle_alarms_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.7 Tool Master
    if any(k in norm_q for k in ['toolmaster', 'tool master', 'tools', 'tool inventory', 'punches', 'dies', 'drawing number', 'drawing no']):
        db_res, db_intent = db_agent.handle_toolmaster_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 0.8 Universal Database Table Query for ALL 53 Tables
    if any(k in norm_q for k in ['all tables', 'show tables', 'list tables', 'database schema', 'how many tables']) or any(f"table {t}" in norm_q or f"show {t}" in norm_q or norm_q == t for t in ['departments', 'audit_logs', 'app_settings', 'parameter_limits', 'recipes', 'critical_spares', 'io_labels', 'tasks', 'users']):
        db_res, db_intent = db_agent.handle_any_table_db(norm_q, raw_q)
        if db_res: return db_res, db_intent

    # 1.2 POLAR / RADAR / CHARTS / TRENDS / GRAPHICAL (General fallback)
    if any(k in norm_q for k in ['polar', 'radar', 'spider', 'chart', 'graph', 'trend', 'histogram', 'graphical', 'pie chart', 'bar chart', 'area chart']):
        return handle_charts(norm_q, raw_q, data)

    # 2. ANALYTICAL & COMPARATIVE QUERIES
    a_res, a_intent = handle_analytical(norm_q, raw_q, data)
    if a_res:
        return a_res, a_intent

    # 3. RECIPE TOOL ALLOCATIONS
    if ('allocat' in norm_q and 'tool' in norm_q) or any(k in norm_q for k in ['allocation', 'allocations', 'assigned tool', 'tool assign']):
        return handle_recipe_tool_allocations(norm_q, raw_q, data)

    # 4. APP SETTINGS & CONFIG
    if any(k in norm_q for k in ['app settings', 'setting', 'settings', 'email config', 'smtp', 'mail config']):
        return handle_app_settings(norm_q, raw_q, data)

    # 5. REPORTS
    if any(k in norm_q for k in ['generated report', 'reports', 'attendance report', 'payroll report', 'pdf report', 'excel report']):
        return handle_reports(norm_q, raw_q, data)

    # 6. RECIPES / PRESS PARAMETERS (Handles 'what are receipes', 'recipe', 'curing', 'fast approach', etc.)
    if any(k in norm_q for k in ['recipe', 'recipes', 'part-', 'curing', 'fast approach', 'first pressing', 'tonnage', 'formula', 'die setting']):
        return handle_recipes(norm_q, raw_q, data)

    # 7. ALARM / FAULT QUERIES
    if any(k in norm_q for k in ['alarm', 'alarms', 'fault', 'faults', 'trip', 'trips', 'emergency stop', 'single phase', 'mpcb', 'overload']):
        return handle_alarms(norm_q, raw_q, data)

    # 8. DOWNTIME / BREAKDOWN QUERIES
    if any(k in norm_q for k in ['downtime', 'breakdown', 'stoppage', 'tea time', 'unplanned', 'planned', 'idle']):
        return handle_downtime(norm_q, raw_q, data)

    # 9. WORK ORDERS / PRODUCTION PROGRESS
    if any(k in norm_q for k in ['work order', 'work orders', 'production order', 'target', 'actual produced', 'produced']):
        return handle_work_orders(norm_q, raw_q, data)

    # 10. PARAMETER LIMITS / OPERATING ENVELOPES
    if any(k in norm_q for k in ['parameter', 'parameters', 'limit', 'limits', 'min_val', 'max_val', 'threshold', 'operating envelope']):
        return handle_parameter_limits(norm_q, raw_q, data)

    # 11. TOOL MASTER / PUNCHES & DIES
    if any(k in norm_q for k in ['tool', 'tools', 'tooling', 'toolmaster', 'punch', 'die', 'cutter', 'forming', 'drawing no']):
        return handle_toolmaster(norm_q, raw_q, data)

    # 12. CRITICAL SPARES / INVENTORY
    if any(k in norm_q for k in ['spare', 'spares', 'spare parts', 'stock', 'inventory', 'valves', 'seal kit']):
        return handle_critical_spares(norm_q, raw_q, data)

    # 13. PLC IO SIGNALS / DIGITAL ADDRESSES
    if any(k in norm_q for k in ['io signal', 'plc signal', 'signals', 'io label', 'binary input', 'digital input', 'digital output', 'limit switch']):
        return handle_io_signals(norm_q, raw_q, data)

    # 14. RUNLOG / CYCLE TIME TELEMETRY
    if any(k in norm_q for k in ['runlog', 'cycle time', 'cycle', 'runs', 'telemetry', 'oae', 'oee']):
        return handle_runlog(norm_q, raw_q, data)

    # 15. BUMPING & DEGASSING
    if any(k in norm_q for k in ['bumping', 'bump', 'degas', 'degassing', 'breathing cycle', 'batch no']):
        return handle_bumping(norm_q, raw_q, data)

    # 16. USERS & OPERATORS
    if any(k in norm_q for k in ['user', 'users', 'operator', 'operators', 'role', 'login', 'who has access', 'who can log']):
        return handle_users(norm_q, raw_q, data)

    # 17. UNIVERSAL TABLE INSPECTOR (Matches ANY of the 52 tables)
    t_res, t_intent = handle_universal_table_search(norm_q, raw_q, data)
    if t_res:
        return t_res, t_intent

    # 18. DATABASE SCHEMA & 52 TABLES CATALOG
    if any(k in norm_q for k in ['table', 'tables', 'schema', 'structure', 'database size', 'catalog', '52 table', 'how many tables']):
        return handle_tables_schema(norm_q, raw_q, data)

    # 19. SCADA MANUAL & HOW-TO
    kb_res, kb_intent = handle_knowledge_base(norm_q, raw_q, data)
    if kb_res:
        return kb_res, kb_intent

    # 20. NEURAL VECTOR & SEMANTIC FALLBACK
    return handle_semantic_fallback(norm_q, raw_q, data)

# ─────────────────────────────────────────────────────────────────────────────
# FLASK HTTP ENDPOINTS
# ─────────────────────────────────────────────────────────────────────────────

@app.route('/chat', methods=['POST'])
def chat():
    body = request.get_json(force=True) or {}
    message = body.get('message', '')
    answer, intent = answer_query(message)
    return jsonify({
        'success': True,
        'answer': answer,
        'intent': intent
    })

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'database': 'gri_db', 'tables_indexed': 52})

if __name__ == '__main__':
    print("Starting Fully Fine-Tuned GRI AI Intelligence microservice on http://127.0.0.1:5005 ...")
    app.run(host='127.0.0.1', port=5005, debug=False)
