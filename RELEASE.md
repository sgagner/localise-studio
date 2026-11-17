# Release checklist

- [ ] Update [CHANGELOG.md](CHANGELOG.md) with the release version, date, and changes.
- [ ] Build the installation ZIP from the release commit on the `localise-studio` branch. Follow the [build instructions](README.md#build-the-joomla-installation-package).
- [ ] Install and test the built ZIP on `studio.joomlab.se/clean`. Confirm the installed version, verify the features affected by the release, and check that automatic file checks are active.
- [ ] Calculate the SHA-256 checksum of the tested installation ZIP.
- [ ] Publish the GitHub release with the tested installation ZIP attached.
- [ ] Link directly to the matching version section in [CHANGELOG.md](CHANGELOG.md) from the release notes. Verify the link, download the ZIP from the published release, and confirm that its SHA-256 checksum matches the tested file.
- [ ] Publish the verified SHA-256 checksum of the installation ZIP in the GitHub release notes.
- [ ] Update the [shared SHA-256 table](#verified-sha-256-checksums) for each new release with the verified checksum and links to the GitHub release and installation ZIP.
- [ ] Link directly to the [shared SHA-256 table](#verified-sha-256-checksums) from the release notes for each new release.
- [ ] Record the test results, the release commit, and links to the GitHub release and installation ZIP in the work log.

## Work log entry template

Copy this template into a new work log entry for each release. Fill in the fields after testing and publishing.

```markdown
### Localise Studio [version] – released [YYYY-MM-DD]

- **Version:** [version number]
- **Release date:** [YYYY-MM-DD]
- **Test results on `studio.joomlab.se/clean`:** [installed version, what was tested, results, and any issues]
- **Release commit:** [full commit SHA] (branch `localise-studio`)
- **GitHub release:** [View the release](RELEASE_URL)
- **Installation ZIP:** [Download the installation package](ZIP_URL)
- **Installation ZIP SHA-256:** `[checksum of the ZIP file]`
```

## Verified SHA-256 checksums

| Version | SHA-256 | GitHub release | Installation ZIP |
| --- | --- | --- | --- |
| 6.0.1 | `22b024660fcedaf723bae993727cc8e447e414969317e0a749a6b14533b4705b` | [Release 6.0.1](https://github.com/sgagner/localise-studio/releases/tag/6.0.1) | [ZIP](https://github.com/sgagner/localise-studio/releases/download/6.0.1/pkg_localisestudio-6.0.1.zip) |
| 6.1.0 | `b84a21202b4f4b44832be62acdee585dd7e268f84c31636ba3368475ded43589` | [Release 6.1.0](https://github.com/sgagner/localise-studio/releases/tag/6.1.0) | [ZIP](https://github.com/sgagner/localise-studio/releases/download/6.1.0/pkg_localisestudio-6.1.0.zip) |
| 6.1.1 | `2a610010be27349cee67c9631497a8ce77ecdd64f7195f4c5743506aaf40482a` | [Release 6.1.1](https://github.com/sgagner/localise-studio/releases/tag/6.1.1) | [ZIP](https://github.com/sgagner/localise-studio/releases/download/6.1.1/pkg_localisestudio-6.1.1.zip) |
