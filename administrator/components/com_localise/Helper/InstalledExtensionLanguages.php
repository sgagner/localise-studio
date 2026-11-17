<?php

namespace Joomla\Component\Localise\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

final class InstalledExtensionLanguages
{
	private const TYPES = ['package', 'component', 'module', 'plugin', 'library', 'file'];

	public static function getSources(): array
	{
		return array_filter(self::getExtensions(), static function ($extension) {
			return $extension->type !== 'file' && !(int) $extension->package_id && !(int) $extension->protected;
		});
	}

	public static function getSourceKey(object $extension): string
	{
		return implode(':', [$extension->type, $extension->client_id, $extension->folder, $extension->element]);
	}

	public static function getSourceId(string $key): int
	{
		foreach (self::getSources() as $id => $extension)
		{
			if (self::getSourceKey($extension) === $key)
			{
				return (int) $id;
			}
		}

		return 0;
	}

	public static function getUpdatedSource(int $extensionId): ?array
	{
		$extensions = self::getExtensions(true);
		if (!isset($extensions[$extensionId]))
		{
			return null;
		}

		// Check the updated extension and any children updated with its package.
		$pending = [$extensions[$extensionId]];
		$keys = [];
		$visited = [];
		while ($pending)
		{
			$extension = array_pop($pending);
			$id = (int) $extension->extension_id;
			if (isset($visited[$id]))
			{
				continue;
			}

			$visited[$id] = true;
			$keys[] = self::getSourceKey($extension);
			if ($extension->type === 'package')
			{
				$manifest = self::loadManifest($extension);
				foreach ($extensions as $child)
				{
					if ((int) $child->package_id === $id || self::isDeclaredChild($manifest, $child))
					{
						$pending[] = $child;
					}
				}
			}
		}

		// Updating one member also affects the language package for its parent.
		$parentId = (int) $extensions[$extensionId]->package_id;
		while (isset($extensions[$parentId]) && !isset($visited[$parentId]))
		{
			$visited[$parentId] = true;
			$keys[] = self::getSourceKey($extensions[$parentId]);
			$parentId = (int) $extensions[$parentId]->package_id;
		}

		$extension = $extensions[$extensionId];

		return ['keys' => $keys, 'name' => trim((string) $extension->name) ?: $extension->element];
	}

	public static function getSuggestions(int $id, string $language): array
	{
		$extensions = self::getExtensions();

		if (!isset($extensions[$id]) || $extensions[$id]->type === 'file' || !preg_match('/^[a-z]{2,3}-[a-z0-9-]{2,}$/iD', $language))
		{
			return [];
		}

		$candidates = [];
		$declared = [];
		$pending = [$extensions[$id]];
		$visited = [];

		while ($pending)
		{
			$extension = array_shift($pending);
			$id = (int) $extension->extension_id;

			if (isset($visited[$id]))
			{
				continue;
			}

			$visited[$id] = true;
			$manifest = self::loadManifest($extension);

			if ($extension->type === 'package')
			{
				foreach ($extensions as $child)
				{
					if ((int) $child->package_id === $id || self::isDeclaredChild($manifest, $child))
					{
						$pending[] = $child;
					}
				}
			}

			if (!$manifest)
			{
				continue;
			}

			$client = $extension->type === 'plugin' || ($extension->type === 'module' && (int) $extension->client_id === 1)
				? 'administrator' : 'site';

			foreach (LocaliseHelper::getManifestLanguageFiles($manifest, null, $client) as $target => $files)
			{
				foreach ($files as $filename)
				{
					$declared[$target][$filename] = true;
				}
			}

			$files = LocaliseHelper::getManifestLanguageFiles($manifest, $language, $client);

			if ($extension->type === 'file')
			{
				$files = array_merge_recursive($files, LocaliseHelper::getFileSetLanguageFiles($manifest, $language));
			}

			$candidates = array_merge_recursive($candidates, $files);
		}

		// Standalone translation packages have no package relationship in Joomla.
		// Include only names the chosen extension declares for the same client.
		foreach ($extensions as $extension)
		{
			if ($extension->type !== 'file' || isset($visited[$extension->extension_id]) || !$declared)
			{
				continue;
			}

			$manifest = self::loadManifest($extension);

			if ($manifest)
			{
				$files = array_merge_recursive(
					LocaliseHelper::getManifestLanguageFiles($manifest, $language, 'site'),
					LocaliseHelper::getFileSetLanguageFiles($manifest, $language)
				);

				foreach ($files as $client => $names)
				{
					foreach ($names as $filename)
					{
						if (isset($declared[$client][$filename]))
						{
							$candidates[$client][] = $filename;
						}
					}
				}
			}
		}

		$selected = [];

		foreach ($candidates as $client => $files)
		{
			foreach (array_unique($files) as $filename)
			{
				$name = substr($filename, 0, -4);

				if (is_file(LocaliseHelper::findTranslationPath($client, $language, $name)))
				{
					$selected[] = $client . '_' . $name;
				}
			}
		}

		return $selected;
	}

	private static function getExtensions(bool $refresh = false): array
	{
		static $extensions;

		if ($extensions === null || $refresh)
		{
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName(['extension_id', 'name', 'type', 'element', 'folder', 'client_id', 'package_id', 'protected']))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('state') . ' >= 0')
				->whereIn($db->quoteName('type'), self::TYPES, \Joomla\Database\ParameterType::STRING)
				->order($db->quoteName('name'));
			$extensions = $db->setQuery($query)->loadObjectList('extension_id');
		}

		return $extensions;
	}

	private static function isDeclaredChild(?\SimpleXMLElement $manifest, object $child): bool
	{
		if (!$manifest || (int) $child->package_id)
		{
			return false;
		}

		foreach ($manifest->xpath('files/file') as $member)
		{
			if ((string) $member['type'] !== $child->type || (string) $member['id'] !== $child->element)
			{
				continue;
			}

			if ($child->type === 'plugin' && (string) $member['group'] !== $child->folder)
			{
				continue;
			}

			if ($child->type === 'module' && ((string) $member['client'] === 'administrator' ? 1 : 0) !== (int) $child->client_id)
			{
				continue;
			}

			return true;
		}

		return false;
	}

	private static function loadManifest(object $extension): ?\SimpleXMLElement
	{
		$element = $extension->element;
		$folder = $extension->folder;
		$identifier = match ($extension->type) {
			'library' => '#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#iD',
			'file' => '/^[a-z0-9_-][a-z0-9_.-]*$/iD',
			default => '/^[a-z0-9_-]+$/iD',
		};

		if (!preg_match($identifier, $element) || ($extension->type === 'plugin' && !preg_match('/^[a-z0-9_-]+$/iD', $folder)))
		{
			return null;
		}

		$moduleRoot = (int) $extension->client_id === 1 ? JPATH_ADMINISTRATOR : JPATH_SITE;
		$paths = match ($extension->type) {
			'package' => [JPATH_MANIFESTS . '/packages/' . $element . '.xml'],
			'file' => [JPATH_MANIFESTS . '/files/' . $element . '.xml'],
			'component' => [
				JPATH_ADMINISTRATOR . '/components/' . $element . '/' . substr($element, 4) . '.xml',
				JPATH_ADMINISTRATOR . '/components/' . $element . '/' . $element . '.xml',
			],
			'module' => [$moduleRoot . '/modules/' . $element . '/' . $element . '.xml'],
			'plugin' => [
				JPATH_PLUGINS . '/' . $folder . '/' . $element . '/' . $element . '.xml',
				JPATH_PLUGINS . '/' . $folder . '/' . $element . '.xml',
			],
			'library' => [JPATH_MANIFESTS . '/libraries/' . $element . '.xml'],
		};

		foreach ($paths as $path)
		{
			$xml = is_file($path) ? simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) : false;

			if ($xml !== false && $xml->getName() === 'extension' && (string) $xml['type'] === $extension->type)
			{
				return $xml;
			}
		}

		return null;
	}
}
