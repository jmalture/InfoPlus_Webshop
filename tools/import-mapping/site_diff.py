"""Diff of site variation SKUs (dev site, queried via MCP 2026-09-26; rebuilt from the pattern groups seen) vs Excel."""
import csv, itertools, collections
site = set()
leg = [1,1051,1052,1055,1056,10931,12186,12187,12191,13446,13447,13451,2,209,210,213,214,5,6]
for p in ('NIA200','NIA300'): site |= {f'{p}-99.{n}' for n in leg}
c13 = 'B10 C12 G10 G21 G23 G32 G60 G80 T00 T10 T20 T30 T60'.split()
c12 = [c for c in c13 if c != 'C12']
for m, w, cols in [('401','12D',c13),('404','8D',c13),('405','10D',c13),('501','12D',c12),('504','8D',c12),('505','10D',c12)]:
    for c,a,f in itertools.product(cols,('D1','D2'),('A','I','IP','K','P')): site.add(f'NIA{m}{c}-{a}-{f}-{w}')
nic = {'000101':'D1 E1','000103':'D1 E1','000104':'E1','000105':'D1 E1','000106':'D1 E1','000181':'D1 L1','000183':'D1 L1','000185':'D1 L1','000186':'D1 L1',
       '000201':'D1','000203':'D1','000205':'D1','000206':'D1','000271':'D1','000273':'D1','000275':'D1','000276':'D1'}
for b,areas in nic.items():
    for a in areas.split():
        for f in ('S','D','DP','MB','P'): site.add(f'NIC{b}-{a}-{f}-ST')
for b in ('001311','001312','001313'):
    for f in ('S','D','DP','MB','P'): site.add(f'NIC{b}-E1-{f}-NU')
for m in ('0011','0041','0051'):
    for c,f in itertools.product([c for c in c13 if c!='G80'],('A','I','IP','K','P')): site.add(f'NNA{m}{c}-{f}')
print('rebuilt site SKUs', len(site), '(expected 1108)')
ex = {r['sku']: r for r in csv.DictReader(open('catalog_mapping.csv', encoding='utf-8-sig'), delimiter=';')}
keep, drop, new = site & set(ex), site - set(ex), set(ex) - site
print('site SKUs also in Excel (keep/update):', len(keep)); print('site SKUs NOT in Excel (delete):', len(drop)); print('Excel SKUs not on site (create):', len(new))
def grp(s): 
    import re; return re.sub(r'(?<=[A-Z])(\d{3,7}).*?((?:-(?:12D|10D|8D|ST|NU))?)$', r'*\2', s)[:14]
print('delete by family:', collections.Counter(s[:6]+('-legacy' if '-99.' in s else '') for s in drop).most_common())
print('keep by family:', collections.Counter(s[:6] for s in keep).most_common())
print('site SKUs missing from Excel, samples:', sorted(drop)[:12])
open('site_skus_not_in_excel.txt','w').write('\n'.join(sorted(drop))+'\n')
