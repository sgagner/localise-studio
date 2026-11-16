<?php
/**
 * @package     Com_Localise
 * @subpackage  model
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Localise\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\Archive\Archive;
use Joomla\CMS\Access\Rules as JAccessRules;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\Component\Localise\Administrator\Helper\LocaliseHelper;
use Joomla\Component\Localise\Administrator\Helper\InstalledExtensionLanguages;
use Joomla\Component\Localise\Administrator\Helper\PackageExportSnapshot;
use Joomla\CMS\Object\CMSObject;

/**
 * Package Model class for the Localise component
 *
 * @package     Extensions.Components
 * @subpackage  Localise
 *
 * @since       1.0
 */
class PackageFileModel extends AdminModel
{
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Get the application
		$app = Factory::getApplication('administrator');

		// Load the User state.
		$name = $app->getUserState('com_localise.packagefile.name');
		$this->setState('packagefile.name', $name);

		$id = $app->input->getInt('id', 0);
		$this->setState('packagefile.id', $id);
	}

	/**
	 * Returns a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable  A database object
	 */
	public function getTable($type = 'LocaliseTable', $prefix = '\\Joomla\\Component\\Localise\\Administrator\\Table\\', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$id   = $this->getState('packagefile.id');
		$name = $this->getState('packagefile.name');
		$form = $this->loadForm('com_localise.packagefile', 'packagefile', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		$form->setFieldAttribute('translations', 'packagefile', $name, 'translations');

		// Check for an error.
		if ($form instanceof Exception)
		{
			$this->setError($form->getMessage());

			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return   mixed  The data for the form.
	 */
	protected function loadFormData()
	{
		// Initialise variables.
		$app = Factory::getApplication();

		// Check the session for previously entered form data.
		$data = $app->getUserState('com_localise.edit.packagefile.data', array());

		// Get the package data.
		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get the ftp form.
	 *
	 * @return  mixed  A JForm object on success, false on failure or not ftp
	 */
	public function getFormFtp()
	{
		// Get the form.
		$form = $this->loadForm('com_localise.ftp', 'ftp');

		if (empty($form))
		{
			return false;
		}

		// Check for an error.
		if ($form instanceof Exception)
		{
			$this->setError($form->getMessage());

			return false;
		}

		return $form;
	}

	/**
	 * Method to get the package.
	 *
	 * @param   integer  $pk  The ID of the primary key.
	 *
	 * @return CMSObject the package
	 */
	public function getItem($pk = null)
	{
		// Initialise variables.
		$app       = Factory::getApplication();
		$input     = $app->input;
		$jformdata = $input->get('jform', array(), 'array');

		$id = $this->getState('packagefile.id');
		$id = is_array($id) ? (count($id) > 0 ? $id[0] : 0) : $id;
		$package = new CMSObject;
		$package->checked_out = 0;
		$package->standalone  = true;
		$package->manifest    = null;
		$package->title       = null;
		$package->description = null;
		$package->id          = $id;

		if (!empty($id))
		{
			// If the package exists get it
			$table = $this->getTable();

			if (is_array($id))
			{
				$id = $id[0];
			}

			$table->load($id);

			$package->setProperties($table->getProperties());

			// Get the manifest
			$xml = LocaliseHelper::loadPackageDefinition($table->path);

			if ($xml)
			{
				$manifest = (string) $xml->manifest;

				// $client   = (string) $xml->manifest->attributes()->client;

				// LocaliseHelper::loadLanguage($manifest, $client);

				// Set up basic information
				$name = basename($table->path);
				$name = substr($name, 0, strlen($name) - 4);

				$package->id          = $id;
				$package->name        = $name;
				$package->manifest    = $manifest;

				// $package->client      = $client;

				// $package->standalone  = substr($manifest, 0, 4) == 'fil_';
				$package->core          = ((string) $xml->attributes()->core) == 'true';
				$package->title         = (string) $xml->title;
				$package->version       = (string) $xml->version;
				$package->packversion   = (string) $xml->packversion;
				$package->description   = (string) $xml->description;
				$package->language      = (string) $xml->language;
				$package->installedsource = (string) $xml->installedsource;
				$package->sourcefiles = self::readSourceFiles($xml);
				$package->license       = (string) $xml->license;
				$package->copyright     = (string) $xml->copyright;
				$package->author        = (string) $xml->author;
				$package->authoremail   = (string) $xml->authoremail;
				$package->authorurl     = (string) $xml->authorurl;
				$package->packager      = (string) $xml->packager;
				$package->packagerurl   = (string) $xml->packagerurl;
				$package->servername    = (string) $xml->servername;
				$package->serverurl     = (string) $xml->serverurl;
				$package->writable      = LocaliseHelper::isWritable($package->path);

				$user = Factory::getUser($table->checked_out);
				$package->setProperties($table->getProperties());

				if ($package->checked_out == Factory::getUser()->id)
				{
					$package->checked_out = 0;
				}

				$package->editor = Text::sprintf('COM_LOCALISE_TEXT_PACKAGE_EDITOR', $user->name, $user->username);

				// Get the translations
				$package->translations  = array();
				$package->administrator = array();

				if ($xml->administrator && empty($jformdata['translations']))
				{
					foreach ($xml->administrator->children() as $file)
					{
						$data = (string) $file;
						$key  = substr($file, 0, strlen($file) - 4);

						if ($data)
						{
							if (!in_array("administrator_$key", $package->translations))
							{
								$package->translations[] = "administrator_$key";
							}
						}
						else
						{
							if (!in_array("administrator_joomla", $package->translations))
							{
								$package->translations[] = "administrator_joomla";
							}
						}

						if (!in_array($data, $package->administrator))
						{
							$package->administrator[] = $data;
						}
					}
				}
				elseif (!empty($jformdata['translations']))
				{
					foreach ($jformdata['translations'] as $file)
					{
						if (preg_match('/^administrator_(.*)$/', $file, $matches))
						{
							if (!in_array($matches[1], $package->administrator))
							{
								$package->administrator[] = $matches[1] . '.ini';
							}
						}
					}
				}

				$package->site = array();

				if ($xml->site && empty($jformdata['translations']))
				{
					foreach ($xml->site->children() as $file)
					{
						$data = (string) $file;
						$key  = substr($file, 0, strlen($file) - 4);

						if ($data)
						{
							if (!in_array("site_$key", $package->translations))
							{
								$package->translations[] = "site_$key";
							}
						}
						else
						{
							if (!in_array("site_joomla", $package->translations))
							{
								$package->translations[] = "site_joomla";
							}
						}

						if (!in_array($data, $package->site))
						{
							$package->site[] = $data;
						}
					}
				}
				elseif (!empty($jformdata['translations']))
				{
					foreach ($jformdata['translations'] as $file)
					{
						if (preg_match('/^site_(.*)$/', $file, $matches))
						{
							if (!in_array($matches[1], $package->site))
							{
								$package->administrator[] = $matches[1] . '.ini';
							}
						}
					}
				}

				if (!empty($jformdata['translations']))
				{
					$package->translations = $jformdata['translations'];
				}
			}
			else
			{
				$package = null;
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_FILEEDIT'), $table->path);
			}
		}

		return $package;
	}

	/**
	 * Method to save data
	 *
	 * @param   array  $data  the data to save
	 *
	 * @return  boolean  success or failure
	 */
	public function save($data)
	{
		// When editing a package, find the original path
		$app = Factory::getApplication('administrator');
		$originalId = (int) $this->getState('packagefile.id');
		$oldpath = null;

		if (!empty($originalId))
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('path'))
				->from($db->quoteName('#__localise'))
				->where($db->quoteName('id') . ' = ' . $originalId);
			$db->setQuery($query);

			$oldpath = $db->loadResult('path');
		}

		// Get the package name
		$name = $data['name'];

		// Get the package
		$package  = $this->getItem();
		$path     = JPATH_COMPONENT_ADMINISTRATOR . '/packages/' . $name . '.xml';
		$manifest = $name;

		// $client   = $package->client ? $package->client : 'site';

		if ($package->standalone)
		{
			$title = $name;
			$description = $data['description'];

			$dom = new \DOMDocument('1.0', 'utf-8');

			// Create simple XML element and base package tag
			$packageXml = $dom->createElement('package');

			// Add main package information
			$titleElement = $dom->createElement('title', $title);
			$descriptionElement = $dom->createElement('description');
			$descriptionElement->appendChild($dom->createTextNode($description));
			$manifestElement = $dom->createElement('manifest', $manifest);
			$versionElement = $dom->createElement('version', $data['version']);
			$packversionElement = $dom->createElement('packversion', $data['packversion']);
			$authorElement = $dom->createElement('author', $data['author']);
			$licenseElement = $dom->createElement('license', $data['license']);
			$authorEmailElement = $dom->createElement('authoremail', $data['authoremail']);
			$authorUrlElement = $dom->createElement('authorurl', $data['authorurl']);
			$languageElement = $dom->createElement('language', $data['language']);
			$copyrightElement = $dom->createElement('copyright', $data['copyright']);
			$packagerElement = $dom->createElement('packager', $data['packager']);
			$packagerUrlElement = $dom->createElement('packagerurl', $data['packagerurl']);
			$servernameElement = $dom->createElement('servername', $data['servername']);
			$serverurlElement = $dom->createElement('serverurl', $data['serverurl']);

			// Set the client attribute on the manifest element

			// $clientAttribute = $dom->createAttribute('client');

			// $clientAttribute->value = $client;

			// $manifestElement->appendChild($clientAttribute);

			// Add all the elements to the parent <package> tag
			$packageXml->appendChild($titleElement);
			$packageXml->appendChild($descriptionElement);
			$packageXml->appendChild($manifestElement);
			$packageXml->appendChild($versionElement);
			$packageXml->appendChild($packversionElement);
			$packageXml->appendChild($authorElement);
			$packageXml->appendChild($copyrightElement);
			$packageXml->appendChild($licenseElement);
			$packageXml->appendChild($authorEmailElement);
			$packageXml->appendChild($authorUrlElement);
			$packageXml->appendChild($languageElement);
			$packageXml->appendChild($copyrightElement);
			$packageXml->appendChild($packagerElement);
			$packageXml->appendChild($packagerUrlElement);
			$packageXml->appendChild($servernameElement);
			$packageXml->appendChild($serverurlElement);

			$source = (string) ($data['installedsource'] ?? $package->installedsource ?? '');

			if ($source !== '')
			{
				$sourceId = InstalledExtensionLanguages::getSourceId($source);
				$sameSource = $source === ($package->installedsource ?? '') && $data['language'] === ($package->language ?? '');
				$checked = json_decode($data['checkedsourcefiles'] ?? '', true);
				$files = $sameSource ? ($package->sourcefiles ?? []) : [];

				if ($sourceId && ($checked['source'] ?? '') === $source && ($checked['language'] ?? '') === $data['language'])
				{
					$files = self::validFileOptions($checked['files'] ?? []);
				}
				elseif ($sourceId && !$sameSource)
				{
					$files = InstalledExtensionLanguages::getSuggestions($sourceId, $data['language']);
				}

				if ($sameSource || $sourceId)
				{
					$packageXml->appendChild($dom->createElement('installedsource'))->appendChild($dom->createTextNode($source));
					$sourceFiles = $packageXml->appendChild($dom->createElement('sourcefiles'));

					foreach ($files as $file)
					{
						$sourceFiles->appendChild($dom->createElement('file', $file));
					}
				}
			}

			$administrator = array();
			$site          = array();

			foreach ($data['translations'] as $translation)
			{
				if (preg_match('/^site_(.*)$/', $translation, $matches))
				{
					if (!in_array($matches[1], $site))
					{
						$site[] = $matches[1];
					}
				}

				if (preg_match('/^administrator_(.*)$/', $translation, $matches))
				{
					if (!in_array($matches[1], $administrator))
					{
						$administrator[] = $matches[1];
					}
				}
			}

			// Add the site language files
			if (count($site))
			{
				$siteXml = $dom->createElement('site');

				foreach ($site as $translation)
				{
					$fileElement = $dom->createElement('filename', $translation . '.ini');
					$siteXml->appendChild($fileElement);
				}

				$packageXml->appendChild($siteXml);
			}

			// Add the administrator language files
			if (count($administrator))
			{
				$adminXml = $dom->createElement('administrator');

				foreach ($administrator as $translation)
				{
					$fileElement = $dom->createElement('filename', $translation . '.ini');
					$adminXml->appendChild($fileElement);
				}

				$packageXml->appendChild($adminXml);
			}

			$dom->appendChild($packageXml);

			// Set FTP credentials, if given.
			ClientHelper::setCredentialsFromRequest('ftp');
			$ftp = ClientHelper::getCredentials('ftp');

			// Try to make the file writeable.
			if (is_file($path) && !$ftp['enabled'] && Path::isOwner($path) && !Path::setPermissions($path, '0644'))
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_WRITABLE', $path));

				return false;
			}

			// Make the XML look pretty
			$dom->formatOutput = true;
			$formattedXML = $dom->saveXML();

			$return = File::write($path, $formattedXML);

			// Try to make the file unwriteable.
			if (!$ftp['enabled'] && Path::isOwner($path) && !Path::setPermissions($path, '0444'))
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_UNWRITABLE', $path));

				return false;
			}
			elseif (!$return)
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_FILESAVE', $path));

				return false;
			}
		}

		/** @TODO: Check ftp code
		// Save the title and the description in the language file
		$translation_path  = LocaliseHelper::findTranslationPath($client, Factory::getLanguage()->getTag(), $manifest);
		$translation_id    = LocaliseHelper::getFileId($translation_path);
		$translation_model = \JModelLegacy::getInstance('Translation', 'LocaliseModel', array('ignore_request' => true));

		if ($translation_model->checkout($translation_id))
		{
			$translation_model->setState('translation.path', $translation_path);
			$translation_model->setState('translation.client', $client);
			$translation = $translation_model->getItem();
			$sections    = LocaliseHelper::parseSections($translation_path);
		}
		else
		{
		}

		$text = '';
		//$text .= strtoupper($title) . '="' . str_replace('"', '"_QQ_"', $data['title']) . "\"\n";
		//$text .= strtoupper($description) . '="' . str_replace('"', '"_QQ_"', $data['description']) . "\"\n";
		//$tag  = Factory::getLanguage()->getTag();
		//$languagePath = JPATH_SITE . "/language/$tag/$tag.$manifest.ini";

		// Try to make the file writeable.
		if (!$ftp['enabled'] && PATH::isOwner($languagePath) && !PATH::setPermissions($languagePath, '0644'))
		{
			$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_WRITABLE', $languagePath));

			return false;
		}

		//$return = File::write($languagePath, $text);

		// Try to make the file unwriteable.
		if (!$ftp['enabled'] && PATH::isOwner($languagePath) && JPATH::setPermissions($languagePath, '0444'))
		{
			$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_UNWRITABLE', $languagePath));

			return false;
		}
		elseif (!$return)
		{
			$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGE_FILESAVE', $languagePath));

			return false;
		}

		*/
		if ($path == $oldpath || (!empty($path) && $oldpath == NULL))
		{
			$id = LocaliseHelper::getFileId($path);
			$this->setState('packagefile.id', $id);

			// Bind the rules.
			$table = $this->getTable();
			$table->load($id);
		}
		else
		{
			$table = $this->getTable();

			if (!$table->delete((int) $originalId))
			{
				$this->setError($table->getError());

				return false;
			}

			$table->store();

			$id = LocaliseHelper::getFileId($path);
			$this->setState('packagefile.id', $id);
		}

		if (isset($data['rules']))
		{
			$rules = new JAccessRules($data['rules']);
			$table->setRules($rules);
		}

		// Check the data.
		if (!$table->check())
		{
			$this->setError($table->getError());

			return false;
		}

		// Store the data.
		if (!$table->store())
		{
			$this->setError($table->getError());

			return false;
		}

		// Delete the older file and redirect
		if ($path !== $oldpath && file_exists($oldpath))
		{
			if (!File::delete($oldpath))
			{
				$app->enqueueMessage(Text::_('COM_LOCALISE_ERROR_OLDFILE_REMOVE'), 'notice');
			}

			PackageExportSnapshot::move($originalId, $id);

			$task = Factory::getApplication()->input->get('task');

			if ($task == 'save')
			{
				$app->redirect(Route::_('index.php?option=com_localise&view=packages', false));
			}
			else
			{
				// Redirect to the new $id as name has changed
				$app->redirect(Route::_('index.php?option=com_localise&view=packagefile&layout=edit&id=' . $this->getState('packagefile.id'), false));
			}
		}

		$this->cleanCache();

		return true;
	}

	/**
	 * Method to generate and download a package
	 *
	 * @param   array  $data  the data to generate the package
	 *
	 * @return  boolean  success or failure
	 */
	public function download($data)
	{
		// The data could potentially be loaded from the file with $this->getItem() instead of using directly the data from the post
		$app = Factory::getApplication();

		// Prevent generating and downloading Master package
		if (strpos($data['name'], 'master_') !== false)
		{
			$app->enqueueMessage(Text::sprintf('COM_LOCALISE_ERROR_MASTER_PACKAGE_DOWNLOAD_FORBIDDEN', $data['name']), 'warning');
			$app->redirect(Route::_('index.php?option=com_localise&view=packagefile&layout=edit&id=' . $this->getState('packagefile.id'), false));

			return false;
		}

		$archiveFilename = $data['archive_filename'] ?? '';
		$useTemplate = ($data['standard_archive'] ?? '') === '1';
		$error = null;

		if ($useTemplate)
		{
			if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $data['version'] ?? '')
				|| !preg_match('/^[0-9]+$/D', $data['packversion'] ?? ''))
			{
				$error = 'COM_LOCALISE_PACKAGE_VERSION_INVALID';
			}
			else
			{
				$archiveFilename = strtr(PackageModel::CORE_ARCHIVE_NAME, [
					'{language}' => $data['language'],
					'{version}' => $data['version'],
					'{revision}' => $data['packversion'],
				]);
			}
		}
		elseif ($archiveFilename === '')
		{
			$archiveFilename = $data['name'] . '_' . $data['language'] . '_' . $data['version'] . 'v' . $data['packversion'] . '.zip';
		}
		elseif (!is_string($archiveFilename) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,249}\.zip$/D', $archiveFilename))
		{
			$error = 'COM_LOCALISE_CUSTOM_ARCHIVE_INVALID';
		}

		if ($error !== null)
		{
			$app->getLanguage()->load('com_localise_picker', JPATH_COMPONENT_ADMINISTRATOR);
			$app->enqueueMessage(Text::_($error), 'warning');
			$app->redirect(Route::_('index.php?option=com_localise&view=packagefile&layout=edit&id=' . $this->getState('packagefile.id'), false));

			return false;
		}

		[$groups, $missing, $hashes] = $this->collectExportFiles($data);

		if ($missing)
		{
			$msg = '';
			foreach ($missing as $file)
			{
				$msg .= Text::sprintf('COM_LOCALISE_FILE_NOT_TRANSLATED', $file['name'], Text::_($file['client'] === 'site' ? 'JSITE' : 'JADMINISTRATOR'));
			}
			$app->enqueueMessage($msg . '<p>...</p>' . Text::_('COM_LOCALISE_UNTRANSLATED'), 'error');
			$app->redirect(Route::_('index.php?option=com_localise&view=packagefile&layout=edit&id=' . $this->getState('packagefile.id'), false));

			return false;
		}

		$main_package_files = [];

		$parts = explode('.', $data['version']);
		$small_version = implode('.', array($parts[0],$parts[1]));

		$escapeXml = static function ($value) {
			return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		};

		// Prepare text to save for the xml package description
		$text = '';
		$text .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$text .= '<extension type="file" version="' . $small_version . '" method="upgrade">' . "\n";
		$text .= "\t" . '<name>' . $escapeXml($data['name']) . $data['language'] . '</name>' . "\n";
		$text .= "\t" . '<version>' . $data['version'] . (!empty($data['packversion']) ? '.' . $data['packversion'] : '') . '</version>' . "\n";
		$text .= "\t" . '<creationDate>' . date('d/m/Y') . '</creationDate>' . "\n";
		$text .= "\t" . '<author>' . $escapeXml($data['author']) . '</author>' . "\n";
		$text .= "\t" . '<authorEmail>' . $escapeXml($data['authoremail']) . '</authorEmail>' . "\n";
		$text .= "\t" . '<authorUrl>' . $escapeXml($data['authorurl']) . '</authorUrl>' . "\n";
		$text .= "\t" . '<copyright>' . $escapeXml($data['copyright']) . '</copyright>' . "\n";
		$text .= "\t" . '<license>' . $escapeXml($data['license']) . '</license>' . "\n";
		$text .= "\t" . '<packager>' . $escapeXml($data['packager']) . '</packager>' . "\n";
		$text .= "\t" . '<packagerurl>' . $escapeXml($data['packagerurl']) . '</packagerurl>' . "\n";
		$description = str_replace(']]>', ']]]]><![CDATA[>', $data['description'] ?? '');
		$text .= "\t" . '<description><![CDATA[' . $description . ']]></description>' . "\n";
		$text .= "\t" . '<fileset>' . "\n";

		foreach ($groups as $group)
		{
			if (!$group['files'])
			{
				continue;
			}

			$text .= "\t\t" . '<files folder="' . $escapeXml($group['folder'])
				. '" target="' . $escapeXml($group['target']) . '">' . "\n";
			foreach ($group['files'] as $file)
			{
				$text .= "\t\t\t" . '<filename>' . $escapeXml($file['name']) . '</filename>' . "\n";
				$main_package_files[] = ['name' => $group['folder'] . '/' . $file['name'], 'data' => $file['data']];
			}
			$text .= "\t\t" . '</files>' . "\n";
		}

		$text .= "\t" . '</fileset>' . "\n";

		if (!empty($data['serverurl']))
		{
			$text .= "\t" . '<updateservers>' . "\n";
			$text .= "\t\t" . '<server type="collection" priority="1" name="' . $escapeXml($data['servername']) . '">' . $escapeXml($data['serverurl']) . '</server>' . "\n";
			$text .= "\t" . '</updateservers>' . "\n";
		}

		$text .= '</extension>' . "\n";

		$main_package_files[] = array('name' => $data['name'] . $data['language'] . '.xml', 'data' => $text);

		$ziproot = JPATH_ROOT . '/tmp/' . uniqid('localise_packagefile_', true) . '.zip';

		// Run the packager

		$archive = new Archive;

		if (!$packager = $archive->getAdapter('zip'))
		{
			$this->setError(Text::_('COM_LOCALISE_ERROR_EXPORT_ADAPTER'));

			return false;
		}
		else
		{
			if (!$packager->create($ziproot, $main_package_files))
			{
				if (is_file($ziproot))
				{
					@unlink($ziproot);
				}

				$this->setError(Text::_('COM_LOCALISE_ERROR_EXPORT_ZIPCREATE'));

				return false;
			}
		}

		$zipdata = file_get_contents($ziproot);
		@unlink($ziproot);

		if ($zipdata === false)
		{
			$this->setError(Text::_('COM_LOCALISE_ERROR_EXPORT_ZIPCREATE'));

			return false;
		}

		$id = $app->input->getInt('id');
		$packagePath = $id ? LocaliseHelper::getFilePath($id) : '';

		if ($packagePath && $app->getIdentity()->authorise('localise.edit', 'com_localise.' . $id))
		{
			$package = LocaliseHelper::loadPackageDefinition($packagePath);

			if ($package && (string) $package->language === $data['language'] && !PackageExportSnapshot::record($id, $data['language'], $hashes))
			{
				$app->getLanguage()->load('com_localise_picker', JPATH_COMPONENT_ADMINISTRATOR);
				$app->enqueueMessage(Text::_('COM_LOCALISE_EXPORT_SNAPSHOT_FAILED'), 'warning');
			}
		}

		ob_clean();
		header("Expires: 0");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $archiveFilename . '"');
		header('Content-Length: ' . strlen($zipdata));
		header("Cache-Control: maxage=1");
		header("Pragma: public");
		header("Content-Transfer-Encoding: binary");
		echo $zipdata;
		exit;
	}

	/**
	 * Method to get the HTML output required to update the translations field list by the selected language tag.
	 *
	 * @return   object  The data for the jform_translations
	 */
	public function updateTranslationsList($data)
	{
		Factory::getApplication()->getLanguage()->load('com_localise_picker', JPATH_COMPONENT_ADMINISTRATOR);
		$params = ComponentHelper::getParams('com_localise');
		$reftag = $params->get('reference') ?: 'en-GB';
		$langtag = (string) ($data[0]->languagetag ?? '');

		$id = (int) ($data[0]->packageid ?? 0);
		$path = $id ? LocaliseHelper::getFilePath($id) : '';
		$packageDirectories = [
			JPATH_COMPONENT_ADMINISTRATOR . '/packages',
			JPATH_SITE . '/media/com_localise/packages',
		];

		if (!in_array(dirname($path), $packageDirectories, true))
		{
			$path = '';
		}

		// Keep the original lookup for callers that do not send a record ID.
		$name = (string) ($data[0]->packagename ?? '');

		if (!$id && $name !== '' && File::makeSafe($name) === $name && basename($name) === $name)
		{
			$path = $packageDirectories[0] . '/' . $name . '.xml';
		}

		$xml = LocaliseHelper::loadPackageDefinition($path, $langtag);
		$selected = [];

		foreach (['site', 'administrator'] as $client)
		{
			if ($xml && $xml->{$client})
			{
				foreach ($xml->{$client}->children() as $file)
				{
					$selected[] = $client . '_' . substr((string) $file, 0, -4);
				}
			}
		}

		$form = Form::getInstance(
			'packagefile',
			JPATH_COMPONENT_ADMINISTRATOR . '/forms/packagefile.xml',
			['control' => 'jform']
		);
		$form->setFieldAttribute('translations', 'reftag', $reftag);
		$form->setFieldAttribute('translations', 'langtag', $langtag);
		$source = (string) ($data[0]->installedextension ?? '');
		$sourceId = InstalledExtensionLanguages::getSourceId($source);
		$html_output = new CMSObject;

		if ($sourceId)
		{
			$current = InstalledExtensionLanguages::getSuggestions($sourceId, $langtag);
			$sameSource = $xml && (string) $xml->language === $langtag && (string) $xml->installedsource === $source;
			$review = !empty($data[0]->review) || $sameSource;
			$known = $sameSource ? self::readSourceFiles($xml) : $current;
			$missing = $review ? array_values(array_diff($known, $current)) : [];
			$new = $review ? array_values(array_diff($current, $known)) : [];

			$selected = $review ? self::validFileOptions($data[0]->selected ?? []) : $current;
			$changed = $review && $id && $xml && (string) $xml->language === $langtag
				? PackageExportSnapshot::getChangedFiles($id, $langtag, $selected) : null;
			$visible = array_values(array_unique(array_merge($current, $missing, $selected)));
			$form->setFieldAttribute('translations', 'suggestedfiles', json_encode($visible));
			$form->setFieldAttribute('translations', 'newfiles', json_encode($new));
			$form->setFieldAttribute('translations', 'missingfiles', json_encode($missing));
			$form->setFieldAttribute('translations', 'changedfiles', json_encode($changed ?? []));
			$html_output->fileChanges = $review ? [
				'new' => count($new),
				'missing' => count($missing),
				'changed' => $changed === null ? null : count($changed),
			] : null;
			$html_output->sourceFiles = ['source' => $source, 'language' => $langtag, 'files' => $current];
		}
		elseif ($source !== '')
		{
			$html_output->sourceUnavailable = true;

			return $html_output;
		}

		$form->setValue('translations', null, $selected);

		$html_output->translations = $form->renderField('translations');

		return $html_output;
	}

	public function checkExportStatus(object $package, array $snapshot): string
	{
		[, $missing, $hashes] = $this->collectExportFiles([
			'language' => $package->language,
			'translations' => $package->translations,
		]);

		if ($missing || array_diff_assoc($hashes, $snapshot['hashes']) || array_diff_key($snapshot['hashes'], $hashes))
		{
			return 'CHANGED';
		}

		if ($package->installedsource !== '')
		{
			$sourceId = InstalledExtensionLanguages::getSourceId($package->installedsource);
			if (!$sourceId)
			{
				return 'SOURCE_UNAVAILABLE';
			}

			$current = InstalledExtensionLanguages::getSuggestions($sourceId, $package->language);
			if (array_diff($current, $package->sourcefiles) || array_diff($package->sourcefiles, $current))
			{
				return 'CHANGED';
			}
		}

		return 'UNCHANGED';
	}

	public function previewExport(array $data, int $id): array
	{
		[$groups, $missing, $hashes] = $this->collectExportFiles($data);
		$changed = $id ? PackageExportSnapshot::getChangedFiles($id, $data['language'], array_keys($hashes), $hashes) : null;
		$files = [];
		foreach ($groups as $client => $group)
		{
			foreach ($group['files'] as $file)
			{
				$files[$client][] = [
					'archive' => $group['folder'] . '/' . $file['name'],
					'target' => $group['target'] . '/' . $file['name'],
					'changed' => in_array($file['option'], $changed ?? [], true),
				];
			}
		}

		return ['files' => $files, 'missing' => $missing, 'hasBaseline' => $changed !== null];
	}

	private function collectExportFiles(array $data): array
	{
		$tag = $data['language'];
		$groups = [
			'site' => ['folder' => 'site/' . $tag, 'target' => 'language/' . $tag, 'files' => []],
			'administrator' => ['folder' => 'admin/' . $tag, 'target' => 'administrator/language/' . $tag, 'files' => []],
		];
		$missing = [];
		$hashes = [];

		foreach (self::validFileOptions($data['translations'] ?? []) as $option)
		{
			[$client, $translation] = explode('_', $option, 2);
			if (!isset($groups[$client]))
			{
				continue;
			}

			$name = $translation . '.ini';
			$path = LocaliseHelper::findTranslationPath($client, $tag, $translation);
			$content = is_file($path) ? file_get_contents($path) : false;
			if (empty($content))
			{
				$missing[] = ['client' => $client, 'name' => $name, 'option' => $option];
				continue;
			}

			$groups[$client]['files'][] = ['name' => $name, 'data' => $content, 'option' => $option];
			$hashes[$option] = hash('sha256', $content);
		}

		return [$groups, $missing, $hashes];
	}

	private static function validFileOptions($files): array
	{
		return array_values(array_unique(array_filter((array) $files, static function ($file) {
			return is_string($file) && preg_match('/^(?:site|administrator)_[a-z0-9_][a-z0-9._-]*$/iD', $file);
		})));
	}

	private static function readSourceFiles(\SimpleXMLElement $xml): array
	{
		return self::validFileOptions(array_map('strval', $xml->xpath('sourcefiles/file')));
	}
}
