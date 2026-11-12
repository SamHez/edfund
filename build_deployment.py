#!/usr/bin/env python3
"""Build one cPanel-ready ZIP for https://edfundfoundation.org/.

Run: python3 build_deployment.py
Output: deployment/edfund-cpanel-ready.zip
Builds and validates an isolated production copy. Never uploads or deploys.
"""
from pathlib import Path
import os
import shutil
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parent
OUTPUT = ROOT / 'deployment'
ORIGIN = 'https://edfundfoundation.org'
FILENAME = 'edfund-cpanel-ready.zip'


def main():
    if len(sys.argv) != 1:
        raise SystemExit('Usage: python3 build_deployment.py')
    with tempfile.TemporaryDirectory(prefix='edfund-cpanel-') as temporary:
        # The shared packager builds fresh HTML and runs the link/asset and SEO
        # validators. The working source configuration is not changed.
        command = [sys.executable, str(ROOT / 'source/package.py'),
                   '--production', '--origin', ORIGIN, '--base-path', '',
                   '--output-dir', temporary]
        try:
            subprocess.run(command, cwd=ROOT, check=True)
        except subprocess.CalledProcessError as error:
            raise SystemExit(error.returncode)
        built = Path(temporary) / 'edfund-plesk-ready.zip'
        with zipfile.ZipFile(built) as archive:
            assert archive.testzip() is None, 'ZIP integrity check failed'
            assert {'index.html', '.htaccess', 'robots.txt', 'sitemap.xml'} <= set(archive.namelist())
            pages = [name for name in archive.namelist() if name.endswith('.html')]
            for name in pages:
                html = archive.read(name).decode('utf-8')
                expected = 'noindex,follow' if name == '404.html' else 'index,follow,max-image-preview:large'
                assert f'name="robots" content="{expected}"' in html, name
                assert f'rel="canonical" href="{ORIGIN}/' in html, name
                assert 'href="/edfund/' not in html and 'src="/edfund/' not in html, name
            assert 'ErrorDocument 404 /404.html' in archive.read('.htaccess').decode('utf-8')
        OUTPUT.mkdir(exist_ok=True)
        destination = OUTPUT / FILENAME
        pending = OUTPUT / ('.' + FILENAME + '.tmp')
        try:
            shutil.copyfile(built, pending)
            os.replace(pending, destination)
        finally:
            pending.unlink(missing_ok=True)
    # Remove only files produced by the superseded preview version of this script.
    for name in ['edfund-subfolder-preview.zip', 'edfund-subfolder-preview-seo-checks.json', 'UPLOAD-INSTRUCTIONS.txt']:
        (OUTPUT / name).unlink(missing_ok=True)
    print(f'\nReady: {destination}\n{len(pages)} HTML pages; {destination.stat().st_size / 1024 / 1024:.2f} MB')
    print('Upload and extract directly into the domain document root (usually public_html).')
    print('index.html, .htaccess and assets/ belong directly in that root. Nothing was uploaded.')


if __name__ == '__main__':
    main()
