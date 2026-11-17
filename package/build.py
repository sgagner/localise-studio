#!/usr/bin/env python3
"""Build the Joomla package entirely from a Git commit."""

import argparse
import io
from pathlib import Path
import subprocess
import tarfile
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


REPO = Path(__file__).resolve().parents[1]
COMPONENT = 'administrator/components/com_localise/'
MEDIA = 'media/com_localise/'
PLUGIN = 'plugins/extension/localisefilecheck/'
MANIFEST = 'package/pkg_localisestudio.xml'


def git(*args):
    return subprocess.check_output(['git', '-C', str(REPO), *args])


def make_zip(files):
    buffer = io.BytesIO()
    with ZipFile(buffer, 'w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for name, data in sorted(files.items()):
            entry = ZipInfo(name)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, data, compress_type=ZIP_DEFLATED, compresslevel=9)
    return buffer.getvalue()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('commit', help='Git commit or ref to build')
    parser.add_argument('output', type=Path, help='Path for the generated ZIP')
    args = parser.parse_args()
    commit = git('rev-parse', '--verify', '--end-of-options', args.commit + '^{commit}').decode().strip()
    source = git('archive', '--format=tar', commit, COMPONENT, MEDIA, PLUGIN, MANIFEST)

    with tarfile.open(fileobj=io.BytesIO(source)) as archive:
        files = {item.name: archive.extractfile(item).read() for item in archive if item.isfile()}

    component = {
        'component/admin/' + name[len(COMPONENT):]: content
        for name, content in files.items() if name.startswith(COMPONENT)
    }
    for name in ('install.php', 'localise.xml'):
        component[name] = component.pop('component/admin/' + name)
    component.update({name: content for name, content in files.items() if name.startswith(MEDIA)})
    plugin = {name[len(PLUGIN):]: content for name, content in files.items() if name.startswith(PLUGIN)}
    args.output.write_bytes(make_zip({
        'pkg_localisestudio.xml': files[MANIFEST],
        'packages/com_localise.zip': make_zip(component),
        'packages/plg_extension_localisefilecheck.zip': make_zip(plugin),
    }))
    print(f'{commit}  {args.output}')


if __name__ == '__main__':
    main()
