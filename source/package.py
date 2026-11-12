"""Retired static packager; refuses to bypass CMS-managed content.

Use --production for a separately validated, indexable Plesk archive.
The working checkout's publish_ready setting and public files stay unchanged.
"""
from pathlib import Path
import argparse
import json
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parent.parent
DEST = ROOT / 'artifacts'


def archive(public, filename):
    destination = DEST / filename
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as z:
        for f in sorted(public.rglob('*')):
            if f.is_file() and f.name != '.DS_Store':
                z.write(f, f.relative_to(public).as_posix())
    with zipfile.ZipFile(destination) as z:
        assert z.testzip() is None
        assert {'index.html', '.htaccess', 'robots.txt', 'sitemap.xml'} <= set(z.namelist())
        html = [n for n in z.namelist() if n.endswith('.html')]
        if filename == 'edfund-plesk-ready.zip':
            for name in html:
                content = z.read(name).decode()
                if name == '404.html':
                    assert 'noindex,follow' in content
                else:
                    assert 'noindex' not in content
                    assert 'index,follow,max-image-preview:large' in content
        print(f'{destination}\n{len(z.namelist())} files; {len(html)} HTML pages; {destination.stat().st_size / 1024 / 1024:.2f} MB; ZIP integrity verified.')
    return destination


def main():
    raise SystemExit('The static release builder is retired for the CMS. It can bypass client content. Use the CMS release workflow in Phase 5; no CMS ZIP is available in Phase 2.')
    global DEST
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--production', action='store_true', help='Build and validate an indexable production copy without changing local settings.')
    parser.add_argument('--base-path', default='', help='Hosting subfolder, for example /edfund. Empty means domain root.')
    parser.add_argument('--origin', help='Hosting origin without a path, for example https://swavemedia.co.')
    parser.add_argument('--output-dir', type=Path, default=DEST, help='Archive output directory (default: artifacts).')
    args = parser.parse_args()
    DEST = args.output_dir.resolve()
    base = '/' + args.base_path.strip('/') if args.base_path.strip('/') else ''
    if base and not re.fullmatch(r'(?:/[A-Za-z0-9_-]+)+', base):
        parser.error('base-path must contain simple folder names')
    DEST.mkdir(parents=True, exist_ok=True)
    if not args.production and not base and not args.origin:
        archive(ROOT / 'public', 'edfund-public.zip')
        return
    with tempfile.TemporaryDirectory(prefix='edfund-production-') as temporary:
        stage = Path(temporary)
        shutil.copytree(ROOT / 'source', stage / 'source', ignore=shutil.ignore_patterns('__pycache__'))
        shutil.copytree(ROOT / 'public', stage / 'public', ignore=shutil.ignore_patterns('.DS_Store'))
        (stage / 'docs').mkdir()
        config_path = stage / 'source/site.json'
        config = json.loads(config_path.read_text())
        config['publish_ready'] = args.production
        config['base_path'] = base
        if args.origin:
            from urllib.parse import urlsplit
            parsed = urlsplit(args.origin)
            if parsed.scheme != 'https' or not parsed.netloc or parsed.path not in ('', '/'):
                parser.error('origin must be an HTTPS hostname with no path')
            config['origin'] = args.origin.rstrip('/')
        if base:
            htaccess = stage / 'public/.htaccess'
            rules = htaccess.read_text()
            rules = re.sub(r'# Only canonicalise.*?(?=RewriteCond %\{THE_REQUEST\})', '', rules, flags=re.S)
            rules = re.sub(r'^(ErrorDocument 404 )/', lambda m: m[1] + base + '/', rules, flags=re.M)
            rules = re.sub(r'^(RewriteRule \S+ )/(?!/)', lambda m: m[1] + base + '/', rules, flags=re.M)
            htaccess.write_text(rules)
        config_path.write_text(json.dumps(config, ensure_ascii=False, indent=2) + '\n')
        for script in ['build.py', 'check_site.py', 'check_seo.py']:
            subprocess.run([sys.executable, str(stage / 'source' / script)], cwd=stage, check=True)
        filename = 'edfund-subfolder-preview.zip' if base and not args.production else 'edfund-subfolder-production.zip' if base else 'edfund-plesk-ready.zip' if args.production else 'edfund-public.zip'
        archive(stage / 'public', filename)
        shutil.copy2(stage / 'docs/seo-checks.json', DEST / filename.replace('.zip', '-seo-checks.json'))


if __name__ == '__main__':
    main()
