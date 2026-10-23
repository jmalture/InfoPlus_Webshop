#!/usr/bin/env python3
"""
Builds the content field groups of the Infoplus theme into wp-content/themes/infoplus/acf-json/.

Run from anywhere:  python3 -I tools/build-acf-json.py
Never edit the JSON by hand: change this script and re-run it, then commit both.

Every field gets a default_value equal to the text the old Elementor page shows
today, read from the verbatim exports in legacy/ wherever the text lives there,
so a page renders exactly the old content before anyone opens it in the admin.
Repeater, gallery and relationship defaults are not applied by SCF itself; the
theme does that (inc/fields.php: ip_field() for the front end, the
acf/load_value filter in inc/content.php for the edit screen).

Keys are stable (group_ip_<page>, field_ip_<page>_<name>): labels can change
freely without losing saved values. Do not rename a key once a client saved data.

Staging IDs used as defaults (checked read-only on 2026-10-20):
front page 8 (page_on_front), Unternehmen 2662, Kontakt 3040, FAQ 2667;
products Alucontur 22964, Atlantis 3338; attachment IDs from the Elementor data.
"""
import html
import json
import re
import sys
import time
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LEGACY = ROOT / 'legacy'
OUT = ROOT / 'wp-content' / 'themes' / 'infoplus' / 'acf-json'
STAGING = 'https://navajowhite-rhinoceros-679020.hostingersite.com'

FRONT_PAGE_ID = 8
UNTERNEHMEN_ID = 2662
KONTAKT_ID = 3040
FAQ_ID = 2667

ICON_CHOICES = {
    'tag': 'Prozent / Rabatt',
    'truck': 'Lieferwagen / Versand',
    'clock': 'Uhr / Zeit',
    'layers': 'Stapel / Menge',
    'chat': 'Sprechblase / Beratung',
    'award': 'Auszeichnung / Erfahrung',
    'pen': 'Stift / Gestaltung',
    'zap': 'Blitz / schnell',
    'check': 'Haken',
    'phone': 'Telefon',
    'mail': 'E-Mail',
}


# ── Reading the legacy exports ────────────────────────────────────────────

def elementor(name):
    data = json.loads((LEGACY / 'elementor' / name).read_text(encoding='utf-8'))
    return data['elementor_data']


def widgets(elements):
    """All widgets of an Elementor tree in document order."""
    for el in elements:
        if el.get('elType') == 'widget':
            yield el
        yield from widgets(el.get('elements', []))


def by_id(elements, wid):
    for w in widgets(elements):
        if w['id'] == wid:
            return w
    for el in elements:  # containers too
        if el['id'] == wid:
            return el
        found = by_id(el.get('elements', []), wid)
        if found:
            return found
    return None


def clean_html(s):
    """Old editor HTML to clean prose: no wrapper divs, no style spans, relative links."""
    s = s.replace(STAGING, '')
    s = re.sub(r'</?div[^>]*>', '', s)
    s = re.sub(r'</?span[^>]*>', '', s)
    s = s.replace('<br />', '<br>')
    s = re.sub(r'<p>\s*(&nbsp;| )?\s*</p>', '', s)
    s = s.strip()
    if not s.startswith('<'):
        s = '<p>' + s + '</p>'
    return s


def plain(s):
    """Editor HTML of a single paragraph to plain text (for textarea fields)."""
    s = re.sub(r'<br\s*/?>', '\n', s)
    s = re.sub(r'</p>\s*<p>', '\n\n', s)
    s = re.sub(r'<[^>]+>', '', s)
    return html.unescape(s).replace(' ', ' ').strip()


def text(elements, wid, key='editor'):
    return by_id(elements, wid)['settings'][key]


def img(elements, wid):
    return int(by_id(elements, wid)['settings']['image']['id'])


def bg(elements, wid):
    return int(by_id(elements, wid)['settings']['background_image']['id'])


# ── Field factories ───────────────────────────────────────────────────────

class Group:
    def __init__(self, slug):
        self.slug = slug

    def key(self, name):
        return f'field_ip_{self.slug}_{name}'

    def tab(self, name, label):
        return {'key': self.key('tab_' + name), 'label': label, 'name': '', 'type': 'tab',
                'placement': 'top', 'endpoint': 0}

    def f(self, name, ftype, label, default=None, parent=None, **extra):
        field = {
            'key': self.key(f'{parent}_{name}' if parent else name),
            'label': label,
            'name': name,
            'type': ftype,
            'instructions': extra.pop('instructions', ''),
            'required': 0,
            'wrapper': {'width': str(extra.pop('width', '')), 'class': '', 'id': ''},
        }
        if default is not None:
            field['default_value'] = default
        if ftype == 'textarea':
            field.setdefault('rows', extra.pop('rows', 3))
            field['new_lines'] = ''
        if ftype == 'wysiwyg':
            field.update({'tabs': 'visual', 'toolbar': 'basic', 'media_upload': 0, 'delay': 1})
        if ftype == 'image':
            field.update({'return_format': 'id', 'preview_size': 'medium', 'library': 'all',
                          'mime_types': 'jpg,jpeg,png,webp,svg'})
        if ftype == 'gallery':
            field.update({'return_format': 'id', 'preview_size': 'thumbnail', 'library': 'all',
                          'mime_types': 'jpg,jpeg,png,webp,svg', 'insert': 'append'})
        if ftype == 'true_false':
            field.update({'ui': 1, 'ui_on_text': 'Ja', 'ui_off_text': 'Nein'})
        if ftype == 'select':
            field.update({'allow_null': 0, 'multiple': 0, 'ui': 0, 'return_format': 'value'})
        field.update(extra)
        return field

    def show(self, name, label):
        return self.f(name + '_show', 'true_false', f'Abschnitt „{label}“ anzeigen', 1,
                      instructions='Aus = der Abschnitt wird ausgeblendet, die Inhalte bleiben gespeichert.')

    def repeater(self, name, label, subs, default, button, collapsed, **extra):
        """subs: list of (name, type, label, extra) tuples."""
        sub_fields = []
        for sname, stype, slabel, sextra in subs:
            sub_fields.append(self.f(sname, stype, slabel, parent=name, **dict(sextra)))
        collapsed_key = self.key(f'{name}_{collapsed}')
        return self.f(name, 'repeater', label, default, layout='block', button_label=button,
                      collapsed=collapsed_key, min=0, max=0, sub_fields=sub_fields, **extra)

    def icon(self, name='icon', default='check'):
        return (name, 'select', 'Symbol', {'choices': ICON_CHOICES, 'default_value': default, 'width': 30})


def group(key, title, page_id, fields, note):
    return {
        'key': key,
        'title': title,
        'fields': fields,
        'location': [[{'param': 'page', 'operator': '==', 'value': str(page_id)}]],
        'menu_order': 0,
        'position': 'normal',
        'style': 'default',
        'label_placement': 'top',
        'instruction_placement': 'label',
        'hide_on_screen': ['the_content', 'excerpt', 'discussion', 'comments', 'revisions', 'author', 'format', 'featured_image', 'categories', 'tags', 'send-trackbacks'],
        'active': True,
        'description': note,
        'show_in_rest': 0,
        'modified': 1792483200,
    }


# ── Startseite (page 8) ───────────────────────────────────────────────────

def home():
    E = elementor('8-home.json')
    g = Group('home')
    occasions = []
    for tab, cont, head, body in [
        ('Events', '2be10bc', '5eacc60', 'c128cbd'),
        ('Hemd & Anzug', 'a5ca756', '24d1ac1', '6692859'),
        ('Firmenlogo', '112ede2', 'f3e9873', '4ec7bbd'),
        ('Service & Verkauf', '96615ea', 'c03f314', '1c1e617'),
        ('pos & promotion', '9fa2ad9', '6e0a88e', '36ffe1b'),
    ]:
        occasions.append({'label': tab, 'title': text(E, head, 'title'),
                          'text': plain(text(E, body)), 'image': bg(E, cont)})

    benefits = [
        {'icon': 'tag', 'title': text(E, '27b830f', 'title_text').strip(), 'text': text(E, '27b830f', 'description_text')},
        {'icon': 'truck', 'title': text(E, '4d3291a', 'title_text'), 'text': ''},
        {'icon': 'clock', 'title': text(E, 'e9fa570', 'title_text'), 'text': ''},
        {'icon': 'layers', 'title': text(E, 'f7beb8a', 'title_text'), 'text': ''},
    ]
    points = [
        {'icon': 'chat', 'title': text(E, 'aec6d47', 'title_text')},
        {'icon': 'award', 'title': text(E, '0286e9b', 'title_text')},
        {'icon': 'pen', 'title': text(E, 'f8ea4aa', 'title_text')},
        {'icon': 'zap', 'title': text(E, '59020d1', 'title_text')},
    ]
    cards = [
        {'image': img(E, 'e38608c'), 'title': text(E, 'cfbc3e4', 'title'), 'text': plain(text(E, '0432149'))},
        {'image': img(E, 'ac262bb'), 'title': text(E, '8a2648a', 'title'), 'text': plain(text(E, 'b0aa3d2'))},
        {'image': img(E, 'dd09e7d'), 'title': text(E, '38a0449', 'title'), 'text': plain(text(E, 'b0bf4aa'))},
    ]
    logos = [img(E, w) for w in ['8c6f875', '8ff5414', 'de0dab4', '1bc6193', 'ff52fe8', '97bb266',
                                 'c006d8c', 'b67ee53', 'd8a3f96', '99e5ed4', '8df53e7', '9412408']]
    seo = [
        {'title': text(E, h, 'title'), 'text': clean_html(text(E, t))}
        for h, t in [('480e6dc', '66f8743'), ('c8d49b3', '48ff9e3'), ('2926574', 'efe8b68')]
    ]
    hero_text = plain(text(E, 'd768077'))

    title_note = 'Der zweite Teil der Überschrift erscheint in Magenta. Leer lassen für eine einfarbige Überschrift.'
    fields = [
        g.tab('hero', 'Kopfbereich'),
        g.f('hero_kicker', 'text', 'Kleine Zeile über der Überschrift', 'Namensschilder seit 1983', width=50,
            instructions='Nur echte Fakten (Jahr, Herkunft, Zielgruppe), keine Werbeversprechen.'),
        g.f('hero_image', 'image', 'Bild', bg(E, '7da160d'), width=50,
            instructions='Querformat, mindestens 1600 px breit. Wird als erstes Bild sofort geladen.'),
        g.f('hero_title', 'text', 'Überschrift (Teil 1)', 'Das tollste Namensschild', width=50),
        g.f('hero_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'für Ihr Event.', width=50, instructions=title_note),
        g.f('hero_text', 'textarea', 'Text', hero_text, rows=3),
        g.f('hero_cta_label', 'text', 'Hauptbutton: Text', 'Alle Namensschilder', width=25,
            instructions='Leer lassen, um den Button auszublenden.'),
        g.f('hero_cta_url', 'text', 'Hauptbutton: Link', '/namensschilder/', width=25,
            instructions='Pfad wie /namensschilder/ oder volle Adresse.'),
        g.f('hero_cta2_label', 'text', 'Zweiter Button: Text', text(E, '4fa5bec', 'text'), width=25),
        g.f('hero_cta2_url', 'text', 'Zweiter Button: Link', '/product-category/atlantis/', width=25),
        # Live configurator demo (template-parts/hero-demo.php). Every value is shown labelled "Beispiel".
        g.f('hero_demo_show', 'true_false', 'Animierte Vorschau statt Bild', 1,
            instructions='Ja = rechts läuft eine kleine Konfigurator-Animation: ein Namensschild wird gestaltet. '
                         'Nein = das Bild oben wird gezeigt. Wer reduzierte Bewegung eingestellt hat, sieht ein fertiges Standbild.'),
        g.f('hero_demo_models', 'textarea', 'Vorschau: Modelle', 'Atlantis\nAlucontur\nWoodline', width=50, rows=4,
            instructions='Ein Modell pro Zeile. Das Aussehen folgt dem Namen: Atlantis, Alucontur, Aluprofil, Woodline, '
                         'Classic, Chalk Board, Papyrus, Translex, X-Style. Andere Namen bekommen ein neutrales Schild.'),
        g.f('hero_demo_names', 'textarea', 'Vorschau: Namen', 'Andreas Denker | Vertrieb\nJulia Weber | Projektleitung\nThomas Krüger | Empfang',
            width=50, rows=4, instructions='Ein Name pro Zeile, die Funktion nach einem senkrechten Strich: Name | Funktion.'),
        g.f('hero_demo_formats', 'textarea', 'Vorschau: Formate', '60 × 20 mm\n75 × 30 mm', width=33, rows=3,
            instructions='Ein Format pro Zeile, Breite × Höhe in mm. Die Schildgröße in der Vorschau folgt den Zahlen.'),
        g.f('hero_demo_fasteners', 'textarea', 'Vorschau: Befestigungen', 'Magnet\nNadel', width=33, rows=3,
            instructions='Eine Befestigung pro Zeile.'),
        g.f('hero_demo_prices', 'textarea', 'Vorschau: Staffelpreise', '10 | 7,92 €\n50 | 6,44 €\n100 | 5,45 €\n250 | 4,95 €', width=34, rows=4,
            instructions='Eine Stufe pro Zeile: Stückzahl | Preis pro Stück. Die Vorschau kennzeichnet die Preise als Beispiel.'),

        g.tab('models', 'Modelle'),
        g.show('models', 'Modelle'),
        g.f('models_kicker', 'text', 'Kleine Zeile', 'Modelle', width=33),
        g.f('models_title', 'text', 'Überschrift (Teil 1)', 'Finden Sie Ihr', width=33),
        g.f('models_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'Namensschild', width=34, instructions=title_note),
        g.f('models_text', 'textarea', 'Text', 'Wählen Sie ein Modell und gestalten Sie Ihr Namensschild online.', rows=2),
        g.f('models_products', 'relationship', 'Gezeigte Modelle', [22964, 3338], post_type=['product'], taxonomy='',
            filters=['search', 'taxonomy'], elements=['featured_image'], min='', max=8, return_format='id',
            instructions='Reihenfolge per Ziehen. Es werden nur veröffentlichte Produkte gezeigt. Bild und Text kommen aus dem Produkt (Beitragsbild, Kurzbeschreibung).'),
        g.f('models_link_label', 'text', 'Link unter den Modellen: Text', 'Alle Namensschilder ansehen', width=50,
            instructions='Führt zur Übersicht /namensschilder/. Leer lassen, um den Link auszublenden.'),

        g.tab('occasions', 'Anlässe'),
        g.show('occasions', 'Anlässe'),
        g.f('occasions_kicker', 'text', 'Kleine Zeile', 'Anlässe', width=33),
        g.f('occasions_title', 'text', 'Überschrift (Teil 1)', 'Die richtigen Namensschilder', width=33),
        g.f('occasions_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'für jeden Anlass', width=34, instructions=title_note),
        g.repeater('occasions', 'Reiter', [
            ('label', 'text', 'Reiter-Beschriftung', {'width': 30}),
            ('title', 'text', 'Überschrift', {'width': 70}),
            ('text', 'textarea', 'Text', {'rows': 3}),
            ('image', 'image', 'Bild', {'width': 50}),
            ('link_label', 'text', 'Link: Text (optional)', {'width': 25}),
            ('link_url', 'text', 'Link: Ziel (optional)', {'width': 25}),
        ], occasions, 'Reiter hinzufügen', 'label',
            instructions='Ein Reiter pro Anlass. Auf dem Handy sind die Reiter eine wischbare Zeile.'),

        g.tab('benefits', 'Vorteile'),
        g.show('benefits', 'Vorteile'),
        g.f('benefits_kicker', 'text', 'Kleine Zeile', 'Online bestellen', width=33),
        g.f('benefits_title', 'text', 'Überschrift (Teil 1)', 'Hier bestellen und', width=33),
        g.f('benefits_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'Vorteile sichern', width=34, instructions=title_note),
        g.f('benefits_text', 'textarea', 'Text', plain(text(E, 'e550a07')), rows=3),
        g.repeater('benefits', 'Vorteile', [
            g.icon('icon', 'check'),
            ('title', 'text', 'Titel', {'width': 40}),
            ('text', 'text', 'Zusatz (optional)', {'width': 30}),
        ], benefits, 'Vorteil hinzufügen', 'title',
            instructions='Nur echte Konditionen (Rabatt, Versand, Lieferzeit). Titel werden in Großbuchstaben gesetzt.'),

        g.tab('quality', 'Qualität & Kunden'),
        g.show('quality', 'Qualität & Kunden'),
        g.f('quality_kicker', 'text', 'Kleine Zeile', 'Referenzen', width=33),
        g.f('quality_title', 'text', 'Überschrift (Teil 1)', 'Qualität,', width=33),
        g.f('quality_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'die für sich spricht', width=34, instructions=title_note),
        g.f('quality_text', 'textarea', 'Text', plain(text(E, '1a89517')), rows=4),
        g.repeater('quality_facts', 'Zahlen', [
            ('value', 'text', 'Zahl', {'width': 40}),
            ('label', 'text', 'Beschriftung', {'width': 60}),
        ], [
            {'value': 'über 35', 'label': 'Jahre Expertise'},
            {'value': 'über 2.000', 'label': 'Kunden'},
            {'value': 'über 5.000.000', 'label': 'produzierte Namensschilder'},
        ], 'Zahl hinzufügen', 'label', instructions='Nur belegbare Zahlen. Leer lassen, um die Zeile auszublenden.'),
        g.f('quality_logos', 'gallery', 'Kundenlogos', logos,
            instructions='Logos in der gewünschten Reihenfolge. Bitte bei jedem Logo in der Mediathek den Firmennamen als Alternativtext eintragen.'),

        g.tab('service', 'Service'),
        g.show('service', 'Service'),
        g.f('service_kicker', 'text', 'Kleine Zeile', 'Full-Service', width=33),
        g.f('service_title', 'text', 'Überschrift (Teil 1)', 'Unser', width=33),
        g.f('service_title_accent', 'text', 'Überschrift (Teil 2, magenta)', 'Serviceversprechen', width=34, instructions=title_note),
        g.f('service_text', 'textarea', 'Text', plain(text(E, 'e4c6907')), rows=3),
        g.repeater('service_points', 'Leistungen', [
            g.icon('icon', 'check'),
            ('title', 'text', 'Titel', {'width': 70}),
        ], points, 'Leistung hinzufügen', 'title'),
        g.repeater('service_cards', 'Karten', [
            ('image', 'image', 'Bild', {'width': 30}),
            ('title', 'text', 'Titel', {'width': 70}),
            ('text', 'textarea', 'Text', {'rows': 2}),
        ], cards, 'Karte hinzufügen', 'title'),

        g.tab('seo', 'Text unten'),
        g.show('seo', 'Text unten'),
        g.repeater('seo_blocks', 'Textblöcke', [
            ('title', 'text', 'Überschrift', {}),
            ('text', 'wysiwyg', 'Text', {}),
        ], seo, 'Textblock hinzufügen', 'title',
            instructions='Längere Texte über das Sortiment, nebeneinander am Seitenende.'),

        g.tab('contact', 'Kontakt-Band'),
        g.f('contact_show', 'true_false', 'Kontakt-Band anzeigen', 1,
            instructions='Band am Seitenende mit Telefon, E-Mail und Öffnungszeiten aus den Website-Einstellungen.'),
        g.f('contact_title', 'text', 'Überschrift', '', width=50,
            instructions='Leer = „Überschrift im Panel“ aus den Website-Einstellungen (heute „Persönlich für Sie da“).'),
        g.f('contact_text', 'textarea', 'Text', '', rows=2, width=50,
            instructions='Leer = „Einleitung im Panel“ aus den Website-Einstellungen.'),
    ]
    return group('group_ip_home', 'Startseite', FRONT_PAGE_ID, fields,
                 'Inhalte der Startseite (front-page.php). Ort: Seite = Startseite (ID 8 auf Staging).')


# ── Unternehmen (page 2662) ───────────────────────────────────────────────

class Collect(HTMLParser):
    """Flat list of (tag, class, text) from the standalone HTML widget."""

    def __init__(self):
        super().__init__()
        self.items, self.stack, self.buf = [], [], []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'img':
            self.items.append(('img', a.get('class', ''), a.get('src', '')))
            return
        if tag == 'br':
            self.buf.append('\n')
            return
        self.stack.append((tag, a.get('class', '')))
        self.buf = []

    def handle_endtag(self, tag):
        if not self.stack:
            return
        t, c = self.stack.pop()
        txt = re.sub(r'[ \t\r\n]+', ' ', ''.join(self.buf)).strip()
        if txt:
            self.items.append((t, c, txt))
        self.buf = []

    def handle_data(self, data):
        self.buf.append(data)


def unternehmen():
    src = (LEGACY / 'html-widgets' / '2662-e43551a.html').read_text(encoding='utf-8')
    body = src[src.index('<body'):]
    p = Collect()
    p.feed(body)
    items = p.items
    get = lambda cls: [t for tag, c, t in items if c == cls]
    para = [t for tag, c, t in items if tag == 'p' and not c]
    h2 = [t for tag, c, t in items if tag == 'h2']
    years = get('timeline-year')
    texts = get('timeline-content')
    g = Group('unternehmen')
    story = ''.join(f'<p>{html.escape(t, quote=False)}</p>' for t in para[0:2])
    fields = [
        g.tab('head', 'Kopfbereich'),
        g.f('kicker', 'text', 'Kleine Zeile', 'Über infoplus', width=33),
        g.f('title', 'text', 'Überschrift (H1)', get('page-title')[0], width=33),
        g.f('subtitle', 'text', 'Unterzeile', get('page-subtitle')[0], width=34),
        g.repeater('highlights', 'Kernaussagen', [('text', 'text', 'Aussage', {})],
                   [{'text': t} for t in [t for tag, c, t in items if tag == 'li']],
                   'Aussage hinzufügen', 'text'),
        g.f('tagline', 'text', 'Schlusssatz unter den Kernaussagen', get('tagline')[0]),
        g.f('team_image', 'image', 'Teamfoto', 16017,
            instructions='Breites Querformat. Alternativtext in der Mediathek pflegen (heute „Infoplus Team“).'),

        g.tab('story', 'Geschichte'),
        g.f('story_title', 'text', 'Überschrift', h2[0]),
        g.f('story_text', 'wysiwyg', 'Text', story),

        g.tab('milestones', 'Meilensteine'),
        g.f('milestones_title', 'text', 'Überschrift', h2[1]),
        g.repeater('milestones', 'Meilensteine', [
            ('year', 'text', 'Jahr', {'width': 20}),
            ('text', 'textarea', 'Text', {'rows': 3, 'width': 80}),
        ], [{'year': y, 'text': t} for y, t in zip(years, texts)], 'Meilenstein hinzufügen', 'year'),

        g.tab('service', 'Service'),
        g.f('service_title', 'text', 'Überschrift', h2[2]),
        g.f('service_text', 'textarea', 'Text', para[2], rows=4),
        g.f('service_quote', 'textarea', 'Hervorgehobener Absatz', para[3], rows=4),

        g.tab('cta', 'Abschluss'),
        g.f('cta_title', 'text', 'Überschrift', h2[3], width=50),
        g.f('cta_text', 'text', 'Text', para[4], width=50),
        g.f('cta_label', 'text', 'Button: Text', 'Kontakt aufnehmen', width=50,
            instructions='Leer lassen, um den Button auszublenden.'),
        g.f('cta_url', 'text', 'Button: Link', '/kontakt/', width=50),
    ]
    return group('group_ip_unternehmen', 'Unternehmen', UNTERNEHMEN_ID, fields,
                 'Inhalte der Seite /unternehmen/ (page-unternehmen.php). Quelle: HTML-Widget e43551a der alten Seite.')


# ── Kontakt (page 3040) ───────────────────────────────────────────────────

def kontakt():
    E = elementor('3040-kontakt.json')
    g = Group('kontakt')
    fields = [
        g.f('kicker', 'text', 'Kleine Zeile', 'Kontakt', width=33),
        g.f('title', 'text', 'Überschrift (H1)', text(E, '7a2585d', 'title'), width=33),
        g.f('image', 'image', 'Bild neben der Einleitung', bg(E, '3604856'), width=34,
            instructions='Leer lassen, um ohne Bild zu zeigen.'),
        g.f('intro', 'textarea', 'Einleitung', plain(text(E, 'e550a07')), rows=3,
            instructions='Kontaktdaten und Öffnungszeiten kommen aus den Website-Einstellungen (Reiter „Kontakt“).'),
        g.f('form_title', 'text', 'Überschrift über dem Formular', text(E, '4e8e7c5', 'title'), width=50),
        g.f('fallback_title', 'text', 'Ohne Formular: Überschrift', 'Schreiben Sie uns eine E-Mail', width=50,
            instructions='Solange kein Formular-Modul aktiv ist, sehen Besucher stattdessen E-Mail- und Telefon-Buttons.'),
        g.f('fallback_text', 'textarea', 'Ohne Formular: Text',
            'Ihre Anfrage per E-Mail oder Telefon erreicht direkt unser Team.', rows=2),
    ]
    return group('group_ip_kontakt', 'Kontakt', KONTAKT_ID, fields,
                 'Texte der Seite /kontakt/ (page-kontakt.php).')


# ── FAQ (page 2667) ───────────────────────────────────────────────────────

def faq():
    E = elementor('2667-faq.json')
    acc = next(w for w in widgets(E) if w.get('widgetType') == 'nested-accordion')
    rows = []
    for item, panel in zip(acc['settings']['items'], acc['elements']):
        answer = ''.join(clean_html(w['settings']['editor']) for w in widgets([panel]) if w.get('widgetType') == 'text-editor')
        rows.append({'question': item['item_title'].strip(), 'answer': answer})
    intro = plain(text(E, '66f8743'))
    # The old intro ends mid-sentence ("... wenden Sie sich gern an"); "uns." completes it.
    if intro.endswith(' an'):
        intro += ' uns.'
    g = Group('faq')
    fields = [
        g.tab('head', 'Kopfbereich'),
        g.f('kicker', 'text', 'Kleine Zeile', 'Häufige Fragen', width=50),
        g.f('title', 'text', 'Überschrift (H1)', text(E, '124a3d3', 'title'), width=50),
        g.f('intro', 'textarea', 'Einleitung', intro, rows=2),
        g.tab('items', 'Fragen'),
        g.repeater('items', 'Fragen und Antworten', [
            ('question', 'text', 'Frage', {}),
            ('answer', 'wysiwyg', 'Antwort', {}),
        ], rows, 'Frage hinzufügen', 'question',
            instructions='Reihenfolge per Ziehen. Die Fragen werden auch als strukturierte Daten (FAQPage) für Suchmaschinen ausgegeben.'),
        g.tab('contact', 'Kontakt-Karte'),
        g.f('contact_title', 'text', 'Überschrift', 'Ihre Frage ist nicht dabei?', width=50,
            instructions='Karte neben den Fragen mit Telefon und E-Mail aus den Website-Einstellungen. Leer lassen, um sie auszublenden.'),
        g.f('contact_text', 'textarea', 'Text', 'Wir beraten Sie gern persönlich.', rows=2, width=50),
    ]
    return group('group_ip_faq', 'FAQ', FAQ_ID, fields, 'Inhalte der Seite /faq/ (page-faq.php).')


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for build in (home, unternehmen, kontakt, faq):
        g = build()
        path = OUT / f"{g['key']}.json"
        # "modified" tells Secure Custom Fields when a group changed: a newer
        # stamp than the database copy shows "Sync available". Keep the old
        # stamp when nothing changed, else use the current time.
        g['modified'] = int(time.time())
        if path.exists():
            old = json.loads(path.read_text(encoding='utf-8'))
            if {k: v for k, v in old.items() if k != 'modified'} == {k: v for k, v in g.items() if k != 'modified'}:
                g['modified'] = old.get('modified', g['modified'])
        path.write_text(json.dumps(g, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
        print('wrote', path.relative_to(ROOT), len(g['fields']), 'fields')
    return 0


if __name__ == '__main__':
    sys.exit(main())
