#!/usr/bin/env python3
"""Recover only published lif_team biographies as plain text; never execute/import SQL.

Usage: python3 tools/recover_team_profiles.py /private/path/wordpress.sql
The full dump, WordPress users/options/plugin data and credentials are never copied.
"""
from pathlib import Path
from html.parser import HTMLParser
import argparse
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parent.parent
EXPECTED = {'Rebecca Day', 'John C. Giebink', 'Dhizaala Stephen', 'Robert Ssebuliba'}


def insert_rows(sql, table):
    pattern = re.compile(r'^INSERT INTO `'+re.escape(table)+r'`\s*\(([^)]+)\)\s*VALUES\s*', re.MULTILINE | re.I)
    for match in pattern.finditer(sql):
        columns = re.findall(r'`([^`]+)`', match[1]); i = match.end()
        while i < len(sql):
            while sql[i].isspace(): i += 1
            if sql[i] != '(': break
            i += 1; row = []
            while True:
                while sql[i].isspace(): i += 1
                if sql[i] == "'":
                    i += 1; value = []
                    while i < len(sql):
                        char = sql[i]; i += 1
                        if char == '\\':
                            char = sql[i]; i += 1
                            value.append({'n': '\n', 'r': '\r', 't': '\t', '0': '\0', 'Z': '\x1a'}.get(char, char)); continue
                        if char == "'":
                            if i < len(sql) and sql[i] == "'": value.append("'"); i += 1; continue
                            break
                        value.append(char)
                    value = ''.join(value)
                else:
                    start = i
                    while sql[i] not in ',)': i += 1
                    raw = sql[start:i].strip(); value = None if raw.upper() == 'NULL' else raw
                row.append(value)
                while sql[i].isspace(): i += 1
                if sql[i] == ')': i += 1; break
                if sql[i] != ',': raise ValueError('Unexpected INSERT syntax')
                i += 1
            yield dict(zip(columns, row))
            while i < len(sql) and sql[i].isspace(): i += 1
            if sql[i] != ',': break
            i += 1


class Biography(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.seen_about = False; self.current = None; self.paragraphs = []; self.ignore = 0
    def handle_starttag(self, tag, attrs):
        if tag in ('script', 'style'): self.ignore += 1
        if tag == 'p' and self.seen_about: self.current = []
        if tag == 'br' and self.current is not None: self.current.append(' ')
    def handle_endtag(self, tag):
        if tag in ('script', 'style'): self.ignore = max(0, self.ignore-1)
        if tag == 'p' and self.current is not None:
            text = re.sub(r'\s+', ' ', ''.join(self.current)).strip()
            if text: self.paragraphs.append(text)
            self.current = None
    def handle_data(self, data):
        if self.ignore: return
        if 'About Me:' in data: self.seen_about = True; return
        if self.current is not None: self.current.append(data)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('dump', type=Path)
    args = parser.parse_args()
    data = args.dump.read_bytes(); sql = data.decode('utf-8', errors='replace'); members = {}
    for post in insert_rows(sql, 'wp_posts'):
        if post.get('post_type') != 'lif_team' or post.get('post_status') != 'publish' or post.get('post_title') not in EXPECTED: continue
        bio = Biography(); bio.feed(post['post_content'])
        if not bio.paragraphs: raise ValueError('No biography found for '+post['post_title'])
        members[post['post_title']] = {'wordpress_post_id': int(post['ID']), 'wordpress_slug': post['post_name'], 'wordpress_modified': post['post_modified'], 'paragraphs': bio.paragraphs}
    if set(members) != EXPECTED: raise ValueError('The four expected published profiles were not all found')
    output = {'source_file': args.dump.name, 'source_sha256': hashlib.sha256(data).hexdigest(), 'scope': 'Published lif_team About Me paragraphs only; plain text; existing card names/roles/images stay unchanged.', 'members': members}
    target = ROOT/'source/team-profiles.json'
    # Reruns are deliberate: do not silently overwrite manually corrected biographies.
    if target.exists():
        if json.loads(target.read_text()) == output:
            print('Recovered profiles already match; no changes.'); return
        raise SystemExit('Profiles file already exists and differs. Review manually before replacing it.')
    target.write_text(json.dumps(output, ensure_ascii=False, indent=2)+'\n')
    print(f'Recovered {len(members)} published biographies; no other WordPress data exported.')


if __name__ == '__main__': main()
