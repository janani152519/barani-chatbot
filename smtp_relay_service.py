import socket
import threading
import os
import email
from email import policy
from email.parser import BytesParser
import time
import subprocess
import re
import smtplib

import sys
if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass

HOST = '127.0.0.1'
PORT = 1025

STORAGE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'storage')
MAILBOX_DIR = os.path.join(STORAGE_DIR, 'mailbox', 'admin')
REPORTS_DIR = os.path.join(STORAGE_DIR, 'reports')
LOG_FILE = os.path.join(STORAGE_DIR, 'logs', 'smtp_relay.log')

os.makedirs(MAILBOX_DIR, exist_ok=True)
os.makedirs(REPORTS_DIR, exist_ok=True)
os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)

def log(msg):
    ts = time.strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}"
    try:
        print(line.encode('ascii', errors='replace').decode('ascii'))
    except Exception:
        pass
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(line + '\n')
    except Exception:
        pass

def get_mx_records(domain):
    """Lookup MX records for a domain using Google DNS 8.8.8.8"""
    try:
        cmd = f"nslookup -type=mx {domain} 8.8.8.8"
        output = subprocess.check_output(cmd, shell=True, text=True, timeout=5)
        matches = re.findall(r'mail exchanger = ([\w\.\-]+)', output, re.IGNORECASE)
        if matches:
            return matches
    except Exception as e:
        log(f"MX lookup error for {domain}: {e}")
    return []

def attempt_direct_mx_delivery(from_addr, to_addrs, raw_bytes):
    """Attempt direct transmission to recipient's mail exchanger on port 25"""
    for to in to_addrs:
        if '@' not in to:
            continue
        domain = to.split('@')[1].strip()
        mx_hosts = get_mx_records(domain)
        for mx in mx_hosts:
            try:
                log(f"Attempting direct delivery to {to} via MX {mx}:25 ...")
                smtp = smtplib.SMTP(mx, 25, timeout=10)
                smtp.helo("mail.baranihydraulics.com")
                smtp.sendmail(from_addr, [to], raw_bytes)
                smtp.quit()
                log(f"✅ Direct MX delivery SUCCESS for {to} via {mx}")
                return True
            except Exception as e:
                log(f"Direct MX delivery attempt to {mx} failed: {e}")
    return False

def handle_smtp_session(conn, addr):
    log(f"Client connected from {addr}")
    try:
        conn.sendall(b"220 Barani-Hydraulics-SMTP-Relay Service Ready\r\n")

        mail_from = ""
        rcpt_tos = []
        in_data = False
        data_buffer = bytearray()

        while True:
            line = bytearray()
            while True:
                chunk = conn.recv(1)
                if not chunk:
                    return
                line.extend(chunk)
                if line.endswith(b"\r\n") or line.endswith(b"\n"):
                    break

            if not in_data:
                cmd_text = line.decode('utf-8', errors='ignore').strip()
                log(f">> {cmd_text[:80]}")
                cmd_upper = cmd_text.upper()

                if cmd_upper.startswith("EHLO") or cmd_upper.startswith("HELO"):
                    conn.sendall(b"250-127.0.0.1 Hello\r\n250-SIZE 35880000\r\n250-8BITMIME\r\n250-PIPELINING\r\n250 OK\r\n")
                elif cmd_upper.startswith("MAIL FROM:"):
                    mail_from = cmd_text[10:].strip('<> ')
                    conn.sendall(b"250 2.1.0 Sender OK\r\n")
                elif cmd_upper.startswith("RCPT TO:"):
                    rcpt = cmd_text[8:].strip('<> ')
                    rcpt_tos.append(rcpt)
                    conn.sendall(b"250 2.1.5 Recipient OK\r\n")
                elif cmd_upper == "DATA":
                    in_data = True
                    data_buffer = bytearray()
                    conn.sendall(b"354 Start mail input; end with <CRLF>.<CRLF>\r\n")
                elif cmd_upper == "RSET":
                    mail_from = ""
                    rcpt_tos = []
                    conn.sendall(b"250 2.0.0 OK Reset\r\n")
                elif cmd_upper == "NOOP":
                    conn.sendall(b"250 2.0.0 OK\r\n")
                elif cmd_upper == "QUIT":
                    conn.sendall(b"221 2.0.0 Service closing transmission channel\r\n")
                    break
                else:
                    conn.sendall(b"250 OK\r\n")
            else:
                data_buffer.extend(line)
                if data_buffer.endswith(b"\r\n.\r\n") or data_buffer.endswith(b"\n.\n"):
                    raw_email = bytes(data_buffer[:-3]) # strip trailing .\r\n
                    in_data = False

                    # Parse message
                    try:
                        msg = BytesParser(policy=policy.default).parsebytes(raw_email)
                        subject = msg.get('Subject', '(No Subject)')
                        msg_id = time.strftime('%Y%m%d_%H%M%S_') + str(int(time.time() * 1000) % 10000)

                        attachments = []
                        # Extract attachments
                        for part in msg.walk():
                            fn = part.get_filename()
                            if fn:
                                part_payload = part.get_payload(decode=True)
                                if part_payload:
                                    att_path = os.path.join(REPORTS_DIR, fn)
                                    with open(att_path, 'wb') as f_att:
                                        f_att.write(part_payload)
                                    attachments.append(fn)

                        # Save message copy to Admin Mailbox
                        eml_filename = f"{msg_id}.eml"
                        eml_path = os.path.join(MAILBOX_DIR, eml_filename)
                        with open(eml_path, 'wb') as f_eml:
                            f_eml.write(raw_email)

                        meta_filename = f"{msg_id}.json"
                        meta_path = os.path.join(MAILBOX_DIR, meta_filename)
                        
                        # Extract text or html body
                        body_text = ""
                        if msg.is_multipart():
                            for part in msg.walk():
                                ctype = part.get_content_type()
                                if ctype in ['text/plain', 'text/html']:
                                    payload = part.get_payload(decode=True)
                                    if payload:
                                        body_text = payload.decode('utf-8', errors='ignore')
                                        break
                        else:
                            payload = msg.get_payload(decode=True)
                            if payload:
                                body_text = payload.decode('utf-8', errors='ignore')

                        meta = {
                            "id": msg_id,
                            "timestamp": time.strftime('%Y-%m-%d %H:%M:%S'),
                            "from": mail_from or msg.get('From', ''),
                            "to": rcpt_tos or [msg.get('To', '')],
                            "subject": subject,
                            "attachments": attachments,
                            "eml_file": eml_filename,
                            "body_preview": body_text[:500] if body_text else "",
                            "delivered_local": True
                        }
                        import json
                        with open(meta_path, 'w', encoding='utf-8') as f_meta:
                            json.dump(meta, f_meta, indent=2)

                        log(f"📥 Received and stored email #{msg_id} to Admin Mailbox! Subject: '{subject}' | To: {rcpt_tos} | Attachments: {attachments}")

                        # If external recipient, attempt direct MX delivery in background thread
                        threading.Thread(
                            target=attempt_direct_mx_delivery,
                            args=(mail_from or "admin@barani.com", rcpt_tos, raw_email),
                            daemon=True
                        ).start()

                    except Exception as e:
                        log(f"Error parsing email: {e}")

                    conn.sendall(b"250 2.0.0 OK: Message accepted for delivery\r\n")

    except Exception as e:
        log(f"Session error: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass

def run_smtp_server():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((HOST, PORT))
    server.listen(10)
    log(f"🚀 Barani Hydraulics SMTP Relay Service running on {HOST}:{PORT} ...")

    while True:
        try:
            conn, addr = server.accept()
            t = threading.Thread(target=handle_smtp_session, args=(conn, addr), daemon=True)
            t.start()
        except Exception as e:
            log(f"Accept error: {e}")
            time.sleep(1)

if __name__ == '__main__':
    run_smtp_server()
