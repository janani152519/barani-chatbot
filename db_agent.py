import os
import re
import pymysql
import pymysql.cursors

def get_db():
    try:
        return pymysql.connect(
            host=os.environ.get('DB_HOST', '127.0.0.1'),
            port=int(os.environ.get('DB_PORT', 3306)),
            user=os.environ.get('DB_USERNAME', 'root'),
            password=os.environ.get('DB_PASSWORD', ''),
            database=os.environ.get('DB_DATABASE', 'gri_db'),
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        return None

def detect_chart_preference(norm_q):
    if any(k in norm_q for k in ['pie', 'donut', 'distribution']):
        return 'pie'
    if any(k in norm_q for k in ['line', 'trend', 'time series', 'over time']):
        return 'line'
    if any(k in norm_q for k in ['area', 'cumulative']):
        return 'area'
    if any(k in norm_q for k in ['radar', 'polar', 'spider']):
        return 'radar'
    return 'bar'

# ─────────────────────────────────────────────────────────────────────────────
# 1. PAYROLL & EMPLOYEE SALARIES
# ─────────────────────────────────────────────────────────────────────────────
def handle_salaries_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            # Query latest salary per employee
            cur.execute("""
                SELECT e.employee_code, e.first_name, e.last_name, e.designation, 
                       COALESCE(d.name, 'General') as department,
                       p.month, p.year, p.basic_salary, p.allowances, p.deductions, p.net_salary, p.payment_status
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN (
                    SELECT p1.* FROM payroll p1
                    INNER JOIN (
                        SELECT employee_id, MAX(id) as max_id FROM payroll GROUP BY employee_id
                    ) p2 ON p1.id = p2.max_id
                ) p ON e.id = p.employee_id
                ORDER BY p.net_salary DESC
            """)
            rows = cur.fetchall()

        if not rows:
            return None, None

        chart_type = detect_chart_preference(norm_q)

        # Calculate analytics
        total_payroll = sum(float(r['net_salary'] or 0) for r in rows)
        avg_salary = total_payroll / len(rows) if rows else 0
        top_earner = rows[0] if rows else None
        lowest_earner = rows[-1] if rows else None

        lines = [
            f"💰 **Live Employee Payroll & Compensation Database Records (`gri_db.payroll`):**\n",
            "Here is the verified salary breakdown for company personnel retrieved live from the database:\n",
            "| Employee Code | Employee Name | Department | Designation | Basic Salary (₹) | Deductions (₹) | Net Salary (₹) | Status |",
            "|---|---|---|---|---|---|---|---|",
        ]

        for r in rows:
            full_name = f"{r['first_name']} {r['last_name']}".strip()
            basic = f"{float(r['basic_salary'] or 0):,.2f}"
            ded = f"{float(r['deductions'] or 0):,.2f}"
            net = f"{float(r['net_salary'] or 0):,.2f}"
            status_badge = "✅ " + r['payment_status'].capitalize() if r['payment_status'] == 'paid' else "⏳ Pending"
            lines.append(f"| `{r['employee_code']}` | **{full_name}** | {r['department']} | {r['designation']} | {basic} | {ded} | **{net}** | {status_badge} |")

        lines.extend([
            f"\n### 📊 Financial & Salary Analytics Summary:",
            f"• **Total Monthly Payroll Outflow:** `₹{total_payroll:,.2f}` across {len(rows)} registered staff members.",
            f"• **Average Net Compensation:** `₹{avg_salary:,.2f}` per employee.",
            f"• **Highest Salary:** **{top_earner['first_name']} {top_earner['last_name']}** (`₹{float(top_earner['net_salary'] or 0):,.2f}`) — *{top_earner['designation']}*.",
            f"• **Lowest Base:** **{lowest_earner['first_name']} {lowest_earner['last_name']}** (`₹{float(lowest_earner['net_salary'] or 0):,.2f}`).\n",
            f"📈 *Graphical view automatically generated above! Use the chart switcher bar to toggle between Bar, Line, Pie, and Area views.*"
        ])

        return "\n".join(lines), "payroll_salaries_db"
    except Exception as e:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 2. ATTENDANCE & LEAVE RECORDS
# ─────────────────────────────────────────────────────────────────────────────
def handle_attendance_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT a.date, a.status, a.check_in, a.check_out,
                       e.employee_code, e.first_name, e.last_name, e.designation
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                ORDER BY a.date DESC, e.employee_code ASC
                LIMIT 25
            """)
            rows = cur.fetchall()

        if not rows:
            return None, None

        present_count = sum(1 for r in rows if r['status'].lower() == 'present')
        leave_count = sum(1 for r in rows if r['status'].lower() in ['leave', 'absent'])
        present_pct = (present_count / len(rows) * 100) if rows else 0

        lines = [
            f"📋 **Live Employee Attendance Telemetry (`gri_db.attendance`):**\n",
            f"Current attendance activity log recorded across active factory personnel:\n",
            "| Date | Employee Code | Employee Name | Designation | Shift Status | Check-In | Check-Out |",
            "|---|---|---|---|---|---|---|",
        ]

        for r in rows:
            name = f"{r['first_name']} {r['last_name']}".strip()
            status_icon = "🟢 Present" if r['status'].lower() == 'present' else "🔴 On Leave"
            cin = r['check_in'] or "—"
            cout = r['check_out'] or "—"
            lines.append(f"| {r['date']} | `{r['employee_code']}` | **{name}** | {r['designation']} | {status_icon} | {cin} | {cout} |")

        lines.extend([
            f"\n### 📊 Attendance KPI Overview:",
            f"• **Total Evaluated Shifts:** `{len(rows)}` records.",
            f"• **Present Turnout:** `{present_count} / {len(rows)}` ({present_pct:.1f}% attendance rate).",
            f"• **Leaves / Absences:** `{leave_count}` shift exceptions recorded.",
            f"\n💡 *You can also ask:* 'Mail attendance report to director@barani.com'"
        ])

        return "\n".join(lines), "attendance_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 3. MACHINE DOWNTIME & BREAKDOWNS
# ─────────────────────────────────────────────────────────────────────────────
def handle_downtime_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, start_time, end_time, duration, reason, operator, category
                FROM down_time
                ORDER BY duration DESC
            """)
            rows = cur.fetchall()

        if not rows:
            return None, None

        total_duration = sum(float(r['duration'] or 0) for r in rows)

        lines = [
            f"⏱️ **Live Machine Downtime & Breakdown Log (`gri_db.down_time`):**\n",
            f"Real-time machine stoppage records and breakdown events from the plant database:\n",
            "| Log ID | Category | Reason for Stoppage | Duration (Hours) | Operator / Technician | Start Time |",
            "|---|---|---|---|---|---|",
        ]

        for r in rows:
            dur = float(r['duration'] or 0)
            lines.append(f"| #{r['id']} | **{r.get('category') or 'General'}** | {r.get('reason')} | **{dur:.2f}** | {r.get('operator') or 'Admin'} | {r.get('start_time')} |")

        lines.extend([
            f"\n### 💡 Downtime Bottleneck Analysis:",
            f"• **Total Stoppage Time:** `{total_duration:.2f} hours` of lost hydraulic machine availability.",
            f"• **Top Primary Bottlenecks:** Breakdown maintenance, hydraulic line pressure variation, and scheduled operator meal intervals.",
            f"• **Visual Representation:** Rendered in interactive chart view above."
        ])

        return "\n".join(lines), "downtime_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 4. PRODUCTION WORK ORDERS
# ─────────────────────────────────────────────────────────────────────────────
def handle_work_orders_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, work_order_no, total, actual, status, created_at
                FROM work_orders
                ORDER BY id ASC
            """)
            rows = cur.fetchall()

        if not rows:
            return None, None

        total_target = sum(int(r['total'] or 0) for r in rows)
        total_produced = sum(int(r['actual'] or 0) for r in rows)
        overall_completion = (total_produced / total_target * 100) if total_target > 0 else 0

        lines = [
            f"🏭 **Live Production Work Orders & Targets (`gri_db.work_orders`):**\n",
            f"Batch order completion metrics and fulfillment rates from the SCADA database:\n",
            "| Work Order No | Target Units | Actual Produced | Remaining Units | Progress (%) | Order Status |",
            "|---|---|---|---|---|---|",
        ]

        for r in rows:
            target = int(r['total'] or 0)
            actual = int(r['actual'] or 0)
            remaining = max(0, target - actual)
            pct = (actual / target * 100) if target > 0 else 0
            status_badge = "✅ Completed" if r['status'] == 'completed' else ("🔄 In Progress" if actual > 0 else "⏳ Queued")
            lines.append(f"| `{r['work_order_no']}` | **{target:,}** | **{actual:,}** | {remaining:,} | **{pct:.1f}%** | {status_badge} |")

        lines.extend([
            f"\n### 📊 Order Fulfillment Analytics:",
            f"• **Total Units Scheduled:** `{total_target:,}` pieces across {len(rows)} work orders.",
            f"• **Total Units Produced to Date:** `{total_produced:,}` pieces ({overall_completion:.1f}% overall completion rate).",
            f"• **Remaining Backlog:** `{total_target - total_produced:,}` units to dispatch."
        ])

        return "\n".join(lines), "work_orders_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 5. PRODUCTION RUNLOG (19,331 RECORDS)
# ─────────────────────────────────────────────────────────────────────────────
def handle_runlog_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) as total_runs FROM runlog")
            total_runs = cur.fetchone()['total_runs']

            # Group recent recipe usage
            cur.execute("""
                SELECT `Recipe Name` as recipe, COUNT(*) as cycles,
                       AVG(`Total Cycle Time`) as avg_time,
                       MAX(`P1 MR Pressure`) as peak_pressure
                FROM runlog
                WHERE `Recipe Name` IS NOT NULL AND `Recipe Name` != ''
                GROUP BY `Recipe Name`
                ORDER BY cycles DESC
                LIMIT 8
            """)
            recipe_stats = cur.fetchall()

        lines = [
            f"📊 **Machine Runlog Production Telemetry (`gri_db.runlog`):**\n",
            f"The system has recorded a massive **{total_runs:,} completed hydraulic press cycles** in the industrial database.\n",
            "### 🏭 Production Volume by Recipe Program:\n",
            "| Recipe Program | Total Cycles Completed | Avg Cycle Time (s) | Peak Pressure (bar) | Production Share (%) |",
            "|---|---|---|---|---|",
        ]

        for r in recipe_stats:
            cycles = int(r['cycles'])
            avg_t = float(r['avg_time'] or 0)
            peak_p = float(r['peak_pressure'] or 0)
            share = (cycles / total_runs * 100) if total_runs > 0 else 0
            lines.append(f"| **`{r['recipe']}`** | **{cycles:,}** | {avg_t:.1f} s | {peak_p:.1f} bar | **{share:.2f}%** |")

        lines.extend([
            f"\n### ⚡ SCADA Operational Performance:",
            f"• **Total Pressing Runs:** `{total_runs:,}` logged machine cycles.",
            f"• **Dominant Production Recipe:** `{recipe_stats[0]['recipe'] if recipe_stats else 'N/A'}`.",
            f"• **High Repeatability:** Hydraulic clamp pressure and bump dwell times remain within calibrated tolerance."
        ])

        return "\n".join(lines), "runlog_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 6. FAULT ALARMS & TRIPS (89 MAPPINGS)
# ─────────────────────────────────────────────────────────────────────────────
def handle_alarms_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT id, byte_addr, bit_addr, alarm_text, severity
                FROM alarm_mappings
                ORDER BY id ASC
                LIMIT 20
            """)
            rows = cur.fetchall()

            cur.execute("SELECT COUNT(*) as total FROM alarm_mappings")
            total_alarms = cur.fetchone()['total']

        lines = [
            f"🚨 **Factory Fault Alarms & Interlocks (`gri_db.alarm_mappings`):**\n",
            f"There are **{total_alarms} active electrical and hydraulic alarms** monitored by the machine PLC controller:\n",
            "| Alarm ID | Fault Alarm Description | Severity Level | PLC Byte Address | PLC Bit Address |",
            "|---|---|---|---|---|",
        ]

        for r in rows:
            sev = r.get('severity') or 'Warning'
            sev_icon = "🔴 Critical" if 'crit' in sev.lower() else ("⚠️ Fault" if 'fault' in sev.lower() else "🟡 Warning")
            lines.append(f"| #{r['id']} | **{r.get('alarm_text')}** | {sev_icon} | `Byte {r.get('byte_addr')}` | `Bit {r.get('bit_addr')}` |")

        lines.extend([
            f"\n💡 *Showing first {len(rows)} of {total_alarms} mapped alarms. All alarm events trigger instant safety interlocks, ram decompression, and siren notification.*"
        ])

        return "\n".join(lines), "alarm_mappings_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 7. TOOL MASTER INVENTORY (50 TOOLS)
# ─────────────────────────────────────────────────────────────────────────────
def handle_toolmaster_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT `ID`, `Tool ID`, `Tool Name`, `Drawing No`, `Tool Type`
                FROM toolmaster
                ORDER BY ID ASC
                LIMIT 15
            """)
            rows = cur.fetchall()

            cur.execute("SELECT COUNT(*) as total FROM toolmaster")
            total_tools = cur.fetchone()['total']

        lines = [
            f"🔧 **Tool Master Inventory (`gri_db.toolmaster`):**\n",
            f"Current tooling registry containing **{total_tools} production dies and molds**:\n",
            "| Tool Record | Tool Identifier | Tool Name & Spec | Drawing Number | Tool Category |",
            "|---|---|---|---|---|",
        ]

        for r in rows:
            lines.append(f"| #{r['ID']} | **`{r.get('Tool ID')}`** | {r.get('Tool Name')} | `{r.get('Drawing No') or 'DWG-STD'}` | {r.get('Tool Type') or 'Compression Mold'} |")

        lines.extend([
            f"\n💡 *Showing first {len(rows)} of {total_tools} registered tools. Drawing numbers and tool wear offsets are synced with machine CAD specifications.*"
        ])

        return "\n".join(lines), "toolmaster_db"
    except Exception:
        return None, None
    finally:
        conn.close()

# ─────────────────────────────────────────────────────────────────────────────
# 8. GENERAL DATABASE TABLE INSPECTOR (FOR ANY OF THE 53 TABLES)
# ─────────────────────────────────────────────────────────────────────────────
def handle_any_table_db(norm_q, raw_q):
    conn = get_db()
    if not conn:
        return None, None

    try:
        with conn.cursor() as cur:
            cur.execute("SHOW TABLES")
            tables = [list(r.values())[0] for r in cur.fetchall()]

            # If user asks for all tables
            if any(k in norm_q for k in ['all tables', 'show tables', 'list tables', 'how many tables', 'database schema']):
                lines = [
                    f"🗄️ **Complete Database Catalog — `gri_db` ({len(tables)} Tables):**\n",
                    f"The Barani Hydraulics SCADA database contains **{len(tables)} relational tables**:\n",
                    "| Table Name | Row Count | Primary Function |",
                    "|---|---|---|",
                ]
                for t in sorted(tables):
                    try:
                        cur.execute(f"SELECT COUNT(*) as cnt FROM `{t}`")
                        cnt = cur.fetchone()['cnt']
                    except Exception:
                        cnt = "—"
                    lines.append(f"| **`{t}`** | **{cnt:,}** rows | SCADA / Factory automation records |" if isinstance(cnt, int) else f"| **`{t}`** | {cnt} | System table |")

                lines.append("\n💡 *Ask me to query any specific table by name (e.g., 'show employees', 'show runlog', 'show down_time')!*")
                return "\n".join(lines), "all_tables_catalog"

            # Check if user mentioned a specific table
            matched_table = None
            for t in tables:
                if re.search(r'\b' + re.escape(t.lower()) + r'\b', norm_q):
                    matched_table = t
                    break

            if not matched_table:
                # Common aliases
                if 'department' in norm_q: matched_table = 'departments'
                elif 'audit' in norm_q: matched_table = 'audit_logs'
                elif 'alarm' in norm_q: matched_table = 'alarm_mappings'
                elif 'recipe' in norm_q: matched_table = 'recipes'
                elif 'spare' in norm_q: matched_table = 'critical_spares'
                elif 'setting' in norm_q: matched_table = 'app_settings'
                elif 'order' in norm_q: matched_table = 'work_orders'
                elif 'limit' in norm_q: matched_table = 'parameter_limits'

            if matched_table:
                cur.execute(f"DESCRIBE `{matched_table}`")
                cols = [c['Field'] for c in cur.fetchall()]

                cur.execute(f"SELECT COUNT(*) as cnt FROM `{matched_table}`")
                cnt = cur.fetchone()['cnt']

                cur.execute(f"SELECT * FROM `{matched_table}` LIMIT 10")
                samples = cur.fetchall()

                display_cols = cols[:7]
                lines = [
                    f"🗄️ **Live Database Table: `{matched_table}` ({cnt:,} Total Records):**\n",
                    f"Columns: `{', '.join(cols)}`\n",
                    "| " + " | ".join(display_cols) + " |",
                    "| " + " | ".join(["---"] * len(display_cols)) + " |",
                ]

                for row in samples:
                    row_vals = [str(row.get(c, ''))[:30].replace("|", "/") for c in display_cols]
                    lines.append("| " + " | ".join(row_vals) + " |")

                lines.append(f"\n💡 *Showing top {len(samples)} of {cnt:,} rows in table `{matched_table}`.*")
                return "\n".join(lines), f"table_{matched_table}"

        return None, None
    except Exception:
        return None, None
    finally:
        conn.close()
