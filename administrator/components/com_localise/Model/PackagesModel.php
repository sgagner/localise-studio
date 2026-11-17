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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Localise\Administrator\Helper\InstalledExtensionLanguages;
use Joomla\Component\Localise\Administrator\Helper\LocaliseHelper;
use Joomla\Component\Localise\Administrator\Helper\PackageExportSnapshot;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Form\Form;

/**
 * Packages Model class for the Localise component
 *
 * @package     Extensions.Components
 * @subpackage  Localise
 *
 * @since       1.0
 */
class PackagesModel extends ListModel
{
	protected $context = 'com_localise.packages';

	protected $items;

	protected $packages;

	protected $filter_fields = array('title', 'language', 'version', 'core');

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     JController
	 * @since   3.5
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'title',
				'language',
				'version',
				'core',
			);
		}

		parent::__construct($config);
	}

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
		$app  = Factory::getApplication();
		$data = $app->input->get('filters', array(), 'array');

		/*if (empty($data['search']))
		{
			$data['search'] = $app->getUserState('com_localise.packages.search');
		}
		else
		{
			$app->setUserState('com_localise.packages.search', $data['search']);
		}
		*/

		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
		$search = InputFilter::getInstance()->clean($search, 'TRIM');
		$search = strtolower($search);

		if ($search)
		{
			$app->setUserState('filter.search', strtolower($search));
		}
		else
		{
			$app->setUserState('filter.search', '');
		}

		$this->setState('filter.title', isset($data['select']['title']) ? $data['select']['title'] : '');

		$this->setState('filter.language', isset($data['select']['language']) ? $data['select']['language'] : '');

		$this->setState('filter.version', isset($data['select']['version']) ? $data['select']['version'] : '');

		$this->setState('filter.core', isset($data['select']['core']) ? $data['select']['core'] : '');

		$params = ComponentHelper::getParams('com_localise');
		$this->setState('params', $params);

		//parent::populateState('title', 'asc');
		parent::populateState($ordering, $direction);
	}

	/**
	 * Get packages
	 *
	 * @return array
	 */
	private function _getPackages()
	{
		if (!isset($this->packages))
		{
			$search = $this->getState('filter.search');
			$this->packages = array();
			$paths = array (
				JPATH_COMPONENT_ADMINISTRATOR . '/packages',
				JPATH_SITE . '/media/com_localise/packages',
			);

			foreach ($paths as $path)
			{
				if (is_dir($path))
				{
					$files = Folder::files($path, '\.xml$');

					foreach ($files as $file)
					{
						$id    = LocaliseHelper::getFileId("$path/$file");
						$context = LocaliseHelper::isCorePackage("$path/$file") ?
									'package' : 'packagefile';
						//$model = \JModelLegacy::getInstance($context, 'LocaliseModel', array('ignore_request' => true));

						if ($context == 'package')
						{
							$model = new \Joomla\Component\Localise\Administrator\Model\PackageModel(array('ignore_request' => true));
						}
						elseif ($context == 'packagefile')
						{
							$model = new \Joomla\Component\Localise\Administrator\Model\PackagefileModel(array('ignore_request' => true));
						}
						$model->getState();

						$model->setState("$context.id", $id);
						$package = $model->getItem();

						if ($package->name === 'core')
						{
							continue;
						}

						if (empty($search) || preg_match("/$search/i", $package->title))
						{
							$this->packages[] = $package;
						}
					}
				}
			}

			$ordering = $this->getState('list.ordering') ? $this->getState('list.ordering') : 'title';
			$this->packages = ArrayHelper::sortObjects(
				$this->packages,
				$ordering, $this->getState('list.direction') == 'DESC' ? -1 : 1
			);
		}

		return $this->packages;
	}

	/**
	 * Get Items
	 *
	 * @return array|mixed
	 */
	public function getItems()
	{
		if (empty($this->items))
		{
			$packages = $this->_getPackages();
			$count    = count($packages);
			$start    = $this->getState('list.start');
			$limit    = $this->getState('list.limit');

			if ($start > $count)
			{
				$start = 0;
			}

			if ($limit == 0)
			{
				$start = 0;
				$limit = null;
			}

			$this->items = array_slice($packages, $start, $limit);
		}

		return $this->items;
	}

	public function getExportStatus(object $package): array
	{
		return $this->buildExportStatus($package, PackageExportSnapshot::read((int) $package->id, $package->language));
	}

	private function buildExportStatus(object $package, ?array $snapshot): array
	{
		if ($snapshot === null)
		{
			return ['status' => 'NEVER', 'exportedAt' => 0, 'checkedAt' => 0];
		}

		$fingerprint = hash('sha256', serialize([
			$snapshot, $package->installedsource, $package->sourcefiles, $package->translations,
		]));
		$check = PackageExportSnapshot::readCheck((int) $package->id, $fingerprint);

		return [
			'status' => $check['status'] ?? 'UNCHECKED',
			'exportedAt' => $snapshot['exportedAt'],
			'checkedAt' => $check['checkedAt'] ?? 0,
			'origin' => $check['origin'] ?? null,
			'updatedExtension' => $check['updatedExtension'] ?? '',
			'fingerprint' => $fingerprint,
			'hasSource' => $package->installedsource !== '',
		];
	}

	public function checkUpdatedExtension(int $extensionId): void
	{
		$source = InstalledExtensionLanguages::getUpdatedSource($extensionId);
		if ($source === null)
		{
			return;
		}

		$ids = [];
		foreach ([JPATH_ADMINISTRATOR . '/components/com_localise/packages', JPATH_SITE . '/media/com_localise/packages'] as $directory)
		{
			if (!is_dir($directory))
			{
				continue;
			}

			foreach (Folder::files($directory, '\.xml$') as $file)
			{
				$path = $directory . '/' . $file;
				$definition = LocaliseHelper::loadPackageDefinition($path);
				if ($definition && in_array((string) $definition->installedsource, $source['keys'], true))
				{
					$ids[] = LocaliseHelper::getFileId($path);
				}
			}
		}

		$this->checkExportStatuses($ids, $source['name']);
	}

	public function checkExportStatuses(array $ids, ?string $updatedExtension = null): int
	{
		$app = Factory::getApplication();
		$model = new PackagefileModel(['ignore_request' => true]);
		$count = 0;

		foreach (array_unique(ArrayHelper::toInteger($ids)) as $id)
		{
			if ($id < 1 || !$app->getIdentity()->authorise('localise.edit', 'com_localise.' . $id))
			{
				continue;
			}

			$path = LocaliseHelper::getFilePath($id);
			if (!is_file($path) || LocaliseHelper::isCorePackage($path))
			{
				continue;
			}

			$model->getState();
			$model->setState('packagefile.id', $id);
			$package = $model->getItem();
			if (!$package || !$package->language)
			{
				continue;
			}

			$snapshot = PackageExportSnapshot::read($id, $package->language);
			if ($snapshot !== null)
			{
				$state = $this->buildExportStatus($package, $snapshot);
				$status = $model->checkExportStatus($package, $snapshot);
				if (!PackageExportSnapshot::recordCheck($id, $state['fingerprint'], $status, $updatedExtension))
				{
					$app->enqueueMessage(\Joomla\CMS\Language\Text::sprintf('COM_LOCALISE_EXPORT_CHECK_SAVE_FAILED', $id), 'warning');
					continue;
				}
			}
			$count++;
		}

		return $count;
	}

	/**
	 * Get Total
	 *
	 * @return int
	 */
	public function getTotal()
	{
		return count($this->_getPackages());
	}

	/**
	 * Method to get the row form.
	 *
	 * @return  mixed  JForm object on success, false on failure.
	 */
	public function getForm()
	{
		// Initialise variables.
		$app = Factory::getApplication();

		// Get the form.
		jimport('joomla.form.form');
		Form::addFormPath(JPATH_COMPONENT . '/forms');
		Form::addFieldPath(JPATH_COMPONENT . '/field');

		$form = Form::getInstance('com_localise.packages', 'packages', array('control' => 'filters', 'event' => 'onPrepareForm'));

		// Check for an error.
		if ($form instanceof Exception)
		{
			$this->setError($form->getMessage());

			return false;
		}

		// Check the session for previously entered form data.
		$data = $app->getUserState('com_localise.select', array());

		// Bind the form data if present.
		if (!empty($data))
		{
			$form->bind(array('select' => $data));
		}

		// Check the session for previously entered form data.
		$data = $app->getUserState('com_localise.packages.filter.search', array());

		// Bind the form data if present.
		if (!empty($data))
		{
			$form->bind(array('search' => $data));
		}

		return $form;
	}

	/**
	 * Remove packages
	 *
	 * @param   array  $selected  array of selected packages
	 *
	 * @return  boolean  true for success, false for failure
	 */
	public function delete($selected)
	{
		// Sanitize the array.
		$selected = ArrayHelper::toInteger((array) $selected);

		// Get a row instance.
		$table = Table::getInstance('LocaliseTable', 'Joomla\\Component\\Localise\\Administrator\\Table\\');

		foreach ($selected as $packageId)
		{
			$path = LocaliseHelper::getFilePath($packageId);
			$package = File::stripExt(basename($path));

			if (!File::delete($path))
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGES_REMOVE', $package));

				return false;
			}

			if (!$table->delete($packageId))
			{
				$this->setError($table->getError());

				return false;
			}

			PackageExportSnapshot::remove($packageId);
		}

		return true;
	}

	/**
	 * Export packages
	 *
	 * @param   array  $selected  array of selected packages
	 *
	 * @return  boolean  success or failure
	 */
	public function export($selected)
	{
		foreach ($selected as $packageId)
		{
			$path = LocaliseHelper::getFilePath($packageId);
			$package = File::stripExt(basename($path));

			if (is_file($path))
			{
				ob_clean();
				$pack = file_get_contents($path);
				header("Expires: 0");
				header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
				header('Content-Type: application/xml');
				header('Content-Disposition: attachment; filename="' . $package . '.xml"');
				header('Content-Length: ' . strlen($pack));
				header("Cache-Control: maxage=1");
				header("Pragma: public");
				header("Content-Transfer-Encoding: binary");
				echo $pack;
				exit;
			}
			else
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGES_EXPORT', $package));
			}
		}
	}

	/**
	 * Clone packages
	 *
	 * @param   array  $selected  array of selected packages
	 *
	 * @return  boolean  success or failure
	 */
	public function duplicate($selected)
	{
		foreach ($selected as $packageId)
		{
			$path = LocaliseHelper::getFilePath($packageId);
			$package = File::stripExt(basename($path));

			if (is_file($path))
			{
				$pack = file_get_contents($path);
				$newpackage = $package . '_' . Factory::getDate()->format("Y-m-d-H-i-s");
				$newpath = JPATH_COMPONENT_ADMINISTRATOR . '/packages/' . $newpackage . '.xml';

				File::write($newpath, $pack);
			}
			else
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGES_READ', $package));

				return false;
			}

			if (!is_file($newpath))
			{
				$this->setError(Text::sprintf('COM_LOCALISE_ERROR_PACKAGES_CLONE', $package));

				return false;
			}
		}

		return true;
	}
}
