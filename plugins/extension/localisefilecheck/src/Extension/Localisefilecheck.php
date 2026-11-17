<?php

namespace Joomla\Plugin\Extension\Localisefilecheck\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Extension\AfterInstallEvent;
use Joomla\CMS\Event\Extension\AfterUpdateEvent;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class Localisefilecheck extends CMSPlugin implements SubscriberInterface
{
	public static function getSubscribedEvents(): array
	{
		return [
			'onExtensionAfterUpdate' => 'afterUpdate',
			'onExtensionAfterInstall' => 'afterInstall',
		];
	}

	public function afterUpdate(AfterUpdateEvent $event): void
	{
		$this->check((int) $event->getArgument('eid'));
	}

	public function afterInstall(AfterInstallEvent $event): void
	{
		if ($event->getArgument('installer')->isUpgrade())
		{
			$this->check((int) $event->getArgument('eid'));
		}
	}

	private function check(int $extensionId): void
	{
		if ($extensionId < 1 || !ComponentHelper::isEnabled('com_localise'))
		{
			return;
		}

		try
		{
			$model = $this->getApplication()->bootComponent('com_localise')->getMVCFactory()
				->createModel('Packages', 'Administrator', ['ignore_request' => true]);
			$model->checkUpdatedExtension($extensionId);
		}
		catch (\Throwable $error)
		{
			Log::add('Localise Studio file check failed: ' . $error->getMessage(), Log::WARNING, 'com_localise');
		}
	}
}
