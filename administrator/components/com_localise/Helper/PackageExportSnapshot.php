<?php

namespace Joomla\Component\Localise\Administrator\Helper;

defined('_JEXEC') or die;

final class PackageExportSnapshot
{
	private static function path(int $id): string
	{
		return JPATH_ADMINISTRATOR . '/components/com_localise/packages/.export-' . $id . '.json';
	}

	private static function checkPath(int $id): string
	{
		return JPATH_ADMINISTRATOR . '/components/com_localise/packages/.export-check-' . $id . '.json';
	}

	private static function write(string $path, array $data): bool
	{
		$temp = @tempnam(dirname($path), '.export-');
		if ($temp === false)
		{
			return false;
		}

		$json = json_encode($data);
		$saved = $json !== false && @file_put_contents($temp, $json) === strlen($json) && @rename($temp, $path);
		if (!$saved)
		{
			@unlink($temp);
		}

		return $saved;
	}

	public static function record(int $id, string $language, array $hashes): bool
	{
		return self::write(self::path($id), [
			'language' => $language,
			'hashes' => $hashes,
			'exportedAt' => time(),
			'revision' => bin2hex(random_bytes(16)),
		]);
	}

	public static function recordCheck(int $id, string $fingerprint, string $status, ?string $updatedExtension = null): bool
	{
		return self::write(self::checkPath($id), [
			'fingerprint' => $fingerprint,
			'status' => $status,
			'checkedAt' => time(),
			'origin' => $updatedExtension === null ? 'manual' : 'automatic',
			'updatedExtension' => $updatedExtension,
		]);
	}

	public static function readCheck(int $id, string $fingerprint): ?array
	{
		$path = self::checkPath($id);
		$data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

		if (($data['fingerprint'] ?? '') !== $fingerprint
			|| !in_array($data['status'] ?? '', ['CHANGED', 'UNCHANGED', 'SOURCE_UNAVAILABLE'], true)
			|| !is_int($data['checkedAt'] ?? null))
		{
			return null;
		}

		return $data;
	}

	public static function read(int $id, string $language): ?array
	{
		$path = self::path($id);
		$data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

		if (($data['language'] ?? '') !== $language || !is_array($data['hashes'] ?? null))
		{
			return null;
		}

		$data['exportedAt'] = (int) ($data['exportedAt'] ?? @filemtime($path));

		return $data;
	}

	/**
	 * Return null until the package has been exported in this language.
	 */
	public static function getChangedFiles(int $id, string $language, array $selected, ?array $currentHashes = null): ?array
	{
		$data = self::read($id, $language);
		if ($data === null)
		{
			return null;
		}

		$changed = [];

		foreach (array_intersect_key($data['hashes'], array_fill_keys($selected, true)) as $file => $previous)
		{
			$current = $currentHashes[$file] ?? false;
			if ($currentHashes === null)
			{
				[$client, $name] = explode('_', $file, 2);
				$source = LocaliseHelper::findTranslationPath($client, $language, $name);
				$current = is_file($source) ? @hash_file('sha256', $source) : false;
			}

			if ($current !== false && $current !== $previous)
			{
				$changed[] = $file;
			}
		}

		return $changed;
	}

	public static function move(int $oldId, int $newId): void
	{
		foreach ([self::path($oldId) => self::path($newId), self::checkPath($oldId) => self::checkPath($newId)] as $oldPath => $newPath)
		{
			if (is_file($oldPath))
			{
				@rename($oldPath, $newPath);
			}
		}
	}

	public static function remove(int $id): void
	{
		foreach ([self::path($id), self::checkPath($id)] as $path)
		{
			if (is_file($path))
			{
				@unlink($path);
			}
		}
	}
}
