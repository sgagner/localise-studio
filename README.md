# Localise Studio

Localise Studio is a Joomla 6 administrator extension for translators. It lets you manage languages, edit translations, and build installable language packages for Joomla core or installed extensions. For an extension package, you can choose which language files to include and review changes against the last export.

The project continues `com_localise`, originally developed by Jean-Marie Simonet (infograf768) and later maintained by valc as JMlocalise. Localise Studio is maintained by Stefan Gagner (CMS Universal / svenskjoomla.se).

## Joomla 6 support

Localise Studio was tested on Joomla 6.2.0 with Joomla's Behaviour – Backward Compatibility 6 plugin disabled. The test covered the Languages, Translations, and Packages views, including saving and reopening a translation.

## Automatic file checks

Enable **Localise Studio – Automatic file check** in Joomla's plugin manager. For a language package linked to an installed extension, export the package to record a baseline. When Joomla successfully updates that extension, Localise Studio compares its language files with the last export. The package list shows the check time, the result, and which extension update triggered the check. **Review file changes** lists new, missing, and changed files. The check preserves your saved file selections.

### Verified with J2Commerce 6.6.5 → 6.6.6

On a separate Joomla 6.2.0 test installation, we updated J2Commerce from 6.6.5 to 6.6.6 through Joomla's extension update manager. Localise Studio recorded a new check time and marked the J2Commerce language package as **Automatic check after updating J2Commerce**, without a manual check.

- The review showed **seven changed Swedish language files**, with no new or missing files. A byte-for-byte comparison of the official 6.6.5 and 6.6.6 archives identified the same seven files.
- The Akeeba Backup language package kept its previous check time.
- PayPal's `plg_j2commerce_payment_paypal.sys.ini` stayed deselected in the saved J2Commerce language package.

## Releases

See the [changelog](CHANGELOG.md) for changes by version and [GitHub Releases](https://github.com/sgagner/localise-studio/releases) for installation ZIPs.

## Build the Joomla installation package

Run with Python 3 and Git from the repository root. The build reads the specified commit, so local working-tree edits are excluded.

```sh
python3 package/build.py HEAD ../pkg_localisestudio.zip
```

Replace `HEAD` with the commit ID to rebuild a specific version. Upload the generated ZIP in Joomla's extension installer. The package includes the Localise component and the automatic file check plugin.
