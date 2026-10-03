"""Turn catalog_mapping.csv (from build_mapping.py) into the importer CSV.

Every row gets its parent product and one term name per WooCommerce attribute.
Term names reuse the names already on the site wherever one exists.

Outputs:
  import.csv             -> upload this in WooCommerce -> Katalog-Import
  ATTRIBUTE-REVIEW.md    -> for review: terms per product, fill values, unclear codes
Exits non-zero if a token can't be mapped or two SKUs in one product share
the same attribute combination (the shop could not tell them apart).
"""
import csv, collections, pathlib, re, sys

HERE = pathlib.Path(__file__).parent
TIERS = [1, 5, 10, 20, 50, 100, 250, 500]

# group code -> (parent product ID on staging, attributes the product uses, in dropdown order)
PARENTS = {
    'NAT-SHOP': (3338, 'Atlantis Namensschild', ['model', 'frontscheibe', 'format', 'fenster', 'tragerfarbe', 'flachenfarbe', 'befestigung']),
    'NAC-SHOP': (22964, 'Alucontur Namensschild', ['model', 'format', 'oberflache', 'befestigung']),
    'NLI-SHOP': (8156, 'Woodline Namensschilder', ['model', 'format', 'holzart', 'befestigung']),
    'NMA-SHOP': (6709, 'Materia Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NLC-SHOP': (6432, 'LaserContur Namensschilder', ['model', 'format', 'flachenfarbe', 'befestigung']),
    'NPA-SHOP': (7124, 'Papyrus Namensschilder', ['model', 'format', 'fenster', 'flachenfarbe', 'befestigung']),
    'NDS-SHOP': (6244, 'DesignStudio Namensschilder', ['model', 'format', 'befestigung']),
    'NIM-SHOP': (6269, 'Image Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NXL-SHOP': (8075, 'X-Style Namensschilder', ['model', 'format', 'tragerfarbe', 'flachenfarbe', 'befestigung']),
    'NCL-SHOP': (6129, 'Classic Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NSL-SHOP': (8010, 'Slim Namensschilder', ['model', 'format', 'flachenfarbe', 'befestigung']),
    'NTO-SHOP': (8036, 'Top Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NAP-SHOP': (2188, 'AluProfil Namensschilder', ['model', 'format', 'oberflache', 'befestigung']),
    'NBU-SHOP': (5642, 'Bussiness Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NQU-SHOP': (7990, 'Quadro Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NSC-SHOP': (8002, 'Screen Namensschilder', ['model', 'farbe', 'befestigung']),
    'NTR-SHOP': (8058, 'Translex Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NFR-SHOP': (5884, 'Chalk Board Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NPP-SHOP': (7980, 'ProfiPlexx Namensschilder', ['model', 'format', 'befestigung']),
    'NPR-SHOP': (7841, 'Prestige Namensschilder', ['model', 'format', 'farbe', 'befestigung']),
    'NFO-SHOP': (6391, 'Folio Namensschilder', ['model', 'befestigung']),
}
ATTR_LABEL = {'model': 'Model', 'format': 'Format', 'fenster': 'Fenster', 'tragerfarbe': 'Trägerfarbe',
              'flachenfarbe': 'Flächenfarbe', 'befestigung': 'Befestigung', 'oberflache': 'Oberfläche',
              'holzart': 'Holzart', 'farbe': 'Farbe', 'frontscheibe': 'Frontscheibe'}
# value used when a product has the attribute but this SKU has no such option
FILL = {'fenster': 'ohne Fenster', 'flachenfarbe': 'ohne Flächendruck', 'frontscheibe': 'Standard'}

M_DRUCK, M_LASER = 'Druck', 'Lasergravur'
M_DRUCK_SK, M_LASER_SK = 'Druck & Sonderkontur', 'Lasergravur & Sonderkontur'
M_LOGO_D = 'Logo gedruckt / Name zur Selbstbeschriftung'
M_LOGO_L = 'Logo lasergraviert / Name zur Selbstbeschriftung'
M_LOGO_D_SK = 'Logo gedruckt & Sonderkontur / Name zur Selbstbeschriftung'
M_LOGO_L_SK = 'Logo lasergraviert & Sonderkontur / Name zur Selbstbeschriftung'
M_SELF = 'Komplette Selbstbeschriftung'
MODEL_BY_QUALITY = {  # (quality, print method) -> model
    ('Comfort', 'D'): M_DRUCK, ('Comfort', 'L'): M_LASER,
    ('Exklusiv', 'D'): M_DRUCK_SK, ('Exklusiv', 'L'): M_LASER_SK,
    ('Classic', 'D'): M_LOGO_D, ('Classic', 'L'): M_LOGO_L,
    ('Professional', 'D'): M_LOGO_D_SK, ('Professional', 'L'): M_LOGO_L_SK,
}

FASTENING = [  # checked in order against the description, most specific first
    (r'MAGNETBR[ÜU]CKE, F[ÄA]HNCHEN', 'Magnetbrücke mit Fähnchen'),
    (r'MAGNETBR', 'Magnetbrücke'),
    (r'INFOMAG-PL', 'Infomag-Magnet (drehsicher & extra stark)'),
    (r'INFOMAG', 'Infomag-Magnet (drehsicher)'),
    (r'DUOMAG-PLUS', 'DUOMAG-Plus Magnetsystem'),
    (r'PROMAG-S', 'Promag-S Magnet'),
    (r'PROMAG', 'PROMAG Magnetsystem'),
    (r'KOMBI-CLIP', 'Kombi-Clip'),
    (r'KROKO', 'Krokodilklemme'),
    (r'ANSTECKN|A\.-NADEL|NADEL', 'Anstecknadel'),
]
CARRIER = {  # colour code in the SKU -> Trägerfarbe term (codes as used by the existing site variations)
    'C12': 'Coating silber', 'C42': 'Coating gold (lackiert)', 'E12': 'Chrom matt (galvanisiert)',
    'G10': 'Weiß', 'G21': 'Lichtgrau', 'G23': 'Anthrazit', 'G32': 'Dunkelblau', 'G60': 'Rot', 'G80': 'Schwarz',
    'T00': 'Klar transparent', 'T10': 'Weiß transluzent', 'T30': 'Blau transparent', 'T60': 'Rot transparent',
}
SURFACE = [(r'SILBER[- ]MATT', 'Silber matt'), (r'SCHWARZ[- ]MATT', 'Schwarz matt'), (r'V2A', 'V2A (Edelstahl)'),
           (r'EDELSTAHL', 'Edelstahl-Look'), (r'WEI(SS|ß)', 'Weiß (Thermolack)')]
WOOD = ['AHORN', 'BAMBUS', 'EICHE', 'KIRSCHE', 'WALNUSS', 'NUSS', 'BUCHE', 'ZEBRANO', 'WENGE', 'OLIVE']
COLOUR = [  # description fragment -> Farbe term (Materia layers, U-series surfaces)
    (r'SCHW\.?/WEI', 'Schwarz/Weiß'), (r'WEI(SS|ß)/SCHW', 'Weiß/Schwarz'),
    (r'SILB(ER|\.)? ?GEB\.?/SCHW', 'Silber geb./Schwarz'), (r'GOLD( GEB\.?)?/SCHW', 'Gold geb./Schwarz'),
    (r'BLAU/WEI', 'Blau/Weiß'), (r'ROT/WEI', 'Rot/Weiß'), (r'GR[ÜU]N/WEI', 'Grün/Weiß'),
    (r'SCHWARZ/KLAR', 'Schwarz/Klar'), (r'COAT\.? SILBER/KLAR', 'Coating silber/Klar'), (r'WEISS/KLAR|WEIß/KLAR', 'Weiß/Klar'),
    (r'FOLIE SCHW', 'Folie schwarz matt'), (r'SILBER MATT', 'Silber matt'), (r'SILBER GEB', 'Silber gebürstet'),
    (r'GOLD GEB', 'Gold gebürstet'), (r'SILBER IMD', 'Silber (IMD)'), (r'COAT(ING|\.)? SILBER|SILBER COAT', 'Coating silber'),
    (r'KLAR', 'Klar transparent'), (r'LICHTGRAU', 'Lichtgrau'), (r'ANTHRAZIT', 'Anthrazit'),
    (r'DUNKELBLAU', 'Dunkelblau'), (r'\bROT\b', 'Rot'), (r'SCHWARZ', 'Schwarz'), (r'WEI(SS|ß)', 'Weiß'), (r'SILBER', 'Silber'),
]
WINDOW = {'8D': '8 mm durchgehend', '10D': '10 mm durchgehend', '12D': '12 mm durchgehend',
          'HF': 'halbe Fläche', 'HF-DI': 'halbe Fläche (DI)', 'OF': 'volle Fläche (ohne Flächendruck)'}
RANGE_FORMAT = {'18': '50-70 mm x 15-25 mm (bis 18 cm²)', '30': '50-75 mm x 15-40 mm (bis 30 cm²)',
                '50': '50-100 mm x 15-50 mm (bis 50 cm²)'}

problems, notes = [], collections.Counter()


def first_match(table, text):
    for pat, name in table:
        if re.search(pat, text):
            return name
    return None


def fmt_size(text):
    m = re.search(r'<\s*(\d+)\s*CM', text)
    if m:
        return RANGE_FORMAT.get(m.group(1))
    m = re.search(r'(\d+(?:,\d+)?)\s*X\s*(\d+(?:,\d+)?)', text)
    return f'{m.group(1)}x{m.group(2)} mm' if m else None


def method(r):
    area, t = r['area_code'], r['search_terms'].upper()
    if area.startswith('E'):
        return 'E'
    if area.startswith('L') or 'GRAV' in t:
        return 'L'
    if area.startswith('D') or 'DIGI' in t:
        return 'D'
    return ''


def model_for(r, group):
    qm = re.match(r'(Comfort|Exklusiv|Classic|Professional|PC|V2A)', r['quality'])
    q, m, desc = (qm.group(1) if qm else ''), method(r), r['description']
    if r['variant_code'] == 'OAW':
        return 'Serienfertigung (OAW)'
    if group == 'NAC-SHOP':
        return {'D': 'Digitaldruck Namensschilder', 'E': 'Untereloxaldruck (100% kratzfest)', 'L': 'Lasergravur'}[m]
    if group == 'NTO-SHOP' and m:
        return 'Logo oben / Name zur Selbstbeschriftung' if 'S1' in desc else 'Logo links / Name zur Selbstbeschriftung'
    if q.startswith('PC'):
        return M_SELF
    if (q, m) in MODEL_BY_QUALITY:
        return MODEL_BY_QUALITY[(q, m)]
    if group == 'NDS-SHOP':  # DesignStudio V2A: fully printed or engraved
        return {'D': M_DRUCK, 'L': M_LASER}.get(m)
    if not q:  # U-series, Screen, Folio, ProfiPlexx: logo printed or blank for self-labelling
        return M_LOGO_D if m == 'D' else M_SELF
    return None


def build(r):
    group, t = r['group_code'], r['search_terms'].upper()
    pid, _, attrs = PARENTS[group]
    v = {}
    v['model'] = model_for(r, group)
    size = fmt_size(t)
    if size and group == 'NAC-SHOP' and 'curved' in r['description']:
        size += ' (gewölbt)'
    if size and group == 'NDS-SHOP':
        shape = t.split(';')[1].strip().lower() if ';' in t else ''
        size = f'{size}, {shape[:1].upper()}{shape[1:]}' if shape else size
    v['format'] = size
    v['befestigung'] = first_match(FASTENING, t)
    if 'tragerfarbe' in attrs:
        code = re.search(r'(' + '|'.join(CARRIER) + ')', r['excel_nr'])
        v['tragerfarbe'] = CARRIER.get(code.group(1)) if code else None
    if 'flachenfarbe' in attrs:
        area = r['area_code']
        if area == 'D9':
            v['flachenfarbe'] = 'ohne Flächendruck'
        elif area == 'D2' or re.search(r'F:? ?SILB', t):
            v['flachenfarbe'] = 'Silber'
        elif area == 'D1':
            v['flachenfarbe'] = 'Weiß'
    if 'fenster' in attrs and r['variant_code'] in WINDOW:
        v['fenster'] = WINDOW[r['variant_code']]
    if 'frontscheibe' in attrs and r['quality'].startswith('PC'):
        v['frontscheibe'] = 'Acrylglas' if 'Acryl' in r['quality'] else 'Polykarbonat'
    if 'oberflache' in attrs:
        v['oberflache'] = first_match(SURFACE, t)
    if 'holzart' in attrs:
        v['holzart'] = next((w.capitalize() for w in WOOD if w in t), None)
    if 'farbe' in attrs:
        body = ';'.join(p for p in t.split(';')[1:2]) or t
        v['farbe'] = first_match(COLOUR, body) or first_match(COLOUR, t)
    out = {}
    for a in attrs:
        val = v.get(a)
        if not val and a in FILL:
            val = FILL[a]; notes[f'{PARENTS[group][1]}: {ATTR_LABEL[a]} = "{val}" (filled)'] += 1
        if not val:
            problems.append(f'{r["sku"]}: no {ATTR_LABEL[a]} from "{r["search_terms"]}" / {r["description"]}')
        out[a] = val or ''
    return pid, out


src = list(csv.DictReader(open(HERE / 'catalog_mapping.csv', encoding='utf-8-sig'), delimiter=';'))
all_attrs = list(ATTR_LABEL)
rows, skipped, combos = [], [], collections.defaultdict(list)
for r in src:
    if not any(r[f'net_{t}'] for t in TIERS):
        skipped.append(f'{r["sku"]} ({r["description"]}; {r["search_terms"]}): no price in any tier')
        continue
    pid, vals = build(r)
    key = (pid, tuple(sorted(vals.items())))
    combos[key].append(r['sku'])
    rows.append({'sku': r['sku'], 'parent_id': pid, 'parent_title': PARENTS[r['group_code']][1],
                 'attributes': ','.join('pa_' + a for a in PARENTS[r['group_code']][2]),
                 'group_code': r['group_code'], 'description': r['description'], 'weight_kg': r['weight_kg'],
                 **{f'attr_{a}': vals.get(a, '') for a in all_attrs},
                 **{f'net_{t}': r[f'net_{t}'] for t in TIERS}})
for (pid, combo), skus in combos.items():
    if len(skus) > 1:
        problems.append(f'parent {pid}: same attributes for {", ".join(skus)} -> {dict(combo)}')

with open(HERE / 'import.csv', 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0]), delimiter=';')
    w.writeheader(); w.writerows(rows)

# review document
by_parent = collections.defaultdict(list)
for r in rows:
    by_parent[(r['parent_id'], r['parent_title'])].append(r)
md = ['# Attribute review', '',
      f'Generated from `catalog_mapping.csv`: {len(rows)} SKUs in {len(by_parent)} products. '
      'Term names in **bold** do not exist on the site yet and will be created.', '']
existing = set(pathlib.Path(HERE / 'site_terms.txt').read_text(encoding='utf-8').splitlines()) if (HERE / 'site_terms.txt').exists() else set()
for (pid, title), lst in sorted(by_parent.items(), key=lambda x: -len(x[1])):
    md += [f'## {title} (ID {pid}) — {len(lst)} SKUs', '']
    for a in PARENTS[lst[0]['group_code']][2]:
        c = collections.Counter(r[f'attr_{a}'] for r in lst)
        vals = ', '.join((f'{k} ({n})' if f'pa_{a}|{k}' in existing else f'**{k}** ({n})') for k, n in sorted(c.items()))
        md.append(f'- **{ATTR_LABEL[a]}:** {vals}')
    md.append('')
md += ['## Fill values', '', 'The product uses this attribute but these SKUs have no such option, so they get a neutral value:', '']
md += [f'- {k}: {n} SKUs' for k, n in sorted(notes.items())]
md += ['', '## Left out of the import', ''] + ([f'- {p}' for p in skipped] or ['None.'])
md += ['', '## Problems', ''] + ([f'- {p}' for p in problems] or ['None.'])
(HERE / 'ATTRIBUTE-REVIEW.md').write_text('\n'.join(md) + '\n', encoding='utf-8')
print(f'{len(rows)} rows, {len(by_parent)} products, {len(problems)} problems')
kinds = collections.Counter(re.sub(r'^\S+: (no \S+).*', r'\1', p) if ': no ' in p else 'duplicate combination' for p in problems)
for k, n in kinds.most_common():
    print(f'  {n:4} x {k}   e.g. {next(p for p in problems if (k in p or k == "duplicate combination" and "same attributes" in p))[:170]}')
sys.exit(1 if problems else 0)
