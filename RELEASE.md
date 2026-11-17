# Release checklist

- [ ] Update [CHANGELOG.md](CHANGELOG.md) with the release version, date, and changes.
- [ ] Build the installation ZIP from the release commit on the `localise-studio` branch. Follow the [build instructions](README.md#build-the-joomla-installation-package).
- [ ] Install and test the built ZIP on `studio.joomlab.se/clean`. Confirm the installed version, verify the features affected by the release, and check that automatic file checks are active.
- [ ] Calculate the SHA-256 checksum of the tested installation ZIP.
- [ ] Publish the GitHub release with the tested installation ZIP attached.
- [ ] Link directly to the matching version section in [CHANGELOG.md](CHANGELOG.md) from the release notes. Verify the link, download the ZIP from the published release, and confirm that its SHA-256 checksum matches the tested file.
- [ ] Publish the verified SHA-256 checksum of the installation ZIP in the GitHub release notes.
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
