# Localise Studio
Developing a version of com_localise adapted to Joomla 6

The original version of this excellent package was developed by Jean-Marie Simonet (infograf768) a prominent person in the Joomla community.
Later the development was continued by (valc) as JMlocalise.

We now want to revive this excellent tool for a Joomla translator and take the development even further.

/Stefan Gagner - CMS Universal - svenskjoomla.se

### Build the Joomla installation package

Run with Python 3 and Git from the repository root. The build reads the specified
commit, so local working-tree edits are excluded.

```sh
python3 package/build.py HEAD ../pkg_localisestudio.zip
```

Replace `HEAD` with the commit ID to rebuild a specific version. Upload the
generated ZIP in Joomla's extension installer. For a fresh installation, enable
**Localise Studio – Automatic file check** in Joomla's plugin manager if you
want automatic checks.
