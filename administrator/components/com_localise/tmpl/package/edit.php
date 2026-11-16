<?php
/**
 * @package     Com_Localise
 * @subpackage  views
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Localise\Administrator\Model\PackageModel;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('jquery.framework');
HTMLHelper::_('script', 'com_localise/package-files.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
HTMLHelper::_('bootstrap.modal', '.modal', []);

$this->form->setFieldAttribute('name', 'label', 'COM_LOCALISE_INTERNAL_PACKAGE_NAME');
$this->form->setFieldAttribute('name', 'description', 'COM_LOCALISE_INTERNAL_PACKAGE_NAME_DESC');

$fieldSets = $this->form->getFieldsets();
$ftpSets   = $this->formftp->getFieldsets();

// Load the file-picker strings independently of older component language packs.
$language = Factory::getApplication()->getLanguage();
$language->load('com_localise_picker', JPATH_COMPONENT_ADMINISTRATOR);

$automaticFiles = [];
foreach (glob(JPATH_ROOT . '/language/*', GLOB_ONLYDIR) ?: [] as $directory)
{
	$tag = basename($directory);
	$files = [
		'site' => ['joomla.ini', 'localise.php', 'install.xml', 'langmetadata.xml'],
		'administrator' => ['joomla.ini', 'localise.php', 'install.xml', 'langmetadata.xml'],
		'api' => ['joomla.ini', 'install.xml', 'langmetadata.xml'],
	];

	$optional = [
		'site' => [
			'com_finder.commonwords.txt' => $directory . '/com_finder.commonwords.txt',
		],
		'administrator' => [
			$tag . '.css' => JPATH_ADMINISTRATOR . '/language/' . $tag . '/' . $tag . '.css',
		],
	];

	foreach (['calendar-setup.js', 'calendar-setup-uncompressed.js', 'index.html', 'calendar.js', 'calendar-uncompressed.js'] as $asset)
	{
		$optional['site']['js/' . $asset] = JPATH_ROOT . '/media/' . $tag . '/js/' . $asset;
	}

	foreach ($optional as $client => $paths)
	{
		foreach ($paths as $filename => $path)
		{
			if (is_file($path) && filesize($path) > 0)
			{
				$files[$client][] = $filename;
			}
		}
	}

	$automaticFiles[$tag] = $files;
}

Text::script('COM_LOCALISE_MSG_CONFIRM_PACKAGE_SAVE');

Factory::getDocument()->addScriptDeclaration("
	(function($){
		$(document).ready(function () {
			$('.fileupload').click(function(e){

				var form = $('#filemodalForm');

				// Assign task
				form.find('input[name=task]').val('package.uploadOtherFile');

				// Submit the form
				if (confirm('" . Text::_('COM_LOCALISE_MSG_FILES_VALID_IMPORT') . "'))
				{
					form.trigger('submit');
				}

				// Avoid the standard link action
				e.preventDefault();
			});
		});
	})(jQuery);

	Joomla.submitbutton = function(task)
	{
		if ((task == 'package.apply' || task == 'package.save') && document.formvalidator.isValid(document.getElementById('localise-package-form')))
		{
			if (confirm(Joomla.JText._('COM_LOCALISE_MSG_CONFIRM_PACKAGE_SAVE')))
			{
				Joomla.submitform(task, document.getElementById('localise-package-form'));
			}
		}
		else if (task == 'package.cancel' || task == 'package.download')
		{
			Joomla.submitform(task, document.getElementById('localise-package-form'));
		}
	}
");

Factory::getDocument()->addScriptDeclaration("
function updateTranslationsList() {
	var packagename = jQuery('#jform_name').val();
	var languagetag = jQuery('#jform_language').val();
	var token = '". Session::getFormToken() ."';
	var required_data = JSON.stringify([{
										'packagename': packagename,
										'languagetag': languagetag
										}]);
	jQuery.post('index.php',{
		'option' : 'com_localise',
		'controller' : 'package',
		'task' : 'package.updatetranslationslist',
		'format' : 'raw',
		'data' : required_data,
		[token] : '1',
		'dataType' : 'json'
		})
	.done(function(result, textStatus, jqXHR)
	{
		const reply = JSON.parse(result);
		//console.log(reply);

		if (!reply.success && reply.message)
		{
			// Success flag is set to 'false' and main response message given
			// so we can alert it or insert it into some HTML element
			alert(reply.message);
		}

		if (reply.messages)
		{
			// All the enqueued messages of the app object can simple be
			// rendered by the respective helper function of Joomla!
			// They will automatically be displayed at the messages section of the template
			Joomla.renderMessages(reply.messages);
		}

		if (reply.data)
		{
			// Here we can access all the data of our response

			if (reply.success)
			{
				if (reply.data.html !== '')
				{
					var updated = jQuery(reply.data.html).find('select').addBack('select').first();
					if (updated.length)
					{
						jQuery('#jform_translations').empty().append(updated.children());
					}
				}

				if (reply.data.success_message)
				{
					jQuery('#flash-message-success').empty().show().html(reply.data.success_message).delay(2000).fadeOut(300);
				}
				else if (reply.data.error_message)
				{
					jQuery('#flash-message-danger').empty().show().html(reply.data.error_message).delay(2000).fadeOut(300);
				}
			}
			else
			{
				if (reply.data.error_message)
				{
					jQuery('#flash-message-danger').empty().show().html(reply.data.error_message).delay(2000).fadeOut(300);
				}
			}
		}
	})
	.fail(function(jqXHR, textStatus, errorThrown)
	{
		//console.log('ajax call failed\\n' + textStatus + '\\n'+ errorThrown);

		// Reaching this point means that the Ajax request itself was not successful
		// So JsonResponse was never called

		// Here we can handle an alert message type 'System message', creating it. Sample:
		//var messages = {
		//					'message': ['Sample message one', 'Sample message two'],
		//					'error': ['Sample error one', 'Sample error two']
		//};

		var messages = {
						'error': ['" . Text::_('COM_LOCALISE_TASK_THROWN_ERROR') . "']
		};

		Joomla.renderMessages(messages);

		// Here we can handle an alert message type 'flash'.
		jQuery('#flash-message-danger').empty().show().html('" . Text::_('COM_LOCALISE_TASK_THROWN_ERROR_FLASH') . "').delay(2000).fadeOut(300);
	});
}


jQuery(document).ready(function() {
	jQuery('#jform_language').change(function(){
		updateTranslationsList();
	})
});
");
?>
<?php
	echo '<div class="text-center"><div id="flash-message-danger" class="alert alert-danger flash-message" style="display: none;"></div></div>';
	echo '<div class="text-center"><div id="flash-message-notice" class="alert alert-notice flash-message" style="display: none;"></div></div>';
	echo '<div class="text-center"><div id="flash-message-success" class="alert alert-success flash-message" style="display: none;"></div></div>';
?>
<form action="<?php echo Route::_('index.php?option=com_localise&view=package&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="localise-package-form" class="form-validate">
	<div class="row-fluid">
		<!-- Begin Localise Package -->
		<div class="col-md-12">
				<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => $this->ftp ? 'ftp' : 'default')); ?>
					<?php if ($this->ftp) : ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ftp', Text::_($ftpSets['ftp']->label, true)); ?>
						<?php if (!empty($ftpSets['ftp']->description)):?>
								<p class="tip"><?php echo Text::_($ftpSets['ftp']->description); ?></p>
						<?php endif;?>
						<?php if ($this->ftp instanceof Exception): ?>
								<p class="error"><?php echo Text::_($this->ftp->message); ?></p>
						<?php endif; ?>
						<?php foreach($this->formftp->getFieldset('ftp',false) as $field) : ?>
							<?php echo $field->renderField(); ?>
						<?php endforeach; ?>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<?php endif; ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'default', Text::_($fieldSets['default']->label, true)); ?>
						<div class="row">
						<div class="col-lg-12 col-xl-6">
							<fieldset id="fieldset-default" class="options-form">
								<legend><?php echo Text::_('COM_LOCALISE_FIELDSET_PACKAGE_DETAIL'); ?></legend>
								<div class="mb-3" id="localise-archive-preview" data-template="<?php echo PackageModel::CORE_ARCHIVE_NAME; ?>" hidden>
									<label for="localise-archive-filename"><?php echo Text::_('COM_LOCALISE_CORE_ARCHIVE_FILENAME'); ?></label>
									<input type="text" class="form-control font-monospace" id="localise-archive-filename" name="jform[archive_filename]" aria-describedby="localise-archive-warning" readonly>
									<label class="d-block mt-2">
										<input type="checkbox" id="localise-custom-archive" name="jform[custom_archive]" value="1">
										<?php echo Text::_('COM_LOCALISE_CUSTOM_ARCHIVE'); ?>
									</label>
									<p class="alert alert-warning mt-2" id="localise-archive-warning" hidden><?php echo Text::_('COM_LOCALISE_CUSTOM_ARCHIVE_WARNING'); ?></p>
								</div>
								<?php foreach($this->form->getFieldset('default') as $field) : ?>
									<?php echo $field->renderField(); ?>
								<?php endforeach; ?>
							</fieldset>
						</div>
						<div class="col-lg-12 col-xl-6">
							<fieldset id="fieldset-translations" class="options-form">
								<legend><?php echo Text::_($fieldSets['translations']->label); ?></legend>
								<?php if (!empty($fieldSets['translations']->description)):?>
										<p><?php echo Text::_($fieldSets['translations']->description); ?></p>
								<?php endif;?>
								<div id="localise-package-files" data-missing-label="<?php echo htmlspecialchars(Text::_('COM_LOCALISE_PACKAGE_FILES_MISSING'), ENT_QUOTES, 'UTF-8'); ?>" hidden>
									<label for="localise-package-file-search"><?php echo Text::_('COM_LOCALISE_PACKAGE_FILES_SEARCH'); ?></label>
									<input type="search" id="localise-package-file-search" class="form-control mb-2">
									<div class="d-flex flex-wrap gap-2 mb-2">
										<button type="button" class="btn btn-outline-secondary" data-file-action="add"><?php echo Text::_('COM_LOCALISE_PACKAGE_FILES_ADD_VISIBLE'); ?></button>
										<button type="button" class="btn btn-outline-secondary" data-file-action="remove"><?php echo Text::_('COM_LOCALISE_PACKAGE_FILES_REMOVE_VISIBLE'); ?></button>
									</div>
									<label class="d-block mb-2"><input type="checkbox" data-file-selected> <?php echo Text::_('COM_LOCALISE_PACKAGE_FILES_SELECTED_ONLY'); ?></label>
									<output class="d-block mb-2" aria-live="polite" data-file-count></output>
									<div class="overflow-auto border rounded p-2" style="max-height: 24rem" data-file-list></div>
									<details class="mt-3" data-automatic-files hidden>
										<summary><?php echo Text::_('COM_LOCALISE_PACKAGE_FILES_AUTOMATIC'); ?></summary>
										<ul class="mt-2 mb-0" data-automatic-list></ul>
									</details>
									<script type="application/json" data-automatic-map><?php echo json_encode($automaticFiles, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
								</div>
								<?php foreach($this->form->getFieldset('translations') as $field) : ?>
									<?php echo $field->renderField(); ?>
								<?php endforeach; ?>
							</fieldset>
						</div>
						</div>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'permissions', Text::_($fieldSets['permissions']->label, true)); ?>
						<fieldset id="fieldset-rules" class="options-form">
							<legend><?php echo Text::_($fieldSets['permissions']->label, true); ?></legend>
							<div>
								<?php echo $this->form->getInput('rules'); ?>
							</div>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<input type="hidden" name="task" value="" />
					<?php echo HTMLHelper::_('form.token'); ?>
				<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
		</div>
		<!-- End Localise Package -->
	</div>
</form>

<div id="fileModal" class="modal hide fade">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title"><?php echo Text::_('COM_LOCALISE_IMPORT_NEW_FILE_HEADER'); ?></h3>
			</div>
			<div class="contentpane component">
				<p><?php echo Text::_('COM_LOCALISE_IMPORT_NEW_FILE_DESC'); ?></p>
				<form method="post" action="<?php echo Route::_('index.php?option=com_localise&task=package.uploadOtherFile&file=' . $this->file); ?>"
					class="well" enctype="multipart/form-data" name="filemodalForm" id="filemodalForm">
					<fieldset>
						<label><?php echo Text::_('COM_LOCALISE_TEXT_CLIENT'); ?></label>
						<select name="location" class="custom-select" type="location" required >
							<option value="admin"><?php echo Text::_('JADMINISTRATOR'); ?></option>
							<option value="site"><?php echo Text::_('JSITE'); ?></option>
						</select>
						<label></label>
						<input type="file" name="files" required />
							<a href="#" class="btn btn-primary fileupload">
							<?php echo Text::_('COM_LOCALISE_BUTTON_IMPORT'); ?>
							</a>
					</fieldset>
				</form>
			</div>
			<div class="modal-footer">
				<a role="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-hidden="true"><?php echo Text::_('COM_LOCALISE_MODAL_CLOSE'); ?></a>
			</div>
		</div>
	</div>
</div>

