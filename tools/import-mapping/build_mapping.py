"""Build the catalog mapping from the client's SKU Excel (source of truth).

Output: catalog_mapping.csv (one row per SKU = Excel column 'Nr.+Variantencode') and REPORT.md.
Prices are net (EUR excl. MwSt), exactly as in the Excel.
"""
import csv, re, collections, pathlib, sys, openpyxl

HERE = pathlib.Path(__file__).parent
# Excel path as first argument; default: next to the repo (the Excel is client data, not committed)
SRC = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else HERE.parent.parent / 'SKU_Namensschilder mit Varianten und Staffelpreisen.xlsx'
TIERS = [1, 5, 10, 20, 50, 100, 250, 500]
FLAGS = ['10D', '12D', '8D', 'HF', 'HF-DI', 'ND', 'OAW', 'OF', 'ST']

# Befestigung suffix -> label. 'verified' = clear from the Excel description text.
FASTENING = {
    'A': 'Anstecknadel', 'P': 'Promag Magnet', 'I': 'Infomag Magnet', 'IP': 'Infomag-Plus Magnet',
    'K': 'Kombi-Clip', 'DP': 'Duomag-Plus Magnet', 'M1': 'Promag-S Magnet',
    'AC': 'Anstecknadel (Business/Prestige)', 'MC': 'Magnetbrücke', 'MCF': 'Magnetbrücke + Fähnchen',
}
AREA = {'D1': 'Digitaldruck, Fläche weiß/farbig/ohne', 'D2': 'Digitaldruck, Fläche silber', 'D9': 'Sonder (ohne Flächendruck)',
        'E1': 'Untereloxaldruck', 'L1': 'Lasergravur', 'S1': 'S1'}

ws = openpyxl.load_workbook(SRC, data_only=True).active
out, warn = [], collections.Counter()
for r in ws.iter_rows(min_row=4, values_only=True):
    if not r[0]:
        continue
    nr, desc, search, weight, pg, vcode, sku = r[:7]
    flags = [h for i, h in enumerate(FLAGS) if r[7 + i]]
    prices = list(r[16:24])
    parts = nr.split('-')
    area = parts[1] if len(parts) > 1 else ''
    fast = parts[2] if len(parts) > 2 else ''
    line, _, quality = (desc or '').partition('|')
    toks = [t.strip() for t in re.split(r'[;,]', search or '') if t.strip()]
    size = toks[0] if toks else ''
    price_map = dict(zip(TIERS, prices))
    first = next((t for t in TIERS if price_map[t] is not None), None)
    notes = []
    if first is None:
        notes.append('NO PRICE'); warn['no price'] += 1
    if fast and fast not in FASTENING:
        notes.append('unknown fastening'); warn['unknown fastening'] += 1
    if vcode in ('ND', 'HF-DI', 'HF', 'OAW', 'ST'):
        notes.append('code meaning unconfirmed: ' + vcode)
    out.append({
        'sku': sku, 'excel_nr': nr, 'variant_code': vcode or '', 'flags': '+'.join(flags),
        'group_code': pg, 'description': desc, 'line': line.strip() if quality else desc,
        'quality': quality.strip(), 'search_terms': search, 'size': size,
        'area_code': area, 'area': AREA.get(area, ''), 'fastening_code': fast, 'fastening': FASTENING.get(fast, ''),
        'weight_kg': weight, 'min_qty': first or '',
        **{f'net_{t}': ('' if price_map[t] is None else price_map[t]) for t in TIERS}, 'notes': '; '.join(notes),
    })

with open(HERE / 'catalog_mapping.csv', 'w', newline='', encoding='utf-8-sig') as f:
    w = csv.DictWriter(f, fieldnames=list(out[0]), delimiter=';')
    w.writeheader(); w.writerows(out)

c = collections.Counter
rep = [f'# Catalog mapping report\n\nSource: `{SRC.name}`\n\n- SKUs (rows): {len(out)}',
       f'- Unique SKUs: {len({o["sku"] for o in out})}',
       f'- Rows without any price: {warn["no price"]}',
       f'- Rows with unknown fastening suffix: {warn["unknown fastening"]}',
       '\n## By product group code\n'] + [f'- {k}: {v}' for k, v in c(o['group_code'] for o in out).most_common()]
rep += ['\n## By line\n'] + [f'- {k}: {v}' for k, v in c(o['line'] for o in out).most_common()]
rep += ['\n## By variant code\n'] + [f'- {k or "(none)"}: {v}' for k, v in c(o['variant_code'] for o in out).most_common()]
rep += ['\n## By fastening\n'] + [f'- {k or "(none)"} {FASTENING.get(k, "")}: {v}' for k, v in c(o['fastening_code'] for o in out).most_common()]
rep += ['\n## By minimum quantity (first priced tier)\n'] + [f'- {k}: {v}' for k, v in c(o['min_qty'] for o in out).most_common()]
(HERE / 'REPORT.md').write_text('\n'.join(rep) + '\n', encoding='utf-8')
print('rows', len(out), dict(warn))
