# Infoplus theme: design direction

Brand stays Infoplus: the kit colours and Archivo Narrow, unchanged. The way the
page is laid out, layered and animated takes its cue from the SDX website
(`jmalture/sdx-website`), which reads calmer and more current than the old
Elementor build. When a template is rebuilt, port the content 1:1 and apply
these rules; do not copy the old Elementor spacing where it fights them.

## Brand (fixed)

| Token | Value | Use |
|---|---|---|
| `--c-primary` | #E6007E | accent: links on hover, active states, primary buttons, kicker text, focus ring |
| `--c-secondary` | #9E0056 | primary hover/pressed; small magenta text where #E6007E is too light (under 18 px) |
| `--c-text` | #191B1C | headings and body |
| `--c-grey-700` | #4A4E53 | secondary text, sublines |
| `--c-grey-800` | #35393D | dark surfaces (rare) |
| `--c-grey-200` | #DEDEE9 | borders, dividers |
| `--c-grey-100` | #EFEFF6 | alternate section background |
| `--c-white` | #FFFFFF | page, cards |
| Font | Archivo Narrow 400 to 700 | everything; no second family |

New derived tokens (added to main.css, derived from the brand, not new colours):
`--c-primary-tint` (primary at about 8 % on white) for icon tiles, hover backgrounds
and selected states; `--shadow-1` and `--shadow-2`, soft layered shadows tinted with
the text colour, never grey-black.

## Principles taken from SDX

1. **Layers, not boxes.** Page = white or #EFEFF6 bands. Content sits in white cards
   (radius 16, 1 px #DEDEE9 border, no shadow at rest). Floating things (mega menu,
   dropdowns, drawer, Kontakt panel) are panels: radius 20 to 24, `--shadow-2`
   (`0 30px 60px -20px rgb(25 27 28 / .28), 0 0 0 1px rgb(25 27 28 / .06)`).
2. **Section head pattern.** Small kicker above the heading (Badge style: 600, 16 px,
   uppercase, 1.5 px tracking, magenta), heading in Heading style, optional second half
   of the heading in magenta, one grey subline. Headlines say the benefit, not the topic.
3. **Icon tiles.** Icons sit on a rounded tile (44 px, radius 13) in `--c-primary-tint`
   with a magenta stroke icon; on hover the tile tilts slightly (rotate -8deg, scale 1.06,
   springy easing). Same 24 px outline icon set everywhere (inc/icons.php).
4. **Motion is short and purposeful.** 200 to 300 ms, `cubic-bezier(.22,1,.36,1)`;
   panels fade + rise 8 px; list items in a panel stagger 40 to 50 ms; chevrons rotate
   180deg when open; cards lift 2 px with `--shadow-1` on hover. All motion off under
   `prefers-reduced-motion`.
5. **Pills and counts.** Small counts and labels ("20 Modelle", "Neu") as pills
   (radius 999, tint background, magenta text, 11 to 12 px, 700).
6. **Buttons.** Primary: magenta fill, white text, radius 10, hover #9E0056. Secondary:
   white with 1 px border, hover tint background. One primary button per view area.
7. **Focus is visible.** 2 px magenta outline, 2 to 3 px offset, rounded.
8. **Phones.** Long card rows become one horizontal swipe row (scroll-snap, 84 %
   card width, dots below) so a section stays about one screen high. Header stays
   sticky and compact. Drawers are native `<dialog>`.
9. **Numbers and facts over adjectives.** Trust lines only state real facts from the
   client (B2B only, payment methods, shipping, location). Never invent claims.
10. **Restraint.** Magenta is an accent: at most one magenta block per screen
    (the bottom bar of the footer counts). Large areas stay white or #EFEFF6.

## Contrast

#E6007E on white is about 4.5:1: fine for text from 18 px or bold 14 px. Smaller magenta
text uses #9E0056. White on #E6007E is fine for buttons and the footer bar.

## E-shop UX for B2B buyers and resellers

Goal: a shop that still looks current in five years because it is calm and fast, not
because it follows a trend. Every screen answers three questions for a professional
buyer: what is it, what does it cost at my quantity, how fast do I get it. Sources:
Baymard B2B research themes, configurator and B2B UX practice (links in the rebuild map).

### Finding products
- Mega menu and listing show every model with a real product photo and its name; no prices
  in the menu (owner decision).
- Listing cards carry 2 to 3 fact chips instead of marketing text (e.g. formats, material,
  "ab 1 Stück"), so models can be compared at a glance.
- Category pills on top of every listing; on phones a horizontal swipe row.
- Search finds model names, SKUs and attribute words, results as product cards.

### Configuring (product page)
- One-page configurator in numbered steps (1 Modell, 2 Format, 3 Optionen, 4 Menge), each
  step a titled block; done steps show a check and their chosen value; unavailable options
  are faded, never hidden without explanation.
- A summary card stays in view: desktop sticky in the right column, phones the bottom bar.
  It shows the chosen variant, unit price at the current quantity, total, and the next action.
- Staffelpreise always visible next to quantity, current tier highlighted; prices labelled
  net or gross exactly as the shop is configured.
- Lead time (Fertigungszeit), shipping and payment facts sit directly under the main button,
  as small icon rows, not in tabs.
- Three order types, three clear main actions: Produkt gestalten (editor), In den Warenkorb
  (buyable), Individuelle Anfrage (inquiry). The inquiry is always available as a secondary
  action, so a buyer with a special case never hits a dead end.

### Cart and checkout
- Company fields first, private-person wording nowhere (B2B only).
- An optional "Ihre Bestellreferenz / PO-Nummer" field, shown on invoice and order e-mail.
- Totals card always visible; shipping and VAT lines spelled out before the last step.
- No surprises: the step indicator shows Warenkorb, Kasse, Bestätigung.

### Account (resellers come back)
- Orders list with status, total, invoice download and "Erneut bestellen" where reordering
  is safe; order detail printable.
- Saved designs / projects from the editor once a data source exists.
- Addresses saved and editable; company data in one place.

### Always
- Real contact one click away (Kontakt panel, phone and e-mail in footer).
- Speed is design: small pages, images sized, no layout shift.
- Accessible by default: keyboard, focus, labels, contrast; motion off when asked.
