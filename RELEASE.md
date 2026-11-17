# Release checklist

- [ ] Update [CHANGELOG.md](CHANGELOG.md) with the release version, date, and changes.
- [ ] Build the installation ZIP from the release commit on the `localise-studio` branch. Follow the [build instructions](README.md#build-the-joomla-installation-package).
- [ ] Install and test the built ZIP on `studio.joomlab.se/clean`. Confirm the installed version, verify the features affected by the release, and check that automatic file checks are active.
- [ ] Publish the GitHub release with the tested installation ZIP attached.
- [ ] Link directly to the matching version section in [CHANGELOG.md](CHANGELOG.md) from the release notes. Verify the link and confirm that the ZIP can be downloaded from the published release.
- [ ] Record the test results, the release commit, and links to the GitHub release and installation ZIP in the work log.
