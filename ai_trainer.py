import json
import os
import numpy as np
from sentence_transformers import SentenceTransformer

def train_and_index_from_json():
    export_path = 'ai_index/gri_db_export.json'
    if not os.path.exists(export_path):
        raise FileNotFoundError(f"Export file {export_path} not found. Run export_db_for_ai.php first.")

    with open(export_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    knowledge_documents = []

    # 1. Knowledge Base (SCADA Manual)
    for row in data.get('knowledge_base', []):
        doc_text = (
            f"Screen: {row.get('screen_name')}. Feature: {row.get('feature_name')}. Keyword: {row.get('keyword')}. "
            f"Description: {row.get('description')}. How to Use: {row.get('how_to_use')}. "
            f"Solution / Troubleshooting: {row.get('solution')}."
        )
        knowledge_documents.append({
            'source': 'knowledge_base',
            'id': row.get('id'),
            'category': 'SCADA Operating Manual',
            'title': f"{row.get('screen_name')} - {row.get('feature_name')}",
            'text': doc_text,
            'data': row
        })

    # 2. Alarm Mappings
    for a in data.get('alarm_mappings', []):
        doc_text = f"Alarm Fault: '{a.get('alarm_text')}' with severity '{a.get('severity')}'. Byte address: {a.get('byte_addr')}, Bit address: {a.get('bit_addr')}. ID: {a.get('id')}."
        knowledge_documents.append({
            'source': 'alarm_mappings',
            'id': a.get('id'),
            'category': 'Machine Alarms & Faults',
            'title': f"Alarm: {a.get('alarm_text')}",
            'text': doc_text,
            'data': a
        })

    # 3. Parameter Limits
    for p in data.get('parameter_limits', []):
        doc_text = f"Parameter: '{p.get('parameter_key')}' has minimum limit of {p.get('min_val')} and maximum limit of {p.get('max_val')}."
        knowledge_documents.append({
            'source': 'parameter_limits',
            'id': p.get('id'),
            'category': 'Parameter Limits',
            'title': f"Parameter: {p.get('parameter_key')}",
            'text': doc_text,
            'data': p
        })

    # 4. Recipes
    for r in data.get('recipes', []):
        doc_text = (
            f"Recipe Name: '{r.get('name')}', Product No: '{r.get('product_no')}', PLC Record: '{r.get('plc_record_name')}'. "
            f"Fast approach position: {r.get('fast_app_position')}, Fast approach speed: {r.get('fast_app_speed')}. "
            f"First pressing position: {r.get('first_pressing_position')}, pressure: {r.get('first_pressing_pressure')}, "
            f"speed: {r.get('first_pressing_speed')}, curing time: {r.get('curing_time_sec')} seconds."
        )
        knowledge_documents.append({
            'source': 'recipes',
            'id': r.get('id'),
            'category': 'Press Recipes',
            'title': f"Recipe: {r.get('name')}",
            'text': doc_text,
            'data': r
        })

    # 5. Down Time
    for dt in data.get('down_time', []):
        doc_text = (
            f"Downtime event: Reason '{dt.get('reason')}', Category '{dt.get('category')}', "
            f"Duration: {dt.get('duration')} hours. Operator: '{dt.get('operator')}'. "
            f"Start time: {dt.get('start_time')}, End time: {dt.get('end_time')}."
        )
        knowledge_documents.append({
            'source': 'down_time',
            'id': dt.get('id'),
            'category': 'Machine Downtime',
            'title': f"Downtime: {dt.get('reason')} by {dt.get('operator')}",
            'text': doc_text,
            'data': dt
        })

    # 6. Work Orders
    for w in data.get('work_orders', []):
        doc_text = (
            f"Work Order: '{w.get('work_order_no')}', Status: '{w.get('status')}', "
            f"Target Total: {w.get('total')}, Actual Produced: {w.get('actual')}. Created at: {w.get('created_at')}."
        )
        knowledge_documents.append({
            'source': 'work_orders',
            'id': w.get('id'),
            'category': 'Production Work Orders',
            'title': f"Work Order: {w.get('work_order_no')}",
            'text': doc_text,
            'data': w
        })

    # 7. Tool Master
    for t in data.get('toolmaster', []):
        doc_text = (
            f"Tool: '{t.get('Tool Name')}', Tool ID: '{t.get('Tool ID')}', Type: '{t.get('Tool Type')}', "
            f"Drawing No: '{t.get('Drawing No')}'. Cumulative Quantity: {t.get('Cum Qty')}, "
            f"Target: {t.get('Target')}, Diff Qty: {t.get('Diff Qty')}."
        )
        knowledge_documents.append({
            'source': 'toolmaster',
            'id': t.get('ID'),
            'category': 'Tooling Master',
            'title': f"Tool: {t.get('Tool Name')} ({t.get('Tool ID')})",
            'text': doc_text,
            'data': t
        })

    # 8. Critical Spares
    for sp in data.get('critical_spares', []):
        doc_text = (
            f"Critical Spare Part: '{sp.get('part_name')}', Description: '{sp.get('part_description')}', "
            f"Category: '{sp.get('category')}', UOM: '{sp.get('uom')}', In Stock Quantity: {sp.get('quantity')}."
        )
        knowledge_documents.append({
            'source': 'critical_spares',
            'id': sp.get('id'),
            'category': 'Critical Spares Inventory',
            'title': f"Spare: {sp.get('part_name')}",
            'text': doc_text,
            'data': sp
        })

    # 9. Users & Roles
    for u in data.get('users', []):
        doc_text = f"Registered User: '{u.get('username')}', Role: '{u.get('role')}', Email: '{u.get('email')}', Active: {u.get('is_active')}, User ID: {u.get('id')}."
        knowledge_documents.append({
            'source': 'users',
            'id': u.get('id'),
            'category': 'System Users & Roles',
            'title': f"User: {u.get('username')}",
            'text': doc_text,
            'data': u
        })

    # 10. Runlog Stats
    run_stats = data.get('runlog_stats')
    if run_stats:
        doc_text = (
            f"Production Runlog Overview: Total production run cycles recorded is {run_stats.get('total_runs')}. "
            f"Average cycle time: {round(float(run_stats.get('avg_cycle') or 0), 2)} seconds. "
            f"Maximum cycle time: {run_stats.get('max_cycle')} seconds."
        )
        knowledge_documents.append({
            'source': 'runlog_stats',
            'id': 1,
            'category': 'Production Runlog Analytics',
            'title': "Production Runlog Overview",
            'text': doc_text,
            'data': run_stats
        })

    # 11. IO Labels (PLC Digital Signals)
    for io in data.get('io_labels', []):
        doc_text = f"PLC IO Signal: '{io.get('label_text')}' on Byte: {io.get('byte_addr')}, Bit: {io.get('bit_addr')}, Type: {io.get('type')}. ID: {io.get('id')}."
        knowledge_documents.append({
            'source': 'io_labels',
            'id': io.get('id'),
            'category': 'PLC Digital IO Signals',
            'title': f"IO: {io.get('label_text')}",
            'text': doc_text,
            'data': io
        })

    # 12. App Settings
    for s in data.get('app_settings', []):
        doc_text = f"Application Setting: '{s.get('key')}' = '{s.get('value')}'. Description: {s.get('description')}."
        knowledge_documents.append({
            'source': 'app_settings',
            'id': s.get('id'),
            'category': 'System Configuration',
            'title': f"Setting: {s.get('key')}",
            'text': doc_text,
            'data': s
        })

    # 13. All Tables Catalog (52 tables)
    for tbl in data.get('all_tables', []):
        doc_text = f"Database Table: '{tbl.get('TABLE_NAME')}' in gri_db contains {tbl.get('TABLE_ROWS')} rows and data size of {tbl.get('DATA_LENGTH')} bytes."
        knowledge_documents.append({
            'source': 'all_tables',
            'id': tbl.get('TABLE_NAME'),
            'category': 'Database Schema Catalog',
            'title': f"Table: {tbl.get('TABLE_NAME')}",
            'text': doc_text,
            'data': tbl
        })

    print(f"Total knowledge facts extracted from gri_db: {len(knowledge_documents)}")
    
    with open('ai_index/documents.json', 'w', encoding='utf-8') as f:
        json.dump(knowledge_documents, f, indent=2, default=str)

    print("Loading SentenceTransformer model 'all-MiniLM-L6-v2'...")
    model = SentenceTransformer('all-MiniLM-L6-v2')
    texts = [doc['text'] for doc in knowledge_documents]
    print(f"Encoding {len(texts)} facts into neural vector embeddings...")
    embeddings = model.encode(texts, show_progress_bar=True, normalize_embeddings=True)

    np.save('ai_index/embeddings.npy', embeddings)
    print("SUCCESS: gri_db neural knowledge index is fully built and ready!")

if __name__ == '__main__':
    train_and_index_from_json()
