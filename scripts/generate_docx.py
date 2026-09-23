import sys
import json
import os
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import parse_xml
from docx.oxml.ns import nsdecls

def set_cell_background(cell, fill_color):
    """Set background color of a table cell (HEX without #)"""
    tcPr = cell._tc.get_or_add_tcPr()
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_color}"/>')
    tcPr.append(shd)

def create_executive_form_docx(config, doc):
    title = config.get('title', 'Amended True-Up Payroll Report')
    report_type = config.get('report_type', 'GENERAL').upper()
    is_payroll = (report_type == 'PAYROLL' or 'ncci_rows' in config or 'true-up' in title.lower() or 'payroll' in title.lower())

    policy_no = config.get('policy_number', 'BWC-8492014-0' if is_payroll else 'BHI-DOC-8492014-0')
    legal_name = config.get('legal_name', 'BARANI HYDRAULICS (INDIA) PVT. LTD.')
    trading_name = config.get('trading_name', 'BARANI HYDRAULICS / GRI SCADA INTELLIGENCE')
    mailing_addr = config.get('mailing_address', 'SF No. 248/2, Trichy Road, Sulur')
    email_addr = config.get('email_address', 'baranihydraulics@gmail.com')
    telephone = config.get('telephone', '(0422) 268-9100')
    city = config.get('city', 'Coimbatore')
    state = config.get('state', 'TN / OH')
    zip_code = config.get('zip_code', '641402')
    period_from = config.get('period_from', '01/08/2025')
    period_through = config.get('period_through', '31/08/2025')
    reason = config.get('reason_for_change', config.get('notes', 'Operational audit reconciliation and SCADA telemetry verification for reported period.'))
    sign_name = config.get('signature_name', 'Managing Director / Officer')
    sign_title = config.get('signature_title', 'Authorized Officer')
    date_str = config.get('date', '08/31/2025')

    classification_title = config.get('classification_title', 'NCCI manual classification' if is_payroll else 'Operational & Telemetry Classification')
    col_headers = config.get('col_headers', [
        "Manual", "Type code", "Description", "Number of employees", "Original reported payroll", "Actual payroll"
    ] if is_payroll else [
        "Manual / Code", "Type Code", "Description", "Units / Count", "Original / Spec", "Actual / Logged"
    ])

    # Clean header titles (replace <br> with newline)
    clean_col_headers = [h.replace('<br>', ' ') for h in col_headers]

    # Top Header: Logo / Corporate Identity (Left) & Document Title / Policy Number (Right)
    head_table = doc.add_table(rows=1, cols=2)
    head_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    c0 = head_table.rows[0].cells[0]
    p0 = c0.paragraphs[0]
    
    logo_path = os.path.join(os.path.dirname(__file__), '..', 'storage', 'assets', 'logo.jpg')
    if not os.path.exists(logo_path):
        logo_path = os.path.join(os.path.dirname(__file__), '..', 'frontend', 'public', 'logo.jpg')
    if not os.path.exists(logo_path):
        logo_path = os.path.join(os.path.dirname(__file__), '..', 'frontend', 'src', 'assets', 'logo.jpg')
        
    if os.path.exists(logo_path):
        try:
            r_img = p0.add_run()
            r_img.add_picture(logo_path, width=Inches(1.2))
            p0.add_run("\n")
        except Exception:
            pass

    r_barani = p0.add_run("BARANI HYDRAULICS\n")
    r_barani.font.name = 'Arial'
    r_barani.font.size = Pt(14)
    r_barani.font.bold = True
    r_barani.font.color.rgb = RGBColor(30, 58, 138)
    sub_title = "(India) Pvt. Ltd. • Payroll & Compensation Audit\nSF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402\n" if is_payroll else "(India) Pvt. Ltd. • GRI SCADA Machine Intelligence\nSF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402\n"
    r_sub = p0.add_run(sub_title)
    r_sub.font.name = 'Calibri'
    r_sub.font.size = Pt(8.5)
    r_sub.font.color.rgb = RGBColor(71, 85, 105)
    inst_text = (
        "Instructions:\n• Official payroll audit and employee wage verification report.\n• Submit to Barani HR & Finance Department for filing."
        if is_payroll else
        "Instructions:\n• Complete this official audit report in its entirety along with operational remarks.\n• Submit this report to the Barani Plant Head or authorized corporate officer."
    )

    r_inst = p0.add_run(inst_text)
    r_inst.font.name = 'Calibri'
    r_inst.font.size = Pt(8)

    c1 = head_table.rows[0].cells[1]
    p1 = c1.paragraphs[0]
    p1.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    r_title = p1.add_run(f"{title}\n\n")
    r_title.font.name = 'Times New Roman'
    r_title.font.size = Pt(13.5)
    r_title.font.bold = True
    r_pol = p1.add_run(f"Policy number: {policy_no}\n")
    r_pol.font.name = 'Calibri'
    r_pol.font.size = Pt(10)
    r_pol.font.bold = True

    # Identity grid (3x2 Table)
    p_sp = doc.add_paragraph()
    p_sp.paragraph_format.space_before = Pt(4)
    p_sp.paragraph_format.space_after = Pt(2)

    id_table = doc.add_table(rows=3, cols=2)
    id_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    
    id_table.rows[0].cells[0].paragraphs[0].text = f"Legal business name:\n{legal_name}"
    id_table.rows[0].cells[1].paragraphs[0].text = f"Trading name or DBA:\n{trading_name}"
    id_table.rows[1].cells[0].paragraphs[0].text = f"Mailing address:\n{mailing_addr}"
    id_table.rows[1].cells[1].paragraphs[0].text = f"Email: {email_addr} | Tel: {telephone}"
    id_table.rows[2].cells[0].paragraphs[0].text = f"City: {city}"
    id_table.rows[2].cells[1].paragraphs[0].text = f"State: {state} | ZIP: {zip_code}"

    for r in id_table.rows:
        for cell in r.cells:
            for p in cell.paragraphs:
                for run in p.runs:
                    run.font.name = 'Calibri'
                    run.font.size = Pt(8.5)

    # Reporting / Payroll Period
    p_per = doc.add_paragraph()
    p_per.paragraph_format.space_before = Pt(6)
    p_per.paragraph_format.space_after = Pt(4)
    r_per = p_per.add_run(f"Payroll period:  from  {period_from}  through  {period_through}")
    r_per.font.name = 'Calibri'
    r_per.font.size = Pt(9.5)
    r_per.font.bold = True

    # Primary 6-Column Classification Table
    ncci_rows = config.get('ncci_rows', config.get('classification_rows', [
        {'manual': '8810', 'type': 'REG', 'desc': 'Clerical Office Employees NOC (HR / Admin)', 'emp': 2, 'orig': 95000.0, 'actual': 100000.0},
        {'manual': '8803', 'type': 'REG', 'desc': 'Auditing, Accounting & Financial Ops', 'emp': 2, 'orig' : 125000.0, 'actual': 130000.0},
        {'manual': '8601', 'type': 'REG', 'desc': 'Engineers & Technical Support Services', 'emp': 1, 'orig': 45000.0, 'actual': 48000.0},
        {'manual': '3632', 'type': 'REG', 'desc': 'Machine Shop & Hydraulic Equipment Mfg', 'emp': 2, 'orig': 60000.0, 'actual': 62500.0},
    ]))

    h_ncci = doc.add_heading(classification_title, level=3)
    h_ncci.paragraph_format.space_before = Pt(6)
    h_ncci.paragraph_format.space_after = Pt(3)

    table = doc.add_table(rows=len(ncci_rows) + 2, cols=6)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(clean_col_headers):
        cell = table.rows[0].cells[i]
        cell.paragraphs[0].text = h
        set_cell_background(cell, "1E3A8A")
        for run in cell.paragraphs[0].runs:
            run.font.name = 'Arial'
            run.font.size = Pt(8.5)
            run.font.bold = True
            run.font.color.rgb = RGBColor(255, 255, 255)

    tot_emp = 0
    tot_orig = 0.0
    tot_act = 0.0
    has_numeric = False

    for r_idx, row in enumerate(ncci_rows):
        cells = table.rows[r_idx + 1].cells
        emp_raw = row.get('emp', 0)
        try:
            emp = int(emp_raw)
            tot_emp += emp
            emp_str = str(emp) if emp > 0 else str(emp_raw)
        except Exception:
            emp_str = str(emp_raw)

        orig_raw = row.get('orig', '')
        act_raw = row.get('actual', '')

        if is_payroll:
            try:
                orig_val = float(orig_raw)
                act_val = float(act_raw)
                tot_orig += orig_val
                tot_act += act_val
                has_numeric = True
                orig_fmt = f"₹{orig_val:,.2f}"
                act_fmt = f"₹{act_val:,.2f}"
            except Exception:
                orig_fmt = str(orig_raw)
                act_fmt = str(act_raw)
        else:
            try:
                orig_val = float(orig_raw)
                act_val = float(act_raw)
                tot_orig += orig_val
                tot_act += act_val
                has_numeric = True
                orig_fmt = f"{orig_val:,.2f}"
                act_fmt = f"{act_val:,.2f}"
            except Exception:
                orig_fmt = str(orig_raw)
                act_fmt = str(act_raw)

        cells[0].paragraphs[0].text = str(row.get('manual', ''))
        cells[1].paragraphs[0].text = str(row.get('type', 'REG'))
        cells[2].paragraphs[0].text = str(row.get('desc', ''))
        cells[3].paragraphs[0].text = emp_str
        cells[4].paragraphs[0].text = orig_fmt
        cells[5].paragraphs[0].text = act_fmt

        bg = "F8FAFC" if r_idx % 2 == 1 else "FFFFFF"
        for c in cells:
            set_cell_background(c, bg)
            for p in c.paragraphs:
                for run in p.runs:
                    run.font.name = 'Calibri'
                    run.font.size = Pt(8.5)

    # Total Row
    tot_cells = table.rows[-1].cells
    tot_cells[0].paragraphs[0].text = "TOTAL:" if is_payroll else "TOTAL AUDITED SUMMARY:"
    tot_cells[3].paragraphs[0].text = str(tot_emp) if tot_emp > 0 else ""
    if is_payroll:
        tot_cells[4].paragraphs[0].text = f"₹{tot_orig:,.2f}"
        tot_cells[5].paragraphs[0].text = f"₹{tot_act:,.2f}"
    elif has_numeric and (tot_orig > 0 or tot_act > 0):
        tot_cells[4].paragraphs[0].text = f"{tot_orig:,.2f}"
        tot_cells[5].paragraphs[0].text = f"{tot_act:,.2f}"
    else:
        tot_cells[4].paragraphs[0].text = "Design Baseline"
        tot_cells[5].paragraphs[0].text = "Compliant / Verified"

    for c in tot_cells:
        set_cell_background(c, "E2E8F0")
        for p in c.paragraphs:
            for run in p.runs:
                run.font.name = 'Arial'
                run.font.size = Pt(8.5)
                run.font.bold = True

    # Reason for change / Remarks
    doc.add_paragraph().paragraph_format.space_before = Pt(6)
    p_rsn = doc.add_paragraph()
    r_lbl = p_rsn.add_run("Reason for change:\n")
    r_lbl.font.bold = True
    r_lbl.font.size = Pt(9)
    r_val = p_rsn.add_run(reason)
    r_val.font.italic = True
    r_val.font.size = Pt(8.5)

    # Certification
    p_cert = doc.add_paragraph()
    p_cert.paragraph_format.space_before = Pt(6)
    r_c_hdr = p_cert.add_run("Certification:\n")
    r_c_hdr.font.bold = True
    r_c_hdr.font.size = Pt(8.5)

    cert_text = config.get('certification_text', (
        "I hereby certify that the payroll records and wage figures reported herein are accurate, verified against company accounts, "
        "and reflect all employee disbursements for the stated period.\n"
        "By my signature, I certify I have the authority to approve and execute this official document on behalf of "
        "Barani Hydraulics (India) Pvt. Ltd."
    ) if is_payroll else (
        "I hereby certify that the operational metrics, telemetry data, and system logs reported herein are true, accurate, "
        "and verified against SCADA supervisory records for the stated period.\n"
        "By my signature, I certify I have the authority to execute this document, and that all facts set forth herein "
        "are true and correct to the best of my knowledge and belief."
    ))
    r_c_txt = p_cert.add_run(cert_text)
    r_c_txt.font.size = Pt(7.5)
    r_c_txt.font.name = 'Calibri'

    # Signature Block
    sign_table = doc.add_table(rows=1, cols=2)
    sign_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    sign_table.rows[0].cells[0].paragraphs[0].text = f"Signature and title:\n[AUTHORIZED SIGNATURE] {sign_name} — {sign_title}"
    sign_table.rows[0].cells[1].paragraphs[0].text = f"Date:\n{date_str}"
    for c in sign_table.rows[0].cells:
        for p in c.paragraphs:
            for run in p.runs:
                run.font.name = 'Calibri'
                run.font.size = Pt(8.5)
                run.font.bold = True

    # Form Footer
    p_ftr = doc.add_paragraph()
    p_ftr.paragraph_format.space_before = Pt(8)
    form_code = config.get('form_code', "BWC-7578 (Rev. Oct. 6, 2016) | RPS-Amend P/R" if is_payroll else f"BHI-DOC-7578 (Rev. {date_str}) | SCADA Intelligence Official Record")
    r_ftr = p_ftr.add_run(form_code)
    r_ftr.font.name = 'Arial'
    r_ftr.font.size = Pt(7.5)
    r_ftr.font.bold = True

    # Secondary Detailed Audit Ledger Table (if records exist)
    raw_records = config.get('raw_records', config.get('records', []))
    if raw_records and isinstance(raw_records, list) and len(raw_records) > 0 and isinstance(raw_records[0], dict):
        rec_headers = list(raw_records[0].keys())
        h_rec = doc.add_heading(f"Detailed Operational Audit Ledger ({len(raw_records)} Records)", level=3)
        h_rec.paragraph_format.space_before = Pt(12)
        h_rec.paragraph_format.space_after = Pt(4)

        rec_table = doc.add_table(rows=len(raw_records) + 1, cols=len(rec_headers))
        rec_table.alignment = WD_TABLE_ALIGNMENT.CENTER

        for idx, rh in enumerate(rec_headers):
            cell = rec_table.rows[0].cells[idx]
            cell.paragraphs[0].text = rh.replace('_', ' ').title()
            set_cell_background(cell, "1E3A8A")
            for run in cell.paragraphs[0].runs:
                run.font.name = 'Arial'
                run.font.size = Pt(8)
                run.font.bold = True
                run.font.color.rgb = RGBColor(255, 255, 255)

        for r_i, rec in enumerate(raw_records):
            row_cells = rec_table.rows[r_i + 1].cells
            bg = "F8FAFC" if r_i % 2 == 1 else "FFFFFF"
            for c_i, rh in enumerate(rec_headers):
                row_cells[c_i].paragraphs[0].text = str(rec.get(rh, ''))
                set_cell_background(row_cells[c_i], bg)
                for run in row_cells[c_i].paragraphs[0].runs:
                    run.font.name = 'Calibri'
                    run.font.size = Pt(8)

def create_docx_report(config):
    doc = docx.Document()

    # Set 0.5 inch margins for official form appearance
    for section in doc.sections:
        section.top_margin = Inches(0.5)
        section.bottom_margin = Inches(0.5)
        section.left_margin = Inches(0.6)
        section.right_margin = Inches(0.6)

    # Universal executive payroll form layout for ALL report types
    create_executive_form_docx(config, doc)

    output_path = config.get('output_path', 'storage/reports/report.docx')
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    doc.save(output_path)
    print(f"SUCCESS:{os.path.abspath(output_path)}")

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python generate_docx.py <config.json>")
        sys.exit(1)
    config_file = sys.argv[1]
    with open(config_file, 'r', encoding='utf-8') as f:
        cfg = json.load(f)
    create_docx_report(cfg)
